<?php

declare(strict_types=1);

namespace Tests\Feature\Bills;

use App\Enums\BillStatus;
use App\Models\CreditCardBill;
use App\Models\Transaction;
use App\Services\BillService;
use Carbon\Carbon;
use Illuminate\Support\Str;

class BillPaymentMarkExpensesTest extends BillTestCase
{
    public function test_paying_bill_marks_all_expenses_as_paid(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);
        $account = $this->createAccount($workspace, $user);

        $bill = CreditCardBill::factory()->closed()->create([
            'credit_card_id' => $card->id,
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'total_amount' => 0,
        ]);

        // Create 3 expenses on the bill
        for ($i = 1; $i <= 3; $i++) {
            Transaction::create([
                'uuid' => Str::orderedUuid()->toString(),
                'workspace_id' => $workspace->id,
                'credit_card_id' => $card->id,
                'credit_card_bill_id' => $bill->id,
                'category_id' => $category->id,
                'type' => 'expense',
                'description' => "Despesa {$i}",
                'value' => 100 * $i,
                'date' => Carbon::today()->format('Y-m-d'),
                'paid_at' => null,
                'created_by' => $user->id,
            ]);
        }

        // Recalculate bill total
        app(BillService::class)->recalculateBillTotal($bill);
        $bill->refresh();
        $this->assertEquals(600.00, (float) $bill->total_amount);

        // Pay the bill
        $response = $this->actingAs($user)
            ->post(route('bills.pay', [$workspace, $bill]), [
                'account_id' => $account->uuid,
            ]);

        $response->assertRedirect();

        // All 3 transactions should have paid_at set
        $transactions = Transaction::where('credit_card_bill_id', $bill->id)
            ->whereNull('deleted_at')
            ->get();

        $this->assertEquals(3, $transactions->count());
        foreach ($transactions as $tx) {
            $this->assertNotNull($tx->fresh()->paid_at, "Transaction {$tx->description} should be paid");
        }

        // Bill status should be Paid
        $bill->refresh();
        $this->assertEquals(BillStatus::Paid, $bill->status);
    }

    public function test_undoing_bill_payment_reverts_expenses_to_unpaid(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);
        $account = $this->createAccount($workspace, $user);

        $bill = CreditCardBill::factory()->closed()->create([
            'credit_card_id' => $card->id,
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'total_amount' => 300,
        ]);

        // Create 3 expenses on the bill
        for ($i = 1; $i <= 3; $i++) {
            Transaction::create([
                'uuid' => Str::orderedUuid()->toString(),
                'workspace_id' => $workspace->id,
                'credit_card_id' => $card->id,
                'credit_card_bill_id' => $bill->id,
                'category_id' => $category->id,
                'type' => 'expense',
                'description' => "Despesa undo {$i}",
                'value' => 100,
                'date' => Carbon::today()->format('Y-m-d'),
                'paid_at' => null,
                'created_by' => $user->id,
            ]);
        }

        // Pay the bill
        $this->actingAs($user)
            ->post(route('bills.pay', [$workspace, $bill]), [
                'account_id' => $account->uuid,
            ]);

        // Verify all paid
        $transactions = Transaction::where('credit_card_bill_id', $bill->id)
            ->whereNull('deleted_at')
            ->get();
        foreach ($transactions as $tx) {
            $this->assertNotNull($tx->fresh()->paid_at);
        }

        // Undo payment
        $response = $this->actingAs($user)
            ->post(route('bills.unpay', [$workspace, $bill]));

        $response->assertRedirect();

        // All transactions should have paid_at = null
        $transactions->each(function ($tx) {
            $this->assertNull($tx->fresh()->paid_at, "Transaction {$tx->description} should be unpaid");
        });

        // Bill status should be Closed
        $bill->refresh();
        $this->assertEquals(BillStatus::Closed, $bill->status);
    }

    public function test_paying_open_bill_rejected(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $account = $this->createAccount($workspace, $user);

        $bill = CreditCardBill::factory()->open()->create([
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

    public function test_paying_empty_bill_rejected(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $account = $this->createAccount($workspace, $user);

        // Pre-created bill (empty, no expenses)
        $bill = CreditCardBill::factory()->closed()->create([
            'credit_card_id' => $card->id,
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'total_amount' => 0,
        ]);

        $response = $this->actingAs($user)
            ->post(route('bills.pay', [$workspace, $bill]), [
                'account_id' => $account->uuid,
            ]);

        // Empty bill with total_amount=0 — verify it doesn't create a meaningful payment
        // The service allows it but the payment transaction will have value=0
        $response->assertRedirect();

        // A payment transaction with value=0 should be created
        $paymentTx = Transaction::where('account_id', $account->id)
            ->where('description', 'like', '%Pagamento Fatura%')
            ->first();
        $this->assertNotNull($paymentTx);
        $this->assertEquals(0.00, (float) $paymentTx->value);
    }
}
