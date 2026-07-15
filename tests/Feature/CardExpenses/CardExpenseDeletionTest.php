<?php

declare(strict_types=1);

namespace Tests\Feature\CardExpenses;

use App\Models\Transaction;
use Tests\Feature\CardExpenses\CardExpenseTestCase;

class CardExpenseDeletionTest extends CardExpenseTestCase
{
    public function test_delete_single_scope_soft_deletes_one(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('card-expenses.store', [$workspace, $card]), [
                'description' => 'Del Single',
                'total_value' => 300,
                'value' => 100,
                'date' => '2026-02-15',
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
                'installments' => 3,
            ]);

        $transactions = Transaction::where('description', 'Del Single')->orderBy('installment_number')->get();
        $installment2 = $transactions[1];

        $response = $this->actingAs($user)
            ->delete(route('card-expenses.destroy', [$workspace, $card, $installment2]), [
                'scope' => 'single',
            ]);

        $response->assertRedirect();

        $this->assertSoftDeleted($installment2);
        $this->assertDatabaseHas('transactions', ['id' => $transactions[0]->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('transactions', ['id' => $transactions[2]->id, 'deleted_at' => null]);
    }

    public function test_delete_group_scope_soft_deletes_future(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('card-expenses.store', [$workspace, $card]), [
                'description' => 'Del Group',
                'total_value' => 300,
                'value' => 100,
                'date' => '2026-02-15',
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
                'installments' => 3,
            ]);

        $transactions = Transaction::where('description', 'Del Group')->orderBy('installment_number')->get();
        $installment2 = $transactions[1];

        $response = $this->actingAs($user)
            ->delete(route('card-expenses.destroy', [$workspace, $card, $installment2]), [
                'scope' => 'group',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('transactions', ['id' => $transactions[0]->id, 'deleted_at' => null]);
        $this->assertSoftDeleted($transactions[1]);
        $this->assertSoftDeleted($transactions[2]);
    }

    public function test_delete_all_installments_no_orphan_group(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('card-expenses.store', [$workspace, $card]), [
                'description' => 'Del All',
                'total_value' => 300,
                'value' => 100,
                'date' => '2026-02-15',
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
                'installments' => 3,
            ]);

        $transactions = Transaction::where('description', 'Del All')->orderBy('installment_number')->get();
        $installment1 = $transactions[0];

        $this->actingAs($user)
            ->delete(route('card-expenses.destroy', [$workspace, $card, $installment1]), [
                'scope' => 'group',
            ]);

        $activeCount = Transaction::where('description', 'Del All')->whereNull('deleted_at')->count();
        $this->assertEquals(0, $activeCount);
    }

    public function test_cannot_delete_installment_on_paid_bill(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $bill = \App\Models\CreditCardBill::factory()->paid()->create([
            'credit_card_id' => $card->id,
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $transaction = Transaction::create([
            'uuid' => \Illuminate\Support\Str::orderedUuid()->toString(),
            'workspace_id' => $workspace->id,
            'credit_card_id' => $card->id,
            'credit_card_bill_id' => $bill->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'description' => 'Paid Bill Del',
            'value' => 100,
            'date' => '2026-07-10',
            'paid_at' => null,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->delete(route('card-expenses.destroy', [$workspace, $card, $transaction]), [
                'scope' => 'single',
            ]);

        $response->assertSessionHasErrors(['bill']);
    }

    public function test_delete_restores_available_limit(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user, ['credit_limit' => 5000, 'available_limit' => 5000]);
        $category = $this->createExpenseCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('card-expenses.store', [$workspace, $card]), [
                'description' => 'Del Limit',
                'value' => 1000,
                'date' => '2026-07-10',
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
            ]);

        $this->assertEquals(4000, (float) $card->fresh()->available_limit);

        $transaction = Transaction::where('description', 'Del Limit')->first();
        $this->actingAs($user)
            ->delete(route('card-expenses.destroy', [$workspace, $card, $transaction]), [
                'scope' => 'single',
            ]);

        $this->assertEquals(5000, (float) $card->fresh()->available_limit);
    }
}
