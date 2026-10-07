<?php

declare(strict_types=1);

namespace Tests\Feature\Cards;

use App\Models\CreditCardBill;
use App\Models\Transaction;
use App\Services\BillService;
use Carbon\Carbon;

class CardExpenseEditingTest extends CardTestCase
{
    public function test_card_expense_can_be_edited_via_unified_form(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $today = Carbon::today()->format('Y-m-d');

        // Create a single card expense via the store endpoint
        $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Compra original',
                'value' => 200.00,
                'date' => $today,
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
            ]);

        $transaction = Transaction::where('description', 'Compra original')->first();
        $this->assertNotNull($transaction);
        $originalBillId = $transaction->credit_card_bill_id;

        // Edit via the update endpoint
        $response = $this->actingAs($user)
            ->put(route('transactions.update', [$workspace, $transaction]), [
                'description' => 'Compra atualizada',
                'value' => 250.00,
                'date' => $today,
                'category_id' => $category->uuid,
            ]);

        $response->assertRedirect(route('transactions.index', $workspace));

        $transaction->refresh();
        $this->assertEquals('Compra atualizada', $transaction->description);
        $this->assertEquals(250.00, (float) $transaction->value);
        // Bill stays the same since date didn't change
        $this->assertEquals($originalBillId, $transaction->credit_card_bill_id);
    }

    public function test_card_expense_date_change_rebuckets_to_new_bill(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user, ['closing_day' => 1, 'due_day' => 10]);
        $category = $this->createExpenseCategory($workspace, $user);

        // Create expense in the current period
        $today = Carbon::today();
        $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Rebucketeable',
                'value' => 300.00,
                'date' => $today->format('Y-m-d'),
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
            ]);

        $transaction = Transaction::where('description', 'Rebucketeable')->first();
        $oldBillId = $transaction->credit_card_bill_id;
        $oldBill = CreditCardBill::find($oldBillId);
        $oldTotal = (float) $oldBill->total_amount;

        // Move date to a different month (next month)
        $newDate = $today->copy()->addMonthsNoOverflow()->format('Y-m-d');

        $response = $this->actingAs($user)
            ->put(route('transactions.update', [$workspace, $transaction]), [
                'description' => 'Rebucketeable',
                'value' => 300.00,
                'date' => $newDate,
                'category_id' => $category->uuid,
            ]);

        $response->assertRedirect();

        $transaction->refresh();
        $oldBill->refresh();

        // Old bill total should decrease
        $this->assertEquals($oldTotal - 300.00, (float) $oldBill->total_amount);

        // Transaction should now be on a different bill
        $this->assertNotEquals($oldBillId, $transaction->credit_card_bill_id);

        // New bill total should increase
        $newBill = CreditCardBill::find($transaction->credit_card_bill_id);
        $this->assertNotNull($newBill);
        $this->assertEquals(300.00, (float) $newBill->total_amount);
    }

    public function test_installment_group_edit_updates_this_and_future(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $today = Carbon::today()->format('Y-m-d');

        // Create 3 installments
        $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Parcela Original',
                'value' => 100.00,
                'date' => $today,
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
                'installments' => 3,
                'total_value' => 300.00,
            ]);

        $transactions = Transaction::where('description', 'Parcela Original')
            ->whereNull('deleted_at')
            ->orderBy('installment_number')
            ->get();

        $this->assertEquals(3, $transactions->count());

        // Edit with scope=group (installment 2 onwards)
        $target = $transactions[1]; // installment #2
        $response = $this->actingAs($user)
            ->put(route('transactions.update', [$workspace, $target]), [
                'description' => 'Parcela Atualizada',
                'value' => 100.00,
                'date' => $today,
                'category_id' => $category->uuid,
                'scope' => 'group',
            ]);

        $response->assertRedirect();

        // Installments 2 and 3 should be updated
        $updated2 = Transaction::find($target->id);
        $this->assertEquals('Parcela Atualizada', $updated2->description);

        $updated3 = Transaction::find($transactions[2]->id);
        $this->assertEquals('Parcela Atualizada', $updated3->description);

        // Installment 1 should remain unchanged
        $unchanged1 = Transaction::find($transactions[0]->id);
        $this->assertEquals('Parcela Original', $unchanged1->description);
    }

    public function test_editing_expense_on_paid_bill_blocked(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);
        $account = $this->createAccount($workspace, $user);

        $today = Carbon::today()->format('Y-m-d');

        // Create expense
        $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Despesa em fatura paga',
                'value' => 200.00,
                'date' => $today,
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
            ]);

        $transaction = Transaction::where('description', 'Despesa em fatura paga')->first();
        $bill = CreditCardBill::find($transaction->credit_card_bill_id);

        // Close and pay the bill
        app(BillService::class)->payBill($bill, $account, $user);

        // Try to edit
        $response = $this->actingAs($user)
            ->put(route('transactions.update', [$workspace, $transaction]), [
                'description' => 'Tentativa de edição',
                'value' => 200.00,
                'date' => $today,
                'category_id' => $category->uuid,
            ]);

        $response->assertSessionHasErrors(['bill']);
    }
}
