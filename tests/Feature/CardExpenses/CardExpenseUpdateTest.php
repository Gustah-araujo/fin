<?php

declare(strict_types=1);

namespace Tests\Feature\CardExpenses;

use App\Models\Transaction;
use Tests\Feature\CardExpenses\CardExpenseTestCase;

class CardExpenseUpdateTest extends CardExpenseTestCase
{
    public function test_edit_single_scope_updates_one_row(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('card-expenses.store', [$workspace, $card]), [
                'description' => 'Original',
                'total_value' => 300,
                'value' => 100,
                'date' => '2026-02-15',
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
                'installments' => 3,
            ]);

        $transactions = Transaction::where('description', 'Original')->orderBy('installment_number')->get();
        $installment2 = $transactions[1];

        $response = $this->actingAs($user)
            ->put(route('card-expenses.update', [$workspace, $card, $installment2]), [
                'description' => 'Changed 2',
                'scope' => 'single',
            ]);

        $response->assertRedirect();

        $this->assertEquals('Changed 2', $transactions[1]->fresh()->description);
        $this->assertEquals('Original', $transactions[0]->fresh()->description);
        $this->assertEquals('Original', $transactions[2]->fresh()->description);
    }

    public function test_edit_group_scope_updates_future_installments(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('card-expenses.store', [$workspace, $card]), [
                'description' => 'Group Original',
                'total_value' => 300,
                'value' => 100,
                'date' => '2026-02-15',
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
                'installments' => 3,
            ]);

        $transactions = Transaction::where('description', 'Group Original')->orderBy('installment_number')->get();
        $installment2 = $transactions[1];

        $response = $this->actingAs($user)
            ->put(route('card-expenses.update', [$workspace, $card, $installment2]), [
                'description' => 'Group Changed',
                'scope' => 'group',
            ]);

        $response->assertRedirect();

        $this->assertEquals('Group Original', $transactions[0]->fresh()->description);
        $this->assertEquals('Group Changed', $transactions[1]->fresh()->description);
        $this->assertEquals('Group Changed', $transactions[2]->fresh()->description);
    }

    public function test_edit_installment_re_buckets_on_date_change(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user, ['closing_day' => 1]);
        $category = $this->createExpenseCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('card-expenses.store', [$workspace, $card]), [
                'description' => 'Rebucket',
                'value' => 100,
                'date' => '2026-02-15',
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
            ]);

        $transaction = Transaction::where('description', 'Rebucket')->first();
        $oldBillId = $transaction->credit_card_bill_id;

        $this->actingAs($user)
            ->put(route('card-expenses.update', [$workspace, $card, $transaction]), [
                'date' => '2026-04-20',
                'scope' => 'single',
            ]);

        $transaction->refresh();
        $this->assertNotEquals($oldBillId, $transaction->credit_card_bill_id);
    }

    public function test_cannot_edit_installment_on_paid_bill(): void
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
            'description' => 'Paid Bill Expense',
            'value' => 100,
            'date' => '2026-07-10',
            'paid_at' => null,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->put(route('card-expenses.update', [$workspace, $card, $transaction]), [
                'description' => 'Try Edit',
                'scope' => 'single',
            ]);

        $response->assertSessionHasErrors(['bill']);
    }

    public function test_edit_updates_tags(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $tag1 = \App\Models\Tag::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);
        $tag2 = \App\Models\Tag::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('card-expenses.store', [$workspace, $card]), [
                'description' => 'Tag Edit',
                'value' => 100,
                'date' => '2026-07-10',
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
                'tags' => [$tag1->uuid],
            ]);

        $transaction = Transaction::where('description', 'Tag Edit')->first();
        $this->assertEquals(1, $transaction->tags()->count());

        $this->actingAs($user)
            ->put(route('card-expenses.update', [$workspace, $card, $transaction]), [
                'tags' => [$tag1->uuid, $tag2->uuid],
                'scope' => 'single',
            ]);

        $this->assertEquals(2, $transaction->fresh()->tags()->count());
    }
}
