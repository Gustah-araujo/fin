<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    private const NO_GROUP_COLOR = '#9CA3AF';

    private const NO_GROUP_NAME = 'Sem Grupo';

    public function __construct(
        private readonly TransactionService $transactionService,
    ) {}

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

    private function aggregateByEffectiveGroup(
        Workspace $workspace,
        string $year,
        string $monthNumber,
        float $monthExpenseTotal,
    ): array {
        $grouped = $this->monthlyExpenseQuery($workspace, $year, $monthNumber)
            ->leftJoin('categories as c', 't.category_id', '=', 'c.id')
            ->selectRaw('COALESCE(t.expense_group_id, c.expense_group_id) as group_pk_id')
            ->addSelect(DB::raw('SUM(t.value) as total'))
            ->groupBy('group_pk_id')
            ->pluck('total', 'group_pk_id');

        $groups = $workspace->expenseGroups()->get(['id', 'uuid', 'name', 'color'])
            ->mapWithKeys(fn ($group) => [$group->id => [
                'uuid' => $group->uuid,
                'name' => $group->name,
                'color' => $group->color,
            ]]);

        $slices = collect($grouped)
            ->map(fn ($total, $groupPkId) => $this->makeSlice(
                $groupPkId === null ? null : $groups[(int) $groupPkId] ?? null,
                (float) $total,
                $monthExpenseTotal,
            ))
            ->sortByDesc('total')
            ->values()
            ->all();

        return array_values($slices);
    }

    private function aggregateByCategory(
        Workspace $workspace,
        string $year,
        string $monthNumber,
        float $monthExpenseTotal,
    ): array {
        $grouped = $this->monthlyExpenseQuery($workspace, $year, $monthNumber)
            ->selectRaw('t.category_id as category_pk_id')
            ->addSelect(DB::raw('SUM(t.value) as total'))
            ->groupBy('category_pk_id')
            ->pluck('total', 'category_pk_id');

        $categories = $workspace->categories()
            ->with('expenseGroup:id,uuid,name,color')
            ->get(['id', 'uuid', 'name', 'color', 'expense_group_id'])
            ->mapWithKeys(fn ($category) => [$category->id => $category]);

        $slices = collect($grouped)
            ->map(fn ($total, $categoryPkId) => $this->makeCategorySlice(
                $categories[(int) $categoryPkId] ?? null,
                (float) $total,
                $monthExpenseTotal,
            ))
            ->sortByDesc('total')
            ->values()
            ->all();

        return array_values($slices);
    }

    private function monthlyExpenseQuery(Workspace $workspace, string $year, string $monthNumber)
    {
        return DB::table('transactions as t')
            ->where('t.workspace_id', $workspace->id)
            ->where('t.type', 'expense')
            ->whereNull('t.deleted_at')
            ->whereYear('t.date', (int) $year)
            ->whereMonth('t.date', (int) $monthNumber);
    }

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

    private function makeCategorySlice(?Category $category, float $total, float $monthExpenseTotal): array
    {
        $group = $category?->expenseGroup;

        return [
            'uuid' => $category?->uuid,
            'name' => $category?->name ?? self::NO_GROUP_NAME,
            'color' => $category?->color ?? self::NO_GROUP_COLOR,
            'group_color' => $group?->color,
            'group_name' => $group?->name,
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
        return explode('-', $month);
    }
}
