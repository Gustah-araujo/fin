<?php

declare(strict_types=1);

namespace Tests\Feature\Transactions;

use App\Enums\WorkspaceRole;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class TransactionFilteringTest extends TestCase
{
    public function test_can_search_transactions_by_description(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);
        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
        ]);

        Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'description' => 'Mercado Extra',
        ]);
        Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'description' => 'Mercado São João',
        ]);
        Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'description' => 'Padaria Pão Doce',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('transactions.datatable', $workspace).'?description=mercado');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_search_is_case_insensitive(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);
        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
        ]);

        Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'description' => 'Mercado',
        ]);
        Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'description' => 'Padaria',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('transactions.datatable', $workspace).'?description=MERCADO');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'Mercado');
    }

    public function test_can_filter_transactions_by_category(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);
        $categoryA = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
            'name' => 'Alimentação',
        ]);
        $categoryB = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
            'name' => 'Transporte',
        ]);

        Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $categoryA->id,
            'created_by' => $user->id,
        ]);
        Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $categoryA->id,
            'created_by' => $user->id,
        ]);
        Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $categoryB->id,
            'created_by' => $user->id,
        ]);
        Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $categoryB->id,
            'created_by' => $user->id,
        ]);

        // Filters by UUID, not integer id (bug fix)
        $response = $this->actingAs($user)
            ->getJson(route('transactions.datatable', $workspace).'?category='.$categoryA->uuid);

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_can_filter_transactions_by_account(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $accountA = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'name' => 'Nubank',
        ]);
        $accountB = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'name' => 'Itaú',
        ]);
        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
        ]);

        Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $accountA->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
        ]);
        Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $accountA->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
        ]);
        Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $accountB->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
        ]);
        Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $accountB->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
        ]);

        // Filters by UUID, not integer id (bug fix)
        $response = $this->actingAs($user)
            ->getJson(route('transactions.datatable', $workspace).'?account='.$accountA->uuid);

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_can_filter_transactions_by_date_range(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);
        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
        ]);

        $dateQuery = [
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
        ];

        Transaction::factory()->create([...$dateQuery, 'date' => '2026-01-01']);
        Transaction::factory()->create([...$dateQuery, 'date' => '2026-06-15']);
        Transaction::factory()->create([...$dateQuery, 'date' => '2026-12-31']);

        $response = $this->actingAs($user)
            ->getJson(route('transactions.datatable', $workspace).'?date_from=2026-03-01&date_to=2026-09-30');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.date', '2026-06-15');
    }

    public function test_can_filter_by_paid_status(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);
        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
        ]);

        Transaction::factory()->paid()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
        ]);
        Transaction::factory()->paid()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
        ]);
        Transaction::factory()->unpaid()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
        ]);

        $paidResponse = $this->actingAs($user)
            ->getJson(route('transactions.datatable', $workspace).'?status=paid');

        $paidResponse->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);

        $unpaidResponse = $this->actingAs($user)
            ->getJson(route('transactions.datatable', $workspace).'?status=unpaid');

        $unpaidResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_filters_combine_with_and_logic(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);
        $categoryA = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
            'name' => 'Categoria A',
        ]);
        $categoryB = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
            'name' => 'Categoria B',
        ]);

        Transaction::factory()->paid()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $categoryA->id,
            'created_by' => $user->id,
        ]);

        Transaction::factory()->paid()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $categoryB->id,
            'created_by' => $user->id,
        ]);
        Transaction::factory()->paid()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $categoryB->id,
            'created_by' => $user->id,
        ]);
        Transaction::factory()->unpaid()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $categoryB->id,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('transactions.datatable', $workspace).'?status=paid&category='.$categoryB->uuid);

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_transaction_list_paginates_at_25(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);
        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
        ]);

        Transaction::factory()->count(30)->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('transactions.datatable', $workspace));

        $response->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.per_page', 25)
            ->assertJsonPath('meta.total', 30)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.current_page', 1);
    }
}
