<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PlanningService
{
    /**
     * Project expenses and incomes across a date range, grouped by month.
     *
     * @return array{month: string, expenses: float, incomes: float, balance: float}[]
     */
    public function getProjection(Workspace $workspace, Carbon $dateStart, Carbon $dateEnd): array
    {
        $transactions = Transaction::where('workspace_id', $workspace->id)
            ->whereIn('type', [TransactionType::Expense, TransactionType::Income])
            ->whereNull('deleted_at')
            ->whereBetween('date', [$dateStart->toDateString(), $dateEnd->toDateString()])
            ->get();

        return $this->summarizeByMonth($transactions, $dateStart, $dateEnd);
    }

    /**
     * Return detailed transactions for a specific month, grouped by type.
     *
     * @return array{expenses: array, incomes: array}
     */
    public function getMonthDetail(Workspace $workspace, Carbon $month): array
    {
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        $transactions = Transaction::where('workspace_id', $workspace->id)
            ->whereIn('type', [TransactionType::Expense, TransactionType::Income])
            ->whereNull('deleted_at')
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get();

        $expenses = $transactions
            ->where('type', TransactionType::Expense)
            ->sortBy('date')
            ->values()
            ->map(fn (Transaction $t) => $this->formatTransactionDetail($t))
            ->toArray();

        $incomes = $transactions
            ->where('type', TransactionType::Income)
            ->sortBy('date')
            ->values()
            ->map(fn (Transaction $t) => $this->formatTransactionDetail($t))
            ->toArray();

        return [
            'expenses' => $expenses,
            'incomes' => $incomes,
        ];
    }

    // ── Private helpers ──────────────────────────────────────────────

    /**
     * Generate all months in the date range and sum transactions by type per month.
     *
     * @return array{month: string, expenses: float, incomes: float, balance: float}[]
     */
    private function summarizeByMonth(Collection $transactions, Carbon $dateStart, Carbon $dateEnd): array
    {
        // Group transactions by month
        $grouped = $transactions->groupBy(fn ($t) => $t->date instanceof Carbon
            ? $t->date->format('Y-m')
            : Carbon::parse($t->date)->format('Y-m')
        );

        // Generate every month in the range
        $result = [];
        $current = $dateStart->copy()->startOfMonth();
        $end = $dateEnd->copy()->startOfMonth();

        while ($current->lte($end)) {
            $monthKey = $current->format('Y-m');
            $monthTransactions = $grouped->get($monthKey, collect());

            $expenses = $monthTransactions
                ->where('type', TransactionType::Expense)
                ->sum(fn ($t) => (float) $t->value);

            $incomes = $monthTransactions
                ->where('type', TransactionType::Income)
                ->sum(fn ($t) => (float) $t->value);

            $result[] = [
                'month' => $monthKey,
                'expenses' => round($expenses, 2),
                'incomes' => round($incomes, 2),
                'balance' => round($incomes - $expenses, 2),
            ];

            $current->addMonthNoOverflow();
        }

        return $result;
    }

    private function formatTransactionDetail(Transaction $t): array
    {
        return [
            'description' => $t->description,
            'value' => (float) $t->value,
            'category' => $t->category?->name,
            'date' => $t->date instanceof Carbon ? $t->date->format('Y-m-d') : $t->date,
            'type' => $t->type->value,
        ];
    }
}
