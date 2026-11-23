<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use App\Models\ExpenseGroup;
use App\Models\Workspace;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    private const NO_GROUP_COLOR = '#9CA3AF';

    private const NO_GROUP_NAME = 'Sem Grupo';

    public function __construct(
        private readonly TransactionService $transactionService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getMonthOverview(Workspace $workspace, ?string $month): array
    {
        $month = $this->resolveMonth($month);
        [$year, $monthNumber] = $this->splitMonth($month);

        $income = $this->transactionService->monthlyIncomeTotals($workspace, $month);
        $expenses = $this->transactionService->monthlyTotals($workspace, $month);

        return [
            'month' => $month,
            'kpis' => $this->buildKpis($workspace, $year, $monthNumber, $income, $expenses),
            'expense_groups' => $this->aggregateByEffectiveGroup($workspace, $year, $monthNumber, $expenses['total']),
            'categories' => $this->aggregateByCategory($workspace, $year, $monthNumber, $expenses['total']),
        ];
    }

    /**
     * @param  array{total: float, paid: float, pending: float}  $income
     * @param  array{total: float, paid: float, pending: float}  $expenses
     * @return array{balance: float, income: float, expenses: float, pending_count: int, pending_value: float}
     */
    private function buildKpis(
        Workspace $workspace,
        string $year,
        string $monthNumber,
        array $income,
        array $expenses,
    ): array {
        $pending = $this->pendingExpenses($workspace, $year, $monthNumber);

        return [
            'balance' => round((float) $workspace->accounts()->sum('current_balance'), 2),
            'income' => $income['total'],
            'expenses' => $expenses['total'],
            'pending_count' => $pending['count'],
            'pending_value' => $pending['value'],
        ];
    }

    /**
     * @return array{count: int, value: float}
     */
    private function pendingExpenses(Workspace $workspace, string $year, string $monthNumber): array
    {
        $query = $workspace->transactions()
            ->where('type', 'expense')
            ->whereNull('paid_at')
            ->whereYear('date', (int) $year)
            ->whereMonth('date', (int) $monthNumber);

        return [
            'count' => (int) $query->count(),
            'value' => round((float) $query->sum('value'), 2),
        ];
    }

    /**
     * @return list<array{uuid: string|null, name: string, color: string, total: float, percent: float}>
     */
    private function aggregateByEffectiveGroup(
        Workspace $workspace,
        string $year,
        string $monthNumber,
        float $monthExpenseTotal,
    ): array {
        $rows = $this->monthlyExpenseQuery($workspace, $year, $monthNumber)
            ->leftJoin('categories as c', 't.category_id', '=', 'c.id')
            ->selectRaw('COALESCE(t.expense_group_id, c.expense_group_id) as group_pk_id')
            ->addSelect(DB::raw('SUM(t.value) as total'))
            ->groupBy('group_pk_id')
            ->get();

        $groups = $workspace->expenseGroups()
            ->get(['id', 'uuid', 'name', 'color'])
            ->mapWithKeys(fn (ExpenseGroup $group) => [$group->id => [
                'uuid' => $group->uuid,
                'name' => $group->name,
                'color' => $group->color,
            ]]);

        $slices = $rows
            ->map(fn (\stdClass $row) => $this->makeSlice(
                is_int($row->group_pk_id) ? ($groups[$row->group_pk_id] ?? null) : null,
                self::floatValue($row->total),
                $monthExpenseTotal,
            ))
            ->sortByDesc('total')
            ->values()
            ->all();

        return array_values($slices);
    }

    /**
     * @return list<array{uuid: string|null, name: string, color: string, group_color: string|null, group_name: string|null, total: float, percent: float}>
     */
    private function aggregateByCategory(
        Workspace $workspace,
        string $year,
        string $monthNumber,
        float $monthExpenseTotal,
    ): array {
        $rows = $this->monthlyExpenseQuery($workspace, $year, $monthNumber)
            ->selectRaw('t.category_id as category_pk_id')
            ->addSelect(DB::raw('SUM(t.value) as total'))
            ->groupBy('category_pk_id')
            ->get();

        $categories = $workspace->categories()
            ->with('expenseGroup:id,uuid,name,color')
            ->get(['id', 'uuid', 'name', 'color', 'expense_group_id'])
            ->mapWithKeys(fn (Category $category) => [$category->id => $category]);

        $slices = $rows
            ->map(fn (\stdClass $row) => $this->makeCategorySlice(
                is_int($row->category_pk_id) ? ($categories[$row->category_pk_id] ?? null) : null,
                self::floatValue($row->total),
                $monthExpenseTotal,
            ))
            ->sortByDesc('total')
            ->values()
            ->all();

        return array_values($slices);
    }

    private function monthlyExpenseQuery(Workspace $workspace, string $year, string $monthNumber): QueryBuilder
    {
        return DB::table('transactions as t')
            ->where('t.workspace_id', $workspace->id)
            ->where('t.type', 'expense')
            ->whereNull('t.deleted_at')
            ->whereYear('t.date', (int) $year)
            ->whereMonth('t.date', (int) $monthNumber);
    }

    /**
     * @param  array{uuid: string, name: string, color: string}|null  $group
     * @return array{uuid: string|null, name: string, color: string, total: float, percent: float}
     */
    private function makeSlice(?array $group, float $total, float $monthExpenseTotal): array
    {
        return [
            'uuid' => $group['uuid'] ?? null,
            'name' => $group['name'] ?? self::NO_GROUP_NAME,
            'color' => $group['color'] ?? self::NO_GROUP_COLOR,
            'total' => round($total, 2),
            'percent' => $this->percent($total, $monthExpenseTotal),
        ];
    }

    /**
     * @return array{uuid: string|null, name: string, color: string, group_color: string|null, group_name: string|null, total: float, percent: float}
     */
    private function makeCategorySlice(?Category $category, float $total, float $monthExpenseTotal): array
    {
        $group = $category?->expenseGroup;

        return [
            'uuid' => $category->uuid ?? null,
            'name' => $category->name ?? self::NO_GROUP_NAME,
            'color' => $category->color ?? self::NO_GROUP_COLOR,
            'group_color' => $group instanceof ExpenseGroup ? $group->color : null,
            'group_name' => $group instanceof ExpenseGroup ? $group->name : null,
            'total' => round($total, 2),
            'percent' => $this->percent($total, $monthExpenseTotal),
        ];
    }

    private function percent(float $total, float $monthExpenseTotal): float
    {
        if ($monthExpenseTotal <= 0.0) {
            return 0.0;
        }

        return round($total / $monthExpenseTotal * 100, 1);
    }

    private function resolveMonth(?string $month): string
    {
        return is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month)
            ? $month
            : now()->format('Y-m');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitMonth(string $month): array
    {
        $parts = explode('-', $month);

        return [$parts[0], $parts[1] ?? ''];
    }

    private static function floatValue(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}
