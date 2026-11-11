<?php

declare(strict_types=1);

namespace Tests\Feature\Incomes;

use App\Enums\WorkspaceRole;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IncomeTotalsTest extends TestCase
{
    public function test_index_returns_totals_structure(): void
    {
        [$user, $workspace] = $this->setupWorkspace();

        $response = $this->actingAs($user)->get(route('incomes.index', $workspace));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('totals')
            ->has('totals.total')
            ->has('totals.paid')
            ->has('totals.pending'));
    }

    public function test_totals_sum_all_incomes_in_month(): void
    {
        [$user, $workspace, $account, $category] = $this->setupWorkspace();

        $this->createIncome($workspace, $account, $category, $user, 100, '2026-09-10');
        $this->createIncome($workspace, $account, $category, $user, 50, '2026-09-11');
        $this->createIncome($workspace, $account, $category, $user, 25, '2026-09-12');

        $response = $this->actingAs($user)
            ->get(route('incomes.index', $workspace).'?month=2026-09');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('totals.total', fn ($value) => (float) $value === 175.0));
    }

    public function test_totals_split_paid_and_pending(): void
    {
        [$user, $workspace, $account, $category] = $this->setupWorkspace();

        $this->createIncome($workspace, $account, $category, $user, 100, '2026-09-10', true);
        $this->createIncome($workspace, $account, $category, $user, 50, '2026-09-11', false);
        $this->createIncome($workspace, $account, $category, $user, 25, '2026-09-12', false);

        $response = $this->actingAs($user)
            ->get(route('incomes.index', $workspace).'?month=2026-09');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('totals.total', fn ($value) => (float) $value === 175.0)
            ->where('totals.paid', fn ($value) => (float) $value === 100.0)
            ->where('totals.pending', fn ($value) => (float) $value === 75.0));
    }

    public function test_totals_respect_month_param(): void
    {
        [$user, $workspace, $account, $category] = $this->setupWorkspace();

        $this->createIncome($workspace, $account, $category, $user, 100, '2026-08-10');
        $this->createIncome($workspace, $account, $category, $user, 50, '2026-09-10');

        $response = $this->actingAs($user)
            ->get(route('incomes.index', $workspace).'?month=2026-09');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('totals.total', fn ($value) => (float) $value === 50.0));
    }

    public function test_totals_without_month_param_defaults_to_current_month(): void
    {
        [$user, $workspace, $account, $category] = $this->setupWorkspace();

        $this->createIncome($workspace, $account, $category, $user, 100, now()->format('Y-m-d'));
        $this->createIncome($workspace, $account, $category, $user, 50, now()->subMonth()->format('Y-m-d'));

        $response = $this->actingAs($user)->get(route('incomes.index', $workspace));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('totals.total', fn ($value) => (float) $value === 100.0));
    }

    public function test_totals_with_invalid_month_param_defaults_to_current_month(): void
    {
        [$user, $workspace, $account, $category] = $this->setupWorkspace();

        $this->createIncome($workspace, $account, $category, $user, 100, now()->format('Y-m-d'));
        $this->createIncome($workspace, $account, $category, $user, 50, now()->subMonth()->format('Y-m-d'));

        $response = $this->actingAs($user)
            ->get(route('incomes.index', $workspace).'?month=invalid');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('totals.total', fn ($value) => (float) $value === 100.0));
    }

    public function test_totals_exclude_expense(): void
    {
        [$user, $workspace, $account, $category] = $this->setupWorkspace();

        $this->createIncome($workspace, $account, $category, $user, 100, '2026-09-10');

        Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'type' => 'expense',
            'value' => 1000,
            'date' => '2026-09-10',
        ]);

        $response = $this->actingAs($user)
            ->get(route('incomes.index', $workspace).'?month=2026-09');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('totals.total', fn ($value) => (float) $value === 100.0));
    }

    public function test_totals_respect_workspace_scope(): void
    {
        [$user, $workspace, $account, $category] = $this->setupWorkspace();

        $this->createIncome($workspace, $account, $category, $user, 100, '2026-09-10');

        $otherWorkspace = Workspace::factory()->create();
        $otherWorkspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);
        $otherAccount = Account::factory()->create([
            'workspace_id' => $otherWorkspace->id,
            'created_by' => $user->id,
        ]);
        $otherCategory = Category::factory()->create([
            'workspace_id' => $otherWorkspace->id,
            'created_by' => $user->id,
            'type' => 'income',
        ]);

        $this->createIncome($otherWorkspace, $otherAccount, $otherCategory, $user, 500, '2026-09-10');

        $response = $this->actingAs($user)
            ->get(route('incomes.index', $workspace).'?month=2026-09');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('totals.total', fn ($value) => (float) $value === 100.0));
    }

    public function test_totals_exclude_soft_deleted(): void
    {
        [$user, $workspace, $account, $category] = $this->setupWorkspace();

        $this->createIncome($workspace, $account, $category, $user, 100, '2026-09-10');
        $softDeleted = $this->createIncome($workspace, $account, $category, $user, 50, '2026-09-11');

        $softDeleted->delete();

        $response = $this->actingAs($user)
            ->get(route('incomes.index', $workspace).'?month=2026-09');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('totals.total', fn ($value) => (float) $value === 100.0));
    }

    /**
     * @return array{0: User, 1: Workspace, 2: Account, 3: Category}
     */
    private function setupWorkspace(): array
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
            'type' => 'income',
        ]);

        return [$user, $workspace, $account, $category];
    }

    private function createIncome(
        Workspace $workspace,
        Account $account,
        Category $category,
        User $user,
        float $value,
        string $date,
        bool $paid = false,
    ): Transaction {
        $factory = $paid
            ? Transaction::factory()->income()->paid()
            : Transaction::factory()->income()->unpaid();

        return $factory->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'value' => $value,
            'date' => $date,
        ]);
    }
}
