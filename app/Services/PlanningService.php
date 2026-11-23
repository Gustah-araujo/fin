<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Category;
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
     * @return array{expenses: array<int, array<string, mixed>>, incomes: array<int, array<string, mixed>>}
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
            ->values()
            ->all();

        $incomes = $transactions
            ->where('type', TransactionType::Income)
            ->sortBy('date')
            ->values()
            ->map(fn (Transaction $t) => $this->formatTransactionDetail($t))
            ->values()
            ->all();

        return [
            'expenses' => $expenses,
            'incomes' => $incomes,
        ];
    }

    // ── Private helpers ──────────────────────────────────────────────

    /**
     * Generate all months in the date range and sum transactions by type per month.
     *
     * @param  Collection<int, Transaction>  $transactions
     * @return array{month: string, expenses: float, incomes: float, balance: float}[]
     */
    private function summarizeByMonth(Collection $transactions, Carbon $dateStart, Carbon $dateEnd): array
    {
        // Group transactions by month
        $grouped = $transactions->groupBy(function (Transaction $t): string {
            $date = $t->getAttribute('date');

            if ($date instanceof Carbon) {
                return $date->format('Y-m');
            }

            return is_string($date) ? Carbon::parse($date)->format('Y-m') : '';
        });

        // Generate every month in the range
        $result = [];
        $current = $dateStart->copy()->startOfMonth();
        $end = $dateEnd->copy()->startOfMonth();

        while ($current->lte($end)) {
            $monthKey = $current->format('Y-m');
            $monthTransactions = $grouped->get($monthKey);

            $expenses = $monthTransactions?->where('type', TransactionType::Expense)
                ->sum(fn (Transaction $t): float => self::floatValue($t->getAttribute('value'))) ?? 0.0;

            $incomes = $monthTransactions?->where('type', TransactionType::Income)
                ->sum(fn (Transaction $t): float => self::floatValue($t->getAttribute('value'))) ?? 0.0;

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

    /**
     * @return array<string, mixed>
     */
    private function formatTransactionDetail(Transaction $t): array
    {
        $date = $t->getAttribute('date');
        $type = $t->getAttribute('type');
        $category = $t->category;

        return [
            'description' => $t->description,
            'value' => self::floatValue($t->getAttribute('value')),
            'category' => $category instanceof Category ? $category->name : null,
            'date' => $date instanceof Carbon ? $date->format('Y-m-d') : (is_string($date) ? $date : ''),
            'type' => $type instanceof TransactionType ? $type->value : (is_string($type) ? $type : ''),
        ];
    }

    private static function floatValue(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}
