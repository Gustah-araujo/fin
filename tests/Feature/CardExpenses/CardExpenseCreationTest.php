<?php

declare(strict_types=1);

namespace Tests\Feature\CardExpenses;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\Workspace;
use App\Models\Workspace as WorkspaceModel;
use Tests\Feature\CardExpenses\CardExpenseTestCase;

class CardExpenseCreationTest extends CardExpenseTestCase
{
    public function test_user_can_create_single_card_expense(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $response = $this->actingAs($user)
            ->post(route('card-expenses.store', [$workspace, $card]), [
                'description' => 'Compra Mercado',
                'value' => 150.75,
                'date' => '2026-07-10',
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'workspace_id' => $workspace->id,
            'credit_card_id' => $card->id,
            'account_id' => null,
            'description' => 'Compra Mercado',
            'value' => 150.75,
            'paid_at' => null,
        ]);
    }

    public function test_single_expense_associated_to_correct_bill(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user, ['closing_day' => 1]);
        $category = $this->createExpenseCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('card-expenses.store', [$workspace, $card]), [
                'description' => 'Compra Feb',
                'value' => 100,
                'date' => '2026-02-15',
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
            ]);

        $transaction = Transaction::where('description', 'Compra Feb')->first();
        $this->assertNotNull($transaction);
        $this->assertNotNull($transaction->credit_card_bill_id);

        $bill = $transaction->bill;
        $this->assertEquals(2026, $bill->period_year);
        $this->assertEquals(3, $bill->period_month);
    }

    public function test_validation_errors_on_create(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);

        $response = $this->actingAs($user)
            ->post(route('card-expenses.store', [$workspace, $card]), []);

        $response->assertSessionHasErrors(['description', 'value', 'date', 'credit_card_id', 'category_id']);
    }

    public function test_card_expense_with_zero_value_rejected(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $response = $this->actingAs($user)
            ->post(route('card-expenses.store', [$workspace, $card]), [
                'description' => 'Zero',
                'value' => 0,
                'date' => '2026-07-10',
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
            ]);

        $response->assertSessionHasErrors(['value']);
    }

    public function test_card_expense_on_archived_card_rejected(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);
        $card->delete();

        $response = $this->actingAs($user)
            ->post(route('card-expenses.store', [$workspace, $card]), [
                'description' => 'Archived',
                'value' => 100,
                'date' => '2026-07-10',
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
            ]);

        $response->assertSessionHasErrors(['credit_card_id']);
    }

    public function test_card_expense_with_income_category_rejected(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $incomeCategory = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'income',
        ]);

        $response = $this->actingAs($user)
            ->post(route('card-expenses.store', [$workspace, $card]), [
                'description' => 'Income Cat',
                'value' => 100,
                'date' => '2026-07-10',
                'credit_card_id' => $card->uuid,
                'category_id' => $incomeCategory->uuid,
            ]);

        $response->assertSessionHasErrors(['category_id']);
    }

    public function test_both_account_and_card_set_rejected(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);
        $account = $this->createAccount($workspace, $user);

        $response = $this->actingAs($user)
            ->post(route('card-expenses.store', [$workspace, $card]), [
                'description' => 'Both',
                'value' => 100,
                'date' => '2026-07-10',
                'credit_card_id' => $card->uuid,
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
            ]);

        $response->assertSessionHasErrors(['account_id']);
    }

    public function test_viewer_cannot_create_card_expense(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember('viewer');
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $response = $this->actingAs($user)
            ->post(route('card-expenses.store', [$workspace, $card]), [
                'description' => 'Viewer',
                'value' => 100,
                'date' => '2026-07-10',
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
            ]);

        $response->assertForbidden();
    }

    public function test_card_expense_tags_synced(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $tag1 = Tag::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);
        $tag2 = Tag::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('card-expenses.store', [$workspace, $card]), [
                'description' => 'With Tags',
                'value' => 200,
                'date' => '2026-07-10',
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
                'tags' => [$tag1->uuid, $tag2->uuid],
            ]);

        $transaction = Transaction::where('description', 'With Tags')->first();
        $this->assertNotNull($transaction);
        $this->assertEquals(2, $transaction->tags()->count());
    }

    public function test_card_from_other_workspace_404(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        [$user2, $workspace2] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace2, $user2);
        $category = $this->createExpenseCategory($workspace, $user);

        $response = $this->actingAs($user)
            ->post(route('card-expenses.store', [$workspace, $card]), [
                'description' => 'Cross',
                'value' => 100,
                'date' => '2026-07-10',
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
            ]);

        $response->assertSessionHasErrors(['credit_card_id']);
    }
}
