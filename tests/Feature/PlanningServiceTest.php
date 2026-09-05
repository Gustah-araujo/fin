<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RecurrenceStatus;
use App\Enums\TransactionType;
use App\Enums\WorkspaceRole;
use App\Models\Account;
use App\Models\Category;
use App\Models\Recurrence;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PlanningService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanningServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlanningService $service;

    private User $user;

    private Workspace $workspace;

    private Account $account;

    private Category $expenseCategory;

    private Category $incomeCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(PlanningService::class);

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

    // ─── getProjection ───────────────────────────────────────────────

    public function test_get_projection_with_avulsa_only(): void
    {
        $start = Carbon::create(2026, 9, 1);
        $end = Carbon::create(2026, 9, 30);

        Transaction::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'type' => TransactionType::Expense,
            'description' => 'Aluguel',
            'value' => 1500.00,
            'date' => '2026-09-05',
            'created_by' => $this->user->id,
        ]);

        Transaction::factory()->income()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->incomeCategory->id,
            'description' => 'Salário',
            'value' => 5000.00,
            'date' => '2026-09-10',
            'created_by' => $this->user->id,
        ]);

        $result = $this->service->getProjection($this->workspace, $start, $end);

        $this->assertCount(1, $result);
        $this->assertEquals('2026-09', $result[0]['month']);
        $this->assertEqualsWithDelta(1500.00, $result[0]['expenses'], 0.01);
        $this->assertEqualsWithDelta(5000.00, $result[0]['incomes'], 0.01);
        $this->assertEqualsWithDelta(3500.00, $result[0]['balance'], 0.01);
    }

    public function test_get_projection_with_installments(): void
    {
        $start = Carbon::create(2026, 9, 1);
        $end = Carbon::create(2026, 10, 31);

        // Installment 1 of 3
        Transaction::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'type' => TransactionType::Expense,
            'description' => 'Notebook Dell',
            'value' => 1000.00,
            'date' => '2026-09-10',
            'installment_number' => 1,
            'installments_total' => 3,
            'created_by' => $this->user->id,
        ]);

        // Installment 2 of 3
        Transaction::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'type' => TransactionType::Expense,
            'description' => 'Notebook Dell',
            'value' => 1000.00,
            'date' => '2026-10-10',
            'installment_number' => 2,
            'installments_total' => 3,
            'created_by' => $this->user->id,
        ]);

        $result = $this->service->getProjection($this->workspace, $start, $end);

        $this->assertCount(2, $result);

        $sept = collect($result)->firstWhere('month', '2026-09');
        $this->assertNotNull($sept);
        $this->assertEqualsWithDelta(1000.00, $sept['expenses'], 0.01);

        $oct = collect($result)->firstWhere('month', '2026-10');
        $this->assertNotNull($oct);
        $this->assertEqualsWithDelta(1000.00, $oct['expenses'], 0.01);
    }

    public function test_get_projection_with_active_recurrences(): void
    {
        $start = Carbon::create(2026, 9, 1);
        $end = Carbon::create(2026, 12, 31);

        // Active monthly recurrence: next_date = 2026-09-15, frequency_day = 15
        Recurrence::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->incomeCategory->id,
            'type' => TransactionType::Income,
            'description' => 'Freelance',
            'value' => 2000.00,
            'frequency' => 'monthly',
            'frequency_day' => 15,
            'start_date' => '2026-09-15',
            'next_date' => '2026-09-15',
            'status' => RecurrenceStatus::Active,
            'created_by' => $this->user->id,
        ]);

        $result = $this->service->getProjection($this->workspace, $start, $end);

        // Should have 4 months: Sep, Oct, Nov, Dec
        $this->assertCount(4, $result);

        foreach ($result as $month) {
            $this->assertEqualsWithDelta(2000.00, $month['incomes'], 0.01);
        }
    }

    public function test_get_projection_excludes_paused_recurrences(): void
    {
        $start = Carbon::create(2026, 9, 1);
        $end = Carbon::create(2026, 12, 31);

        // Paused recurrence — should not appear
        Recurrence::factory()->paused()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->incomeCategory->id,
            'type' => TransactionType::Income,
            'description' => 'Freelance Pausado',
            'value' => 2000.00,
            'frequency' => 'monthly',
            'frequency_day' => 15,
            'start_date' => '2026-09-15',
            'next_date' => '2026-09-15',
            'status' => RecurrenceStatus::Paused,
            'created_by' => $this->user->id,
        ]);

        $result = $this->service->getProjection($this->workspace, $start, $end);

        // All months should have 0 incomes
        foreach ($result as $month) {
            $this->assertEqualsWithDelta(0.00, $month['incomes'], 0.01);
        }
    }

    public function test_get_projection_respects_until_date(): void
    {
        $start = Carbon::create(2026, 9, 1);
        $end = Carbon::create(2026, 12, 31);

        // Active recurrence that ends in October
        Recurrence::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->incomeCategory->id,
            'type' => TransactionType::Income,
            'description' => 'Consultoria',
            'value' => 3000.00,
            'frequency' => 'monthly',
            'frequency_day' => 10,
            'start_date' => '2026-09-10',
            'next_date' => '2026-09-10',
            'until_date' => '2026-10-31',
            'status' => RecurrenceStatus::Active,
            'created_by' => $this->user->id,
        ]);

        $result = $this->service->getProjection($this->workspace, $start, $end);

        $sept = collect($result)->firstWhere('month', '2026-09');
        $this->assertEqualsWithDelta(3000.00, $sept['incomes'], 0.01);

        $oct = collect($result)->firstWhere('month', '2026-10');
        $this->assertEqualsWithDelta(3000.00, $oct['incomes'], 0.01);

        $nov = collect($result)->firstWhere('month', '2026-11');
        $this->assertEqualsWithDelta(0.00, $nov['incomes'], 0.01);

        $dec = collect($result)->firstWhere('month', '2026-12');
        $this->assertEqualsWithDelta(0.00, $dec['incomes'], 0.01);
    }

    public function test_get_projection_empty_month_returns_zero(): void
    {
        $start = Carbon::create(2026, 9, 1);
        $end = Carbon::create(2026, 9, 30);

        // No transactions at all
        $result = $this->service->getProjection($this->workspace, $start, $end);

        $this->assertCount(1, $result);
        $this->assertEquals('2026-09', $result[0]['month']);
        $this->assertEqualsWithDelta(0.00, $result[0]['expenses'], 0.01);
        $this->assertEqualsWithDelta(0.00, $result[0]['incomes'], 0.01);
        $this->assertEqualsWithDelta(0.00, $result[0]['balance'], 0.01);
    }

    public function test_get_projection_period_longer_than_24_months(): void
    {
        $start = Carbon::create(2026, 9, 1);
        $end = Carbon::create(2029, 12, 31);

        // One avulsa
        Transaction::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'type' => TransactionType::Expense,
            'description' => 'Carro',
            'value' => 500.00,
            'date' => '2026-09-15',
            'created_by' => $this->user->id,
        ]);

        $result = $this->service->getProjection($this->workspace, $start, $end);

        // Should still compute all months
        $this->assertGreaterThanOrEqual(36, count($result));

        $sept = collect($result)->firstWhere('month', '2026-09');
        $this->assertEqualsWithDelta(500.00, $sept['expenses'], 0.01);
    }

    public function test_get_projection_mixed_avulsas_and_recurrences(): void
    {
        $start = Carbon::create(2026, 9, 1);
        $end = Carbon::create(2026, 11, 30);

        // Avulsa expense
        Transaction::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'type' => TransactionType::Expense,
            'description' => 'Supermercado',
            'value' => 800.00,
            'date' => '2026-09-20',
            'created_by' => $this->user->id,
        ]);

        // Recurrence income
        Recurrence::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->incomeCategory->id,
            'type' => TransactionType::Income,
            'description' => 'Salário',
            'value' => 6000.00,
            'frequency' => 'monthly',
            'frequency_day' => 5,
            'start_date' => '2026-09-05',
            'next_date' => '2026-09-05',
            'status' => RecurrenceStatus::Active,
            'created_by' => $this->user->id,
        ]);

        $result = $this->service->getProjection($this->workspace, $start, $end);

        $sept = collect($result)->firstWhere('month', '2026-09');
        $this->assertEqualsWithDelta(800.00, $sept['expenses'], 0.01);
        $this->assertEqualsWithDelta(6000.00, $sept['incomes'], 0.01);
        $this->assertEqualsWithDelta(5200.00, $sept['balance'], 0.01);

        $oct = collect($result)->firstWhere('month', '2026-10');
        $this->assertEqualsWithDelta(0.00, $oct['expenses'], 0.01);
        $this->assertEqualsWithDelta(6000.00, $oct['incomes'], 0.01);
    }

    public function test_get_projection_expense_recurrence_projects_as_expense(): void
    {
        $start = Carbon::create(2026, 9, 1);
        $end = Carbon::create(2026, 12, 31);

        // Active monthly expense recurrence: Netflix R$50/month
        Recurrence::factory()->expense()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'type' => TransactionType::Expense,
            'description' => 'Netflix',
            'value' => 50.00,
            'frequency' => 'monthly',
            'frequency_day' => 15,
            'start_date' => '2026-09-15',
            'next_date' => '2026-09-15',
            'status' => RecurrenceStatus::Active,
            'created_by' => $this->user->id,
        ]);

        $result = $this->service->getProjection($this->workspace, $start, $end);

        // 4 months: Sep–Dec, each with R$50 expense
        $this->assertCount(4, $result);

        foreach ($result as $month) {
            $this->assertEqualsWithDelta(50.00, $month['expenses'], 0.01);
            $this->assertEqualsWithDelta(0.00, $month['incomes'], 0.01);
            $this->assertEqualsWithDelta(-50.00, $month['balance'], 0.01);
        }
    }

    public function test_get_projection_mixed_income_and_expense_recurrences(): void
    {
        $start = Carbon::create(2026, 9, 1);
        $end = Carbon::create(2026, 11, 30);

        // Income recurrence: Salário R$6000
        Recurrence::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->incomeCategory->id,
            'type' => TransactionType::Income,
            'description' => 'Salário',
            'value' => 6000.00,
            'frequency' => 'monthly',
            'frequency_day' => 5,
            'start_date' => '2026-09-05',
            'next_date' => '2026-09-05',
            'status' => RecurrenceStatus::Active,
            'created_by' => $this->user->id,
        ]);

        // Expense recurrence: Netflix R$50
        Recurrence::factory()->expense()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'type' => TransactionType::Expense,
            'description' => 'Netflix',
            'value' => 50.00,
            'frequency' => 'monthly',
            'frequency_day' => 10,
            'start_date' => '2026-09-10',
            'next_date' => '2026-09-10',
            'status' => RecurrenceStatus::Active,
            'created_by' => $this->user->id,
        ]);

        // Expense recurrence: Academia R$200
        Recurrence::factory()->expense()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'type' => TransactionType::Expense,
            'description' => 'Academia',
            'value' => 200.00,
            'frequency' => 'monthly',
            'frequency_day' => 1,
            'start_date' => '2026-09-01',
            'next_date' => '2026-09-01',
            'status' => RecurrenceStatus::Active,
            'created_by' => $this->user->id,
        ]);

        $result = $this->service->getProjection($this->workspace, $start, $end);

        $this->assertCount(3, $result);

        foreach ($result as $month) {
            $this->assertEqualsWithDelta(6000.00, $month['incomes'], 0.01);
            $this->assertEqualsWithDelta(250.00, $month['expenses'], 0.01); // 50 + 200
            $this->assertEqualsWithDelta(5750.00, $month['balance'], 0.01); // 6000 - 250
        }
    }

    public function test_get_projection_only_returns_months_in_range(): void
    {
        $start = Carbon::create(2026, 9, 1);
        $end = Carbon::create(2026, 10, 31);

        // Recurrence that projects into Nov too
        Recurrence::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->incomeCategory->id,
            'type' => TransactionType::Income,
            'description' => 'Freelance',
            'value' => 1000.00,
            'frequency' => 'monthly',
            'frequency_day' => 15,
            'start_date' => '2026-09-15',
            'next_date' => '2026-09-15',
            'status' => RecurrenceStatus::Active,
            'created_by' => $this->user->id,
        ]);

        $result = $this->service->getProjection($this->workspace, $start, $end);

        $this->assertCount(2, $result);
        $this->assertEquals('2026-09', $result[0]['month']);
        $this->assertEquals('2026-10', $result[1]['month']);
    }

    // ─── getMonthDetail ──────────────────────────────────────────────

    public function test_get_month_detail_with_avulsas(): void
    {
        $month = Carbon::create(2026, 9, 15);

        Transaction::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'type' => TransactionType::Expense,
            'description' => 'Aluguel',
            'value' => 1500.00,
            'date' => '2026-09-05',
            'created_by' => $this->user->id,
        ]);

        Transaction::factory()->income()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->incomeCategory->id,
            'description' => 'Salário',
            'value' => 5000.00,
            'date' => '2026-09-10',
            'created_by' => $this->user->id,
        ]);

        $result = $this->service->getMonthDetail($this->workspace, $month);

        $this->assertCount(1, $result['expenses']);
        $this->assertCount(1, $result['incomes']);
        $this->assertEquals('Aluguel', $result['expenses'][0]['description']);
        $this->assertEquals('Salário', $result['incomes'][0]['description']);
    }

    public function test_get_month_detail_includes_projected_recurrences(): void
    {
        $month = Carbon::create(2026, 9, 15);

        Recurrence::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->incomeCategory->id,
            'type' => TransactionType::Income,
            'description' => 'Freelance',
            'value' => 2000.00,
            'frequency' => 'monthly',
            'frequency_day' => 15,
            'start_date' => '2026-09-15',
            'next_date' => '2026-09-15',
            'status' => RecurrenceStatus::Active,
            'created_by' => $this->user->id,
        ]);

        $result = $this->service->getMonthDetail($this->workspace, $month);

        $this->assertCount(1, $result['incomes']);
        $this->assertEquals('Freelance', $result['incomes'][0]['description']);
        $this->assertEqualsWithDelta(2000.00, $result['incomes'][0]['value'], 0.01);
    }

    public function test_get_month_detail_excludes_paused_recurrences(): void
    {
        $month = Carbon::create(2026, 9, 15);

        Recurrence::factory()->paused()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->incomeCategory->id,
            'type' => TransactionType::Income,
            'description' => 'Freelance Pausado',
            'value' => 2000.00,
            'frequency' => 'monthly',
            'frequency_day' => 15,
            'start_date' => '2026-09-15',
            'next_date' => '2026-09-15',
            'status' => RecurrenceStatus::Paused,
            'created_by' => $this->user->id,
        ]);

        $result = $this->service->getMonthDetail($this->workspace, $month);

        $this->assertCount(0, $result['incomes']);
    }

    public function test_get_month_detail_empty_month(): void
    {
        $month = Carbon::create(2026, 9, 15);

        $result = $this->service->getMonthDetail($this->workspace, $month);

        $this->assertCount(0, $result['expenses']);
        $this->assertCount(0, $result['incomes']);
    }

    public function test_get_month_detail_sorted_by_date(): void
    {
        $month = Carbon::create(2026, 9, 15);

        Transaction::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'type' => TransactionType::Expense,
            'description' => 'Supermercado',
            'value' => 200.00,
            'date' => '2026-09-20',
            'created_by' => $this->user->id,
        ]);

        Transaction::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'type' => TransactionType::Expense,
            'description' => 'Aluguel',
            'value' => 1500.00,
            'date' => '2026-09-05',
            'created_by' => $this->user->id,
        ]);

        $result = $this->service->getMonthDetail($this->workspace, $month);

        $this->assertCount(2, $result['expenses']);
        $this->assertEquals('Aluguel', $result['expenses'][0]['description']);
        $this->assertEquals('Supermercado', $result['expenses'][1]['description']);
    }

    public function test_get_month_detail_until_date_blocks_projection(): void
    {
        $month = Carbon::create(2026, 11, 15);

        // Recurrence until Oct 31 — should NOT appear in Nov
        Recurrence::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->incomeCategory->id,
            'type' => TransactionType::Income,
            'description' => 'Consultoria',
            'value' => 3000.00,
            'frequency' => 'monthly',
            'frequency_day' => 10,
            'start_date' => '2026-09-10',
            'next_date' => '2026-09-10',
            'until_date' => '2026-10-31',
            'status' => RecurrenceStatus::Active,
            'created_by' => $this->user->id,
        ]);

        $result = $this->service->getMonthDetail($this->workspace, $month);

        $this->assertCount(0, $result['incomes']);
    }

    public function test_get_projection_does_not_persist_projected_transactions(): void
    {
        $start = Carbon::create(2026, 9, 1);
        $end = Carbon::create(2026, 12, 31);

        Recurrence::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->incomeCategory->id,
            'type' => TransactionType::Income,
            'description' => 'Freelance',
            'value' => 2000.00,
            'frequency' => 'monthly',
            'frequency_day' => 15,
            'start_date' => '2026-09-15',
            'next_date' => '2026-09-15',
            'status' => RecurrenceStatus::Active,
            'created_by' => $this->user->id,
        ]);

        $countBefore = Transaction::where('workspace_id', $this->workspace->id)->count();

        $this->service->getProjection($this->workspace, $start, $end);

        $countAfter = Transaction::where('workspace_id', $this->workspace->id)->count();

        $this->assertEquals($countBefore, $countAfter);
    }
}
