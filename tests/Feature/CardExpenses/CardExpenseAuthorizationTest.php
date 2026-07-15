<?php

declare(strict_types=1);

namespace Tests\Feature\CardExpenses;

use App\Enums\BillStatus;
use App\Models\CreditCardBill;
use App\Models\Transaction;
use Tests\Feature\CardExpenses\CardExpenseTestCase;

class CardExpenseAuthorizationTest extends CardExpenseTestCase
{
    public function test_viewer_cannot_create_card_expense(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember('viewer');
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $response = $this->actingAs($user)
            ->post(route('card-expenses.store', [$workspace, $card]), [
                'description' => 'Viewer Create',
                'value' => 100,
                'date' => '2026-07-10',
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
            ]);

        $response->assertForbidden();
    }

    public function test_viewer_cannot_edit_card_expense(): void
    {
        [$admin, $workspace] = $this->createWorkspaceWithMember('admin');
        $card = $this->createCard($workspace, $admin);
        $category = $this->createExpenseCategory($workspace, $admin);

        $this->actingAs($admin)
            ->post(route('card-expenses.store', [$workspace, $card]), [
                'description' => 'Auth Edit',
                'value' => 100,
                'date' => '2026-07-10',
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
            ]);

        $transaction = Transaction::where('description', 'Auth Edit')->first();

        [$viewer] = $this->createWorkspaceWithMember('viewer');
        $workspace->members()->attach($viewer, ['role' => 'viewer']);

        $response = $this->actingAs($viewer)
            ->put(route('card-expenses.update', [$workspace, $card, $transaction]), [
                'description' => 'Viewer Edit',
                'scope' => 'single',
            ]);

        $response->assertForbidden();
    }

    public function test_editor_cannot_delete_card_expense(): void
    {
        [$admin, $workspace] = $this->createWorkspaceWithMember('admin');
        $card = $this->createCard($workspace, $admin);
        $category = $this->createExpenseCategory($workspace, $admin);

        $this->actingAs($admin)
            ->post(route('card-expenses.store', [$workspace, $card]), [
                'description' => 'Auth Delete',
                'value' => 100,
                'date' => '2026-07-10',
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
            ]);

        $transaction = Transaction::where('description', 'Auth Delete')->first();

        [$editor] = $this->createWorkspaceWithMember('editor');
        $workspace->members()->attach($editor, ['role' => 'editor']);

        $response = $this->actingAs($editor)
            ->delete(route('card-expenses.destroy', [$workspace, $card, $transaction]), [
                'scope' => 'single',
            ]);

        $response->assertForbidden();
    }

    public function test_cannot_access_card_expense_from_different_card(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card1 = $this->createCard($workspace, $user, ['name' => 'Card A']);
        $card2 = $this->createCard($workspace, $user, ['name' => 'Card B']);
        $category = $this->createExpenseCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('card-expenses.store', [$workspace, $card1]), [
                'description' => 'Card A Expense',
                'value' => 100,
                'date' => '2026-07-10',
                'credit_card_id' => $card1->uuid,
                'category_id' => $category->uuid,
            ]);

        $transaction = Transaction::where('description', 'Card A Expense')->first();

        $response = $this->actingAs($user)
            ->put(route('card-expenses.update', [$workspace, $card2, $transaction]), [
                'description' => 'Cross Card',
                'scope' => 'single',
            ]);

        $response->assertNotFound();
    }

    public function test_cannot_access_bill_from_other_workspace(): void
    {
        [$user1, $workspace1] = $this->createWorkspaceWithMember();
        [$user2, $workspace2] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace2, $user2);

        $bill = CreditCardBill::factory()->open()->create([
            'credit_card_id' => $card->id,
            'workspace_id' => $workspace2->id,
            'created_by' => $user2->id,
        ]);

        $response = $this->actingAs($user1)
            ->get(route('bills.show', [$workspace1, $bill]));

        $response->assertNotFound();
    }
}
