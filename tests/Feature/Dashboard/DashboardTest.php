<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Enums\WorkspaceRole;
use App\Models\Account;
use App\Models\Category;
use App\Models\ExpenseGroup;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_dashboard_page_returns_ok_with_home_component(): void
    {
        [$user, $workspace] = $this->setupWorkspace();

        $response = $this->actingAs($user)->get(route('dashboard', $workspace));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Home'));
    }

    public function test_kpis_are_computed_from_workspace_data(): void
    {
        [$user, $workspace, $account, $category] = $this->setupWorkspace();

        Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'current_balance' => 2500,
        ]);

        Transaction::factory()->income()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'value' => 1000,
            'date' => '2026-09-05',
        ]);

        $this->createExpense($workspace, $account, $category, $user, 300, '2026-09-10', true);
        $this->createExpense($workspace, $account, $category, $user, 200, '2026-09-12', false);

        $response = $this->actingAs($user)->get(route('dashboard', $workspace).'?month=2026-09');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('month', '2026-09')
            ->where('kpis.balance', fn ($value) => (float) $value === 5000.0)
            ->where('kpis.income', fn ($value) => (float) $value === 1000.0)
            ->where('kpis.expenses', fn ($value) => (float) $value === 500.0)
            ->where('kpis.pending_count', 1)
            ->where('kpis.pending_value', fn ($value) => (float) $value === 200.0));
    }

    public function test_expense_groups_are_aggregated_by_effective_group(): void
    {
        [$user, $workspace, $account, $category] = $this->setupWorkspace();

        $groupA = ExpenseGroup::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Essencial',
            'color' => '#FF0000',
        ]);
        $groupB = ExpenseGroup::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Lazer',
            'color' => '#00FF00',
        ]);

        $categoryA = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
            'expense_group_id' => $groupA->id,
        ]);
        $noGroupCategory = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
            'expense_group_id' => null,
        ]);

        // Inherited from category A (group A): 100
        $this->createExpense($workspace, $account, $categoryA, $user, 100, '2026-09-10');
        // Overridden to group B: 50
        $this->createExpense($workspace, $account, $categoryA, $user, 50, '2026-09-11', true, $groupB->id);
        // No group at all: 25
        $this->createExpense($workspace, $account, $noGroupCategory, $user, 25, '2026-09-12');

        $response = $this->actingAs($user)->get(route('dashboard', $workspace).'?month=2026-09');

        $response->assertInertia(fn (Assert $page) => $page
            ->has('expenseGroups', 3)
            ->where('expenseGroups.0.uuid', $groupA->uuid)
            ->where('expenseGroups.0.name', 'Essencial')
            ->where('expenseGroups.0.color', '#FF0000')
            ->where('expenseGroups.0.total', fn ($value) => (float) $value === 100.0)
            ->where('expenseGroups.0.percent', fn ($value) => (float) $value === 57.1)
            ->where('expenseGroups.1.uuid', $groupB->uuid)
            ->where('expenseGroups.1.total', fn ($value) => (float) $value === 50.0)
            ->where('expenseGroups.2.uuid', null)
            ->where('expenseGroups.2.name', 'Sem Grupo')
            ->where('expenseGroups.2.color', '#9CA3AF')
            ->where('expenseGroups.2.total', fn ($value) => (float) $value === 25.0));
    }

    public function test_expenses_from_other_months_are_excluded(): void
    {
        [$user, $workspace, $account, $category] = $this->setupWorkspace();

        $this->createExpense($workspace, $account, $category, $user, 100, '2026-08-31');
        $this->createExpense($workspace, $account, $category, $user, 50, '2026-09-01');

        $response = $this->actingAs($user)->get(route('dashboard', $workspace).'?month=2026-09');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('kpis.expenses', fn ($value) => (float) $value === 50.0)
            ->where('expenseGroups.0.total', fn ($value) => (float) $value === 50.0));
    }

    public function test_month_defaults_to_current_month_without_param(): void
    {
        [$user, $workspace, $account, $category] = $this->setupWorkspace();

        $this->createExpense($workspace, $account, $category, $user, 100, now()->format('Y-m-d'));
        $this->createExpense($workspace, $account, $category, $user, 50, now()->subMonth()->format('Y-m-d'));

        $response = $this->actingAs($user)->get(route('dashboard', $workspace));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('month', now()->format('Y-m'))
            ->where('kpis.expenses', fn ($value) => (float) $value === 100.0));
    }

    public function test_invalid_month_param_falls_back_to_current_month(): void
    {
        [$user, $workspace, $account, $category] = $this->setupWorkspace();

        $this->createExpense($workspace, $account, $category, $user, 100, now()->format('Y-m-d'));

        $response = $this->actingAs($user)->get(route('dashboard', $workspace).'?month=invalid');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('month', now()->format('Y-m'))
            ->where('kpis.expenses', fn ($value) => (float) $value === 100.0));
    }

    public function test_category_breakdown_is_ranked_by_total(): void
    {
        [$user, $workspace, $account, $categoryA] = $this->setupWorkspace();

        $group = ExpenseGroup::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Essencial',
            'color' => '#FF0000',
        ]);
        $categoryB = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
            'name' => 'Transporte',
            'color' => '#0000FF',
            'expense_group_id' => $group->id,
        ]);

        $this->createExpense($workspace, $account, $categoryB, $user, 80, '2026-09-10');
        $this->createExpense($workspace, $account, $categoryA, $user, 120, '2026-09-11');

        $response = $this->actingAs($user)->get(route('dashboard', $workspace).'?month=2026-09');

        $response->assertInertia(fn (Assert $page) => $page
            ->has('categories', 2)
            ->where('categories.0.name', $categoryA->name)
            ->where('categories.0.group_name', null)
            ->where('categories.0.total', fn ($value) => (float) $value === 120.0)
            ->where('categories.1.name', 'Transporte')
            ->where('categories.1.group_name', 'Essencial')
            ->where('categories.1.group_color', '#FF0000')
            ->where('categories.1.total', fn ($value) => (float) $value === 80.0));
    }

    public function test_has_groups_is_false_when_workspace_has_no_groups(): void
    {
        [$user, $workspace] = $this->setupWorkspace();

        $response = $this->actingAs($user)->get(route('dashboard', $workspace));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('hasGroups', false));
    }

    public function test_has_groups_is_true_when_workspace_has_groups(): void
    {
        [$user, $workspace] = $this->setupWorkspace();

        ExpenseGroup::factory()->create(['workspace_id' => $workspace->id]);

        $response = $this->actingAs($user)->get(route('dashboard', $workspace));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('hasGroups', true));
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
            'current_balance' => 2500,
        ]);
        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
        ]);

        return [$user, $workspace, $account, $category];
    }

    private function createExpense(
        Workspace $workspace,
        Account $account,
        Category $category,
        User $user,
        float $value,
        string $date,
        bool $paid = false,
        ?int $expenseGroupId = null,
    ): Transaction {
        $factory = $paid
            ? Transaction::factory()->paid()
            : Transaction::factory()->unpaid();

        return $factory->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'expense_group_id' => $expenseGroupId,
            'created_by' => $user->id,
            'value' => $value,
            'date' => $date,
        ]);
    }
}
