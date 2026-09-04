<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RecurrenceFrequency;
use App\Enums\RecurrenceStatus;
use App\Enums\TransactionType;
use App\Models\Recurrence;
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
        // 1. Real transactions in range (avulsas + installments + materialized recurrences)
        $realTransactions = Transaction::where('workspace_id', $workspace->id)
            ->whereIn('type', [TransactionType::Expense, TransactionType::Income])
            ->whereNull('deleted_at')
            ->whereBetween('date', [$dateStart->toDateString(), $dateEnd->toDateString()])
            ->get();

        // 2. Active recurrences — project occurrences in memory
        $projectedTransactions = $this->projectActiveRecurrences($workspace, $dateStart, $dateEnd);

        // 3. Merge and group by month
        $allTransactions = $realTransactions->concat($projectedTransactions);

        // 4. Generate all months in range and fill with sums
        return $this->summarizeByMonth($allTransactions, $dateStart, $dateEnd);
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

        // 1. Real transactions for the month
        $realTransactions = Transaction::where('workspace_id', $workspace->id)
            ->whereIn('type', [TransactionType::Expense, TransactionType::Income])
            ->whereNull('deleted_at')
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get();

        // 2. Projected recurrence transactions for the month
        $projectedTransactions = $this->projectActiveRecurrences($workspace, $monthStart, $monthEnd);

        // 3. Merge and format
        $allTransactions = $realTransactions->concat($projectedTransactions);

        $expenses = $allTransactions
            ->where('type', TransactionType::Expense)
            ->sortBy('date')
            ->values()
            ->map(fn (Transaction $t) => $this->formatTransactionDetail($t))
            ->toArray();

        $incomes = $allTransactions
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
     * Generate projected transaction objects for active recurrences in the given range.
     * Returns Collection of pseudo-Transaction models (not persisted).
     */
    private function projectActiveRecurrences(Workspace $workspace, Carbon $dateStart, Carbon $dateEnd): Collection
    {
        $recurrences = Recurrence::where('workspace_id', $workspace->id)
            ->where('status', RecurrenceStatus::Active)
            ->whereNotNull('next_date')
            ->whereNull('deleted_at')
            ->get();

        $projected = collect();

        foreach ($recurrences as $recurrence) {
            $frequency = $recurrence->frequency instanceof RecurrenceFrequency
                ? $recurrence->frequency
                : RecurrenceFrequency::from($recurrence->frequency);

            $date = $recurrence->next_date->copy();

            while ($date->lte($dateEnd)) {
                // Skip if before range start
                if ($date->gte($dateStart)) {
                    // Respect until_date
                    if ($recurrence->until_date && $date->gt($recurrence->until_date)) {
                        break;
                    }

                    $projected->push(new Transaction([
                        'workspace_id' => $workspace->id,
                        'account_id' => $recurrence->account_id,
                        'category_id' => $recurrence->category_id,
                        'type' => $recurrence->type,
                        'description' => $recurrence->description,
                        'value' => $recurrence->value,
                        'date' => $date->toDateString(),
                        'paid_at' => null,
                        'recurrence_id' => $recurrence->id,
                    ]));
                }

                // Advance to next occurrence
                $date = match ($frequency) {
                    RecurrenceFrequency::Weekly => $date->copy()->addWeek(),
                    RecurrenceFrequency::Monthly => (function () use ($date, $recurrence) {
                        $next = $date->copy()->addMonthNoOverflow();
                        $next->day = min($recurrence->frequency_day, $next->daysInMonth);

                        return $next;
                    })(),
                };
            }
        }

        return $projected;
    }

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
