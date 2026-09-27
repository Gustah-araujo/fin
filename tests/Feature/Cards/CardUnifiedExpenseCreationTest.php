<?php

declare(strict_types=1);

namespace Tests\Feature\Cards;

use App\Models\CreditCardBill;
use App\Models\Transaction;
use Carbon\Carbon;

class CardUnifiedExpenseCreationTest extends CardTestCase
{
    public function test_single_card_expense_can_be_created_via_unified_form(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $today = Carbon::today()->format('Y-m-d');

        $response = $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Compra no mercado',
                'value' => 150.00,
                'date' => $today,
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
            ]);

        $response->assertRedirect(route('transactions.index', $workspace));

        $transaction = Transaction::where('description', 'Compra no mercado')->first();
        $this->assertNotNull($transaction);
        $this->assertEquals($card->id, $transaction->credit_card_id);
        $this->assertNull($transaction->account_id);
        $this->assertNull($transaction->paid_at);
        $this->assertNotNull($transaction->credit_card_bill_id);

        // Bill total should be recalculated
        $bill = CreditCardBill::find($transaction->credit_card_bill_id);
        $this->assertNotNull($bill);
        $this->assertEquals(150.00, (float) $bill->total_amount);
    }

    public function test_installment_card_expense_creates_n_transactions(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $today = Carbon::today()->format('Y-m-d');

        $response = $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Notebook parcelado',
                'value' => 100.00,
                'date' => $today,
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
                'installments' => 12,
                'total_value' => 1200.00,
            ]);

        $response->assertRedirect(route('transactions.index', $workspace));

        $transactions = Transaction::where('description', 'Notebook parcelado')
            ->whereNull('deleted_at')
            ->orderBy('installment_number')
            ->get();

        $this->assertEquals(12, $transactions->count());

        $groupId = $transactions->first()->installment_group_id;
        $this->assertNotNull($groupId);

        foreach ($transactions as $tx) {
            $this->assertEquals(100.00, (float) $tx->value);
            $this->assertEquals($card->id, $tx->credit_card_id);
            $this->assertNull($tx->account_id);
            $this->assertNull($tx->paid_at);
            $this->assertEquals($groupId, $tx->installment_group_id);
            $this->assertNotNull($tx->credit_card_bill_id);
        }
    }

    public function test_installment_with_remainder_goes_to_last(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $today = Carbon::today()->format('Y-m-d');

        $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Parcela com resto',
                'value' => 33.33,
                'date' => $today,
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
                'installments' => 3,
                'total_value' => 100.00,
            ]);

        $transactions = Transaction::where('description', 'Parcela com resto')
            ->whereNull('deleted_at')
            ->orderBy('installment_number')
            ->get();

        $this->assertEquals(3, $transactions->count());
        $this->assertEquals(33.33, (float) $transactions[0]->value);
        $this->assertEquals(33.33, (float) $transactions[1]->value);
        $this->assertEquals(33.34, (float) $transactions[2]->value);
    }

    public function test_both_account_and_card_rejected(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $account = $this->createAccount($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $response = $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Ambos',
                'value' => 100,
                'date' => Carbon::today()->format('Y-m-d'),
                'account_id' => $account->uuid,
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
            ]);

        $response->assertSessionHasErrors(['account_id']);
    }

    public function test_neither_account_nor_card_rejected(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $category = $this->createExpenseCategory($workspace, $user);

        $response = $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Sem fonte',
                'value' => 100,
                'date' => Carbon::today()->format('Y-m-d'),
                'category_id' => $category->uuid,
            ]);

        $response->assertSessionHasErrors(['account_id']);
    }

    public function test_installments_require_total_value(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $response = $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Sem total',
                'value' => 100,
                'date' => Carbon::today()->format('Y-m-d'),
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
                'installments' => 3,
            ]);

        $response->assertSessionHasErrors(['total_value']);
    }

    public function test_card_recurrence_locks_installments_to_1(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $response = $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Recorrência parcelada',
                'value' => 100,
                'date' => Carbon::today()->format('Y-m-d'),
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
                'is_recurring' => true,
                'frequency' => 'monthly',
                'frequency_day' => (int) Carbon::today()->format('d'),
                'installments' => 3,
            ]);

        $response->assertSessionHasErrors(['installments']);
    }

    public function test_single_installment_without_total_value_accepted(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $response = $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Compra simples',
                'value' => 100,
                'date' => Carbon::today()->format('Y-m-d'),
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'description' => 'Compra simples',
            'value' => 100.00,
        ]);
    }
}
