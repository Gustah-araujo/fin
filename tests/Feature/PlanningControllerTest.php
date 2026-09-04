<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Enums\WorkspaceRole;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanningControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Workspace $workspace;

    private Account $account;

    private Category $expenseCategory;

    private Category $incomeCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->members()->attach($this->user, ['role' => WorkspaceRole::Admin->value]);

        $this->account = Account::factory()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        $this->expenseCategory = Category::factory()->expense()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        $this->incomeCategory = Category::factory()->income()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);
    }

    // ─── index ───────────────────────────────────────────────────────

    public function test_index_returns_200_with_projection_props(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('planning.index', $this->workspace));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Planning/Index')
            ->has('projection')
            ->has('date_start')
            ->has('date_end')
            ->has('totals')
            ->has('filter')
        );
    }

    public function test_index_totals_contain_expenses_incomes_balance(): void
    {
        Transaction::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'type' => TransactionType::Expense,
            'description' => 'Aluguel',
            'value' => 1500.00,
            'date' => Carbon::now()->startOfMonth()->addDays(5)->toDateString(),
            'created_by' => $this->user->id,
        ]);

        Transaction::factory()->income()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->incomeCategory->id,
            'description' => 'Salário',
            'value' => 5000.00,
            'date' => Carbon::now()->startOfMonth()->addDays(10)->toDateString(),
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('planning.index', $this->workspace));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('totals.expenses')
            ->has('totals.incomes')
            ->has('totals.balance')
        );
    }

    // ─── monthDetail ─────────────────────────────────────────────────

    public function test_month_detail_returns_json_with_expenses_and_incomes(): void
    {
        Transaction::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'type' => TransactionType::Expense,
            'description' => 'Supermercado',
            'value' => 200.00,
            'date' => Carbon::now()->startOfMonth()->addDays(3)->toDateString(),
            'created_by' => $this->user->id,
        ]);

        Transaction::factory()->income()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->incomeCategory->id,
            'description' => 'Freelance',
            'value' => 1000.00,
            'date' => Carbon::now()->startOfMonth()->addDays(7)->toDateString(),
            'created_by' => $this->user->id,
        ]);

        $month = Carbon::now()->format('Y-m');

        $response = $this->actingAs($this->user)
            ->get(route('planning.month-detail', $this->workspace), [
                'month' => $month,
            ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'expenses' => [
                ['description', 'value', 'category', 'date', 'type'],
            ],
            'incomes' => [
                ['description', 'value', 'category', 'date', 'type'],
            ],
        ]);
    }

    public function test_month_detail_returns_empty_arrays_for_month_without_transactions(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('planning.month-detail', $this->workspace), [
                'month' => '2030-01',
            ]);

        $response->assertOk();
        $response->assertJson([
            'expenses' => [],
            'incomes' => [],
        ]);
    }

    // ─── Authorization ───────────────────────────────────────────────

    public function test_non_member_gets_403_on_index(): void
    {
        $nonMember = User::factory()->create();

        $response = $this->actingAs($nonMember)
            ->get(route('planning.index', $this->workspace));

        $response->assertForbidden();
    }

    public function test_non_member_gets_403_on_month_detail(): void
    {
        $nonMember = User::factory()->create();

        $response = $this->actingAs($nonMember)
            ->get(route('planning.month-detail', $this->workspace), [
                'month' => Carbon::now()->format('Y-m'),
            ]);

        $response->assertForbidden();
    }

    // ─── Validation ──────────────────────────────────────────────────

    public function test_date_end_before_date_start_returns_422(): void
    {
        $start = Carbon::now()->addMonth()->format('Y-m-d');
        $end = Carbon::now()->subMonth()->format('Y-m-d');

        $response = $this->actingAs($this->user)
            ->get(route('planning.index', $this->workspace), [
                'date_start' => $start,
                'date_end' => $end,
            ]);

        $response->assertUnprocessable();
    }

    public function test_period_longer_than_24_months_returns_422(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('planning.index', $this->workspace), [
                'date_start' => '2026-01-01',
                'date_end' => '2029-01-01',
            ]);

        $response->assertUnprocessable();
    }

    // ─── Preset filtering ────────────────────────────────────────────

    public function test_index_accepts_type_filter_query_param(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('planning.index', $this->workspace), [
                'filter' => 'expenses',
            ]);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('filter', 'expenses')
        );
    }
}
