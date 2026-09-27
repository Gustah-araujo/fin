<?php

declare(strict_types=1);

namespace Tests\Feature\Cards;

use App\Models\CreditCardBill;
use App\Models\Transaction;
use App\Services\BillService;
use App\Services\CreditCardService;
use Carbon\Carbon;

class CardExpenseDeletionTest extends CardTestCase
{
    public function test_card_expense_can_be_deleted(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user, ['credit_limit' => 5000, 'available_limit' => 5000]);
        $category = $this->createExpenseCategory($workspace, $user);

        $today = Carbon::today()->format('Y-m-d');

        $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Para deletar',
                'value' => 300.00,
                'date' => $today,
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
            ]);

        $transaction = Transaction::where('description', 'Para deletar')->first();
        $this->assertNotNull($transaction);

        $billId = $transaction->credit_card_bill_id;
        $bill = CreditCardBill::find($billId);
        $this->assertEquals(300.00, (float) $bill->total_amount);

        $response = $this->actingAs($user)
            ->delete(route('transactions.destroy', [$workspace, $transaction]));

        $response->assertRedirect(route('transactions.index', $workspace));

        // Transaction should be soft-deleted
        $this->assertSoftDeleted('transactions', ['id' => $transaction->id]);

        // Bill total should be recalculated
        $bill->refresh();
        $this->assertEquals(0.00, (float) $bill->total_amount);

        // Available limit should be recalculated
        app(CreditCardService::class)->recalculateAvailableLimit($card->fresh());
        $this->assertEquals(5000.00, (float) $card->fresh()->available_limit);
    }

    public function test_deleting_installment_group_deletes_this_and_future(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $today = Carbon::today()->format('Y-m-d');

        // Create 5 installments
        $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Parcela group delete',
                'value' => 100.00,
                'date' => $today,
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
                'installments' => 5,
                'total_value' => 500.00,
            ]);

        $transactions = Transaction::where('description', 'Parcela group delete')
            ->whereNull('deleted_at')
            ->orderBy('installment_number')
            ->get();

        $this->assertEquals(5, $transactions->count());

        // Delete installment 3 (should delete 3, 4, 5)
        $target = $transactions[2]; // installment #3

        $response = $this->actingAs($user)
            ->delete(route('transactions.destroy', [$workspace, $target]));

        $response->assertRedirect();

        // Installments 1 and 2 should remain
        $this->assertDatabaseHas('transactions', ['id' => $transactions[0]->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('transactions', ['id' => $transactions[1]->id, 'deleted_at' => null]);

        // Installments 3, 4, 5 should be soft-deleted
        $this->assertSoftDeleted('transactions', ['id' => $transactions[2]->id]);
        $this->assertSoftDeleted('transactions', ['id' => $transactions[3]->id]);
        $this->assertSoftDeleted('transactions', ['id' => $transactions[4]->id]);
    }

    public function test_deleting_expense_on_paid_bill_blocked(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);
        $account = $this->createAccount($workspace, $user);

        $today = Carbon::today()->format('Y-m-d');

        // Create expense
        $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Despesa fatura paga delete',
                'value' => 200.00,
                'date' => $today,
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
            ]);

        $transaction = Transaction::where('description', 'Despesa fatura paga delete')->first();
        $bill = CreditCardBill::find($transaction->credit_card_bill_id);

        // Close and pay the bill
        app(BillService::class)->payBill($bill, $account, $user);

        // Try to delete
        $response = $this->actingAs($user)
            ->delete(route('transactions.destroy', [$workspace, $transaction]));

        $response->assertSessionHasErrors(['bill']);

        // Transaction should still exist
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'deleted_at' => null,
        ]);
    }
}
