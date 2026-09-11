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

class TransactionMonthBoundTest extends TestCase
{
    public function test_datatable_filters_by_month_param(): void
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

        $base = [
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
        ];

        Transaction::factory()->create([...$base, 'date' => '2026-08-05']);
        Transaction::factory()->create([...$base, 'date' => '2026-08-20']);
        Transaction::factory()->create([...$base, 'date' => '2026-09-10']);

        $response = $this->actingAs($user)
            ->getJson(route('transactions.datatable', $workspace).'?month=2026-08');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.date', '2026-08-20');
    }

    public function test_datatable_returns_all_without_month_param(): void
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

        $base = [
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
        ];

        Transaction::factory()->create([...$base, 'date' => '2026-07-15']);
        Transaction::factory()->create([...$base, 'date' => '2026-08-15']);
        Transaction::factory()->create([...$base, 'date' => '2026-09-15']);

        $response = $this->actingAs($user)
            ->getJson(route('transactions.datatable', $workspace));

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3);
    }

    public function test_datatable_ignores_invalid_month_format(): void
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
            'date' => '2026-08-15',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('transactions.datatable', $workspace).'?month=invalid');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_month_filter_respects_workspace_scope(): void
    {
        $user = User::factory()->create();

        $workspaceA = Workspace::factory()->create();
        $workspaceA->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $workspaceB = Workspace::factory()->create();
        $workspaceB->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $accountA = Account::factory()->create([
            'workspace_id' => $workspaceA->id,
            'created_by' => $user->id,
        ]);
        $categoryA = Category::factory()->create([
            'workspace_id' => $workspaceA->id,
            'created_by' => $user->id,
            'type' => 'expense',
        ]);
        $accountB = Account::factory()->create([
            'workspace_id' => $workspaceB->id,
            'created_by' => $user->id,
        ]);
        $categoryB = Category::factory()->create([
            'workspace_id' => $workspaceB->id,
            'created_by' => $user->id,
            'type' => 'expense',
        ]);

        Transaction::factory()->create([
            'workspace_id' => $workspaceA->id,
            'account_id' => $accountA->id,
            'category_id' => $categoryA->id,
            'created_by' => $user->id,
            'date' => '2026-09-10',
        ]);
        Transaction::factory()->create([
            'workspace_id' => $workspaceB->id,
            'account_id' => $accountB->id,
            'category_id' => $categoryB->id,
            'created_by' => $user->id,
            'date' => '2026-09-10',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('transactions.datatable', $workspaceA).'?month=2026-09');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.date', '2026-09-10');
    }

    public function test_month_filter_with_year_boundary(): void
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

        $base = [
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
        ];

        Transaction::factory()->create([...$base, 'date' => '2025-12-28']);
        Transaction::factory()->create([...$base, 'date' => '2026-01-03']);

        $december = $this->actingAs($user)
            ->getJson(route('transactions.datatable', $workspace).'?month=2025-12');

        $december->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.date', '2025-12-28');

        $january = $this->actingAs($user)
            ->getJson(route('transactions.datatable', $workspace).'?month=2026-01');

        $january->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.date', '2026-01-03');
    }

    public function test_month_filter_composes_with_status_filter(): void
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

        $base = [
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
        ];

        Transaction::factory()->paid()->create([...$base, 'date' => '2026-09-10']);
        Transaction::factory()->unpaid()->create([...$base, 'date' => '2026-09-12']);
        Transaction::factory()->paid()->create([...$base, 'date' => '2026-08-10']);

        $response = $this->actingAs($user)
            ->getJson(route('transactions.datatable', $workspace).'?month=2026-09&status=paid');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.date', '2026-09-10');
    }
}
