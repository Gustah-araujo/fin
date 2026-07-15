<?php

declare(strict_types=1);

namespace Tests\Feature\Bills;

use App\Enums\BillStatus;
use App\Models\Account;
use App\Models\Transaction;
use App\Services\BillService;
use App\Services\CreditCardService;
use Tests\Feature\CardExpenses\CardExpenseTestCase;

class BillPaymentTest extends CardExpenseTestCase
{
    public function test_can_pay_closed_bill(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);
        $account = $this->createAccount($workspace, $user);

        $bill = \App\Models\CreditCardBill::factory()->closed()->create([
            'credit_card_id' => $card->id,
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'total_amount' => 1000,
        ]);

        $response = $this->actingAs($user)
            ->post(route('bills.pay', [$workspace, $bill]), [
                'account_id' => $account->uuid,
            ]);

        $response->assertRedirect();
        $bill->refresh();
        $this->assertEquals(BillStatus::Paid, $bill->status);
        $this->assertNotNull($bill->paid_at);
        $this->assertNotNull($bill->payment_transaction_id);
    }

    public function test_bill_payment_creates_debit_transaction(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);
        $account = $this->createAccount($workspace, $user);

        $bill = \App\Models\CreditCardBill::factory()->closed()->create([
            'credit_card_id' => $card->id,
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'total_amount' => 500,
        ]);

        $this->actingAs($user)
            ->post(route('bills.pay', [$workspace, $bill]), [
                'account_id' => $account->uuid,
            ]);

        $paymentTx = Transaction::where('description', 'like', '%Pagamento Fatura%')
            ->where('account_id', $account->id)
            ->first();

        $this->assertNotNull($paymentTx);
        $this->assertEquals('expense', $paymentTx->type->value);
        $this->assertNotNull($paymentTx->paid_at);
        $this->assertEquals(500, (float) $paymentTx->value);
    }

    public function test_bill_payment_deducts_account_balance(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $account = $this->createAccount($workspace, $user, 5000);

        $bill = \App\Models\CreditCardBill::factory()->closed()->create([
            'credit_card_id' => $card->id,
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'total_amount' => 1000,
        ]);

        $this->actingAs($user)
            ->post(route('bills.pay', [$workspace, $bill]), [
                'account_id' => $account->uuid,
            ]);

        $account->refresh();
        $this->assertEquals(4000, (float) $account->current_balance);
    }

    public function test_bill_payment_restores_available_limit(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);
        $account = $this->createAccount($workspace, $user);

        $bill = \App\Models\CreditCardBill::factory()->closed()->create([
            'credit_card_id' => $card->id,
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'total_amount' => 1000,
        ]);

        Transaction::create([
            'uuid' => \Illuminate\Support\Str::orderedUuid()->toString(),
            'workspace_id' => $workspace->id,
            'credit_card_id' => $card->id,
            'credit_card_bill_id' => $bill->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'description' => 'Expense on bill',
            'value' => 1000,
            'date' => '2026-07-01',
            'paid_at' => null,
            'created_by' => $user->id,
        ]);

        app(CreditCardService::class)->recalculateAvailableLimit($card->fresh());
        $this->assertEquals(4000, (float) $card->fresh()->available_limit);

        $this->actingAs($user)
            ->post(route('bills.pay', [$workspace, $bill]), [
                'account_id' => $account->uuid,
            ]);

        $this->assertEquals(5000, (float) $card->fresh()->available_limit);
    }

    public function test_cannot_pay_open_bill(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $account = $this->createAccount($workspace, $user);

        $bill = \App\Models\CreditCardBill::factory()->open()->create([
            'credit_card_id' => $card->id,
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->post(route('bills.pay', [$workspace, $bill]), [
                'account_id' => $account->uuid,
            ]);

        $response->assertSessionHasErrors(['bill']);
    }

    public function test_cannot_pay_already_paid_bill(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $account = $this->createAccount($workspace, $user);

        $bill = \App\Models\CreditCardBill::factory()->paid()->create([
            'credit_card_id' => $card->id,
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'paid_to_account_id' => $account->id,
        ]);

        $response = $this->actingAs($user)
            ->post(route('bills.pay', [$workspace, $bill]), [
                'account_id' => $account->uuid,
            ]);

        $response->assertSessionHasErrors(['bill']);
    }

    public function test_viewer_cannot_pay_bill(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember('viewer');
        $card = $this->createCard($workspace, $user);
        $account = $this->createAccount($workspace, $user);

        $bill = \App\Models\CreditCardBill::factory()->closed()->create([
            'credit_card_id' => $card->id,
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'total_amount' => 100,
        ]);

        $response = $this->actingAs($user)
            ->post(route('bills.pay', [$workspace, $bill]), [
                'account_id' => $account->uuid,
            ]);

        $response->assertForbidden();
    }

    public function test_payment_category_auto_created_if_missing(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $account = $this->createAccount($workspace, $user);

        $bill = \App\Models\CreditCardBill::factory()->closed()->create([
            'credit_card_id' => $card->id,
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'total_amount' => 100,
        ]);

        $this->assertDatabaseMissing('categories', [
            'workspace_id' => $workspace->id,
            'name' => 'Pagamento de Cartão',
        ]);

        $this->actingAs($user)
            ->post(route('bills.pay', [$workspace, $bill]), [
                'account_id' => $account->uuid,
            ]);

        $this->assertDatabaseHas('categories', [
            'workspace_id' => $workspace->id,
            'name' => 'Pagamento de Cartão',
            'is_system' => true,
        ]);
    }

    public function test_insufficient_balance_allows_payment(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $account = $this->createAccount($workspace, $user, 100);

        $bill = \App\Models\CreditCardBill::factory()->closed()->create([
            'credit_card_id' => $card->id,
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'total_amount' => 500,
        ]);

        $response = $this->actingAs($user)
            ->post(route('bills.pay', [$workspace, $bill]), [
                'account_id' => $account->uuid,
            ]);

        $response->assertRedirect();
        $account->refresh();
        $this->assertEquals(-400, (float) $account->current_balance);
    }

    public function test_can_undo_bill_payment(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $account = $this->createAccount($workspace, $user, 5000);

        $bill = \App\Models\CreditCardBill::factory()->closed()->create([
            'credit_card_id' => $card->id,
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'total_amount' => 1000,
        ]);

        $this->actingAs($user)
            ->post(route('bills.pay', [$workspace, $bill]), [
                'account_id' => $account->uuid,
            ]);

        $this->assertEquals(4000, (float) $account->fresh()->current_balance);

        $this->actingAs($user)
            ->post(route('bills.unpay', [$workspace, $bill]));

        $bill->refresh();
        $this->assertEquals(BillStatus::Closed, $bill->status);
        $this->assertNull($bill->paid_at);
        $this->assertNull($bill->payment_transaction_id);

        $account->refresh();
        $this->assertEquals(5000, (float) $account->current_balance);
    }
}
