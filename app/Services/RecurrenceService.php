<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RecurrenceFrequency;
use App\Enums\RecurrenceStatus;
use App\Enums\TransactionType;
use App\Jobs\ApplyRecurrenceScopeChangeJob;
use App\Models\Account;
use App\Models\Category;
use App\Models\Recurrence;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecurrenceService
{
    public function __construct(
        private readonly AccountService $accountService,
    ) {}

    /**
     * Create a recurrence rule without generating the first transaction.
     * Used when start_date > today.
     */
    public function create(Workspace $workspace, array $data, User $user): Recurrence
    {
        $accountId = $this->resolveAccountId($workspace, $data['account_id']);
        $categoryId = $this->resolveCategoryId($workspace, $data['category_id']);

        $startDate = Carbon::parse($data['start_date']);
        $today = Carbon::today();
        $frequency = RecurrenceFrequency::from($data['frequency']);
        $frequencyDay = (int) $data['frequency_day'];

        // Compute initial next_date
        if ($startDate->lte($today)) {
            $nextDate = $this->nextOccurrenceAfter($startDate, $frequency, $frequencyDay);
        } else {
            $nextDate = $startDate;
        }

        $recurrence = Recurrence::create([
            'uuid' => Str::orderedUuid()->toString(),
            'workspace_id' => $workspace->id,
            'account_id' => $accountId,
            'category_id' => $categoryId,
            'type' => $data['type'] ?? TransactionType::Income,
            'description' => $data['description'],
            'value' => $data['value'],
            'frequency' => $data['frequency'],
            'frequency_day' => $frequencyDay,
            'start_date' => $startDate,
            'until_date' => isset($data['until_date']) && $data['until_date'] ? Carbon::parse($data['until_date']) : null,
            'next_date' => $nextDate,
            'status' => RecurrenceStatus::Active,
            'created_by' => $user->id,
        ]);

        if (! empty($data['tags'])) {
            $this->syncTags($recurrence, $workspace, $data['tags']);
        }

        return $recurrence;
    }

    /**
     * Create a recurrence and immediately generate the first transaction.
     * Used when start_date <= today.
     *
     * @return array{recurrence: Recurrence, transaction: Transaction}
     */
    public function createWithFirstInstance(Workspace $workspace, array $data, User $user): array
    {
        return DB::transaction(function () use ($workspace, $data, $user) {
            $accountId = $this->resolveAccountId($workspace, $data['account_id']);
            $categoryId = $this->resolveCategoryId($workspace, $data['category_id']);

            $startDate = Carbon::parse($data['start_date']);
            $startDateStr = $startDate->toDateString();
            $frequency = RecurrenceFrequency::from($data['frequency']);
            $frequencyDay = (int) $data['frequency_day'];

            // Create recurrence with next_date = start_date (will be advanced after first instance)
            $recurrence = Recurrence::create([
                'uuid' => Str::orderedUuid()->toString(),
                'workspace_id' => $workspace->id,
                'account_id' => $accountId,
                'category_id' => $categoryId,
                'type' => $data['type'] ?? TransactionType::Income,
                'description' => $data['description'],
                'value' => $data['value'],
                'frequency' => $data['frequency'],
                'frequency_day' => $frequencyDay,
                'start_date' => $startDateStr,
                'until_date' => isset($data['until_date']) && $data['until_date'] ? Carbon::parse($data['until_date']) : null,
                'next_date' => $startDateStr,
                'status' => RecurrenceStatus::Active,
                'created_by' => $user->id,
            ]);

            // Create first transaction
            $transaction = Transaction::create([
                'uuid' => Str::orderedUuid()->toString(),
                'workspace_id' => $workspace->id,
                'account_id' => $accountId,
                'category_id' => $categoryId,
                'type' => $data['type'] ?? TransactionType::Income,
                'description' => $data['description'],
                'value' => $data['value'],
                'date' => $startDateStr,
                'paid_at' => null,
                'recurrence_id' => $recurrence->id,
                'created_by' => $user->id,
            ]);

            // Sync tags to both
            if (! empty($data['tags'])) {
                $this->syncTags($recurrence, $workspace, $data['tags']);
                $this->syncTags($transaction, $workspace, $data['tags']);
            }

            // Advance next_date to next occurrence
            $nextNextDate = $this->nextOccurrenceAfter($startDate, $frequency, $frequencyDay);

            if ($recurrence->until_date && $nextNextDate->gt($recurrence->until_date)) {
                $newNextDate = null;
            } else {
                $newNextDate = $nextNextDate->toDateString();
            }

            // Optimistic lock: use whereDate for reliable comparison across DB drivers
            $affected = Recurrence::where('id', $recurrence->id)
                ->whereDate('next_date', $startDateStr)
                ->update(['next_date' => $newNextDate ? Carbon::parse($newNextDate) : null]);

            if ($affected === 0) {
                throw ValidationException::withMessages([
                    'recurrence' => 'Recurso já existe. Recarregue e tente novamente.',
                ]);
            }

            return [
                'recurrence' => $recurrence->fresh(),
                'transaction' => $transaction,
            ];
        });
    }

    /**
     * Generate the next transaction instance for a recurrence.
     * Idempotent: uses optimistic lock to prevent duplicates.
     */
    public function generateNextInstance(Recurrence $recurrence): Transaction
    {
        $today = Carbon::today();

        if ($recurrence->status !== RecurrenceStatus::Active) {
            throw ValidationException::withMessages([
                'status' => 'A recorrência não está ativa.',
            ]);
        }

        if ($recurrence->trashed()) {
            throw ValidationException::withMessages([
                'status' => 'A recorrência foi removida.',
            ]);
        }

        if ($recurrence->next_date === null) {
            throw ValidationException::withMessages([
                'next_date' => 'A recorrência não possui próxima data.',
            ]);
        }

        $recurrence->load(['account' => fn ($q) => $q->withTrashed()]);

        if ($recurrence->account?->trashed()) {
            throw ValidationException::withMessages([
                'account' => 'A conta vinculada foi arquivada.',
            ]);
        }

        if ($recurrence->until_date && $recurrence->next_date->gt($recurrence->until_date)) {
            throw ValidationException::withMessages([
                'until_date' => 'A recorrência já atingiu sua data final.',
            ]);
        }

        if ($recurrence->next_date->gt($today)) {
            throw ValidationException::withMessages([
                'next_date' => "A próxima ocorrência é em {$recurrence->next_date->format('d/m/Y')}. Aguarde ou ajuste a recorrência.",
            ]);
        }

        $generationDate = $this->resolveGenerationDate($recurrence, $today);

        return $this->createGeneratedInstance($recurrence, $generationDate);
    }

    /**
     * Recompute the next_date from today respecting frequency and until_date.
     */
    public function recomputeNextDate(Recurrence $recurrence): ?Carbon
    {
        $today = Carbon::today();
        $frequency = $recurrence->frequency;
        $frequencyDay = $recurrence->frequency_day;

        if ($frequency instanceof RecurrenceFrequency) {
            // Already an enum instance
        } elseif (is_string($frequency)) {
            $frequency = RecurrenceFrequency::from($frequency);
        } else {
            return null;
        }

        $nextDate = $this->nextOccurrenceOnOrAfter($today, $frequency, $frequencyDay);

        if ($recurrence->until_date && $nextDate->gt($recurrence->until_date)) {
            return null;
        }

        return $nextDate;
    }

    /**
     * Update recurrence rule fields and recompute next_date when needed.
     */
    public function updateRule(Recurrence $recurrence, array $data): Recurrence
    {
        $data = $this->resolveForeignKeyIds($recurrence, $data);

        $this->applyStartDate($recurrence, $data);

        $this->applyEditableFields($recurrence, $data);

        $scheduleChanged = $this->applyScheduleFields($recurrence, $data);

        $this->exhaustIfUntilDatePassed($recurrence, $data);

        if ($scheduleChanged) {
            $this->recomputeNextDateAfterScheduleChange($recurrence);
        }

        $recurrence->save();

        if (! empty($data['tags'])) {
            $this->syncTags($recurrence, $recurrence->workspace, $data['tags']);
        }

        return $recurrence->fresh();
    }

    /**
     * Pause the recurrence — no new instances will be generated.
     */
    public function pause(Recurrence $recurrence): void
    {
        $recurrence->status = RecurrenceStatus::Paused;
        $recurrence->save();
    }

    /**
     * Restore a paused recurrence — generation resumes from today.
     */
    public function restore(Recurrence $recurrence): void
    {
        // Reject if linked account is archived (load with trashed to detect)
        $recurrence->load(['account' => fn ($q) => $q->withTrashed()]);
        $account = $recurrence->account;

        if ($account && $account->trashed()) {
            throw ValidationException::withMessages([
                'account_id' => 'A conta vinculada foi arquivada. Restaure a conta antes de reativar a recorrência.',
            ]);
        }

        // Reject if until_date < today
        if ($recurrence->until_date && $recurrence->until_date->lt(Carbon::today())) {
            throw ValidationException::withMessages([
                'until_date' => 'A data final já passou. Não é possível reativar esta recorrência.',
            ]);
        }

        $recurrence->status = RecurrenceStatus::Active;

        // Recompute next_date from today
        $nextDate = $this->recomputeNextDate($recurrence);
        $recurrence->next_date = $nextDate;

        $recurrence->save();
    }

    /**
     * Dispatch a background job to update the current transaction, the parent recurrence,
     * and all future instances. Returns immediately — the actual work runs asynchronously.
     */
    public function updateThisAndFuture(Transaction $transaction, array $data, User $user): void
    {
        dispatch(new ApplyRecurrenceScopeChangeJob(
            operation: 'update',
            transactionUuid: $transaction->uuid,
            payload: $data,
            userId: $user->id,
        ));
    }

    /**
     * Apply the "Esta e futuras" update synchronously inside a DB::transaction.
     * Called by ApplyRecurrenceScopeChangeJob.
     */
    public function applyUpdateThisAndFuture(Transaction $transaction, array $data, User $user): void
    {
        DB::transaction(function () use ($transaction, $data) {
            $recurrence = $this->requireRecurrence($transaction);

            $data = $this->resolveForeignKeyIds($recurrence, $data);

            $accountIds = $this->updateCurrentTransaction($transaction, $recurrence, $data);

            $this->updateRecurrence($recurrence, $data);

            $futureAccountIds = $this->updateFutureTransactions($transaction, $recurrence, $data);

            $this->recalculateAccounts(array_merge($accountIds, $futureAccountIds));
        });
    }

    /**
     * Dispatch a background job to soft-delete the current transaction, future instances,
     * and parent recurrence. Returns immediately — the actual work runs asynchronously.
     */
    public function deleteThisAndFuture(Transaction $transaction): void
    {
        dispatch(new ApplyRecurrenceScopeChangeJob(
            operation: 'delete',
            transactionUuid: $transaction->uuid,
        ));
    }

    /**
     * Apply the "Esta e parar futuras" soft-delete synchronously inside a DB::transaction.
     * Called by ApplyRecurrenceScopeChangeJob.
     */
    public function applyDeleteThisAndFuture(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $recurrence = $transaction->recurrence;

            if (! $recurrence) {
                throw ValidationException::withMessages([
                    'recurrence' => 'Transação não está vinculada a uma recorrência.',
                ]);
            }

            $accountsToRecalculate = [];

            // ── Soft-delete current transaction (recalculate balance if paid) ──
            if ($transaction->paid_at && $transaction->account) {
                $accountsToRecalculate[] = $transaction->account_id;
            }
            $transaction->delete();

            // ── Soft-delete future transactions ────────────────────────────────
            $futureTransactions = Transaction::where('recurrence_id', $recurrence->id)
                ->where('date', '>', $transaction->date)
                ->whereNull('deleted_at')
                ->where('workspace_id', $recurrence->workspace_id)
                ->get();

            foreach ($futureTransactions as $futureTransaction) {
                if ($futureTransaction->paid_at && $futureTransaction->account) {
                    $accountsToRecalculate[] = $futureTransaction->account_id;
                }
                $futureTransaction->delete();
            }

            // ── Soft-delete parent recurrence ───────────────────────────────────
            $recurrence->delete();

            // ── Recalculate unique accounts ─────────────────────────────────────
            foreach (array_unique($accountsToRecalculate) as $accountId) {
                $account = Account::find($accountId);
                if ($account) {
                    $this->accountService->recalculateBalance($account);
                }
            }
        });
    }

    /**
     * Sync tags on a given model (Recurrence or Transaction) by resolving
     * tag UUIDs within the workspace.
     */
    public function syncTags(Recurrence|Transaction $model, Workspace $workspace, ?array $tagUuids): void
    {
        if (empty($tagUuids)) {
            return;
        }

        $tagIds = Tag::whereIn('uuid', $tagUuids)
            ->where('workspace_id', $workspace->id)
            ->pluck('id')
            ->toArray();

        $model->tags()->sync($tagIds);
    }

    // ── Private helpers ─────────────────────────────────────────────────

    /**
     * Determine whether a recurrence is eligible to generate an instance today.
     * Logs a warning and mutates state (exhaustion) when generation is blocked.
     */
    private function canGenerate(Recurrence $recurrence, Carbon $today): bool
    {
        if ($recurrence->status !== RecurrenceStatus::Active) {
            Log::warning("Recurrence {$recurrence->uuid}: status is not active, skipping");

            return false;
        }

        if ($recurrence->trashed()) {
            Log::warning("Recurrence {$recurrence->uuid}: is soft-deleted, skipping");

            return false;
        }

        if ($recurrence->next_date === null) {
            return false;
        }

        $recurrence->load(['account' => fn ($q) => $q->withTrashed()]);

        if ($recurrence->account?->trashed()) {
            Log::warning("Recurrence {$recurrence->uuid}: linked account is archived, skipping");

            return false;
        }

        return $this->markExhaustedIfPastEnd($recurrence) && $this->isDue($recurrence, $today);
    }

    /**
     * Mark the recurrence as exhausted (next_date = null) when it has passed its end date.
     * Returns false when the recurrence was exhausted, true otherwise.
     */
    private function markExhaustedIfPastEnd(Recurrence $recurrence): bool
    {
        if ($recurrence->until_date && $recurrence->next_date->gt($recurrence->until_date)) {
            $recurrence->next_date = null;
            $recurrence->save();

            return false;
        }

        return true;
    }

    private function isDue(Recurrence $recurrence, Carbon $today): bool
    {
        return $recurrence->next_date->lte($today);
    }

    private function resolveGenerationDate(Recurrence $recurrence, Carbon $today): Carbon
    {
        $mostRecentDueDate = $this->mostRecentOccurrenceOnOrBefore(
            $today,
            $recurrence->frequency,
            $recurrence->frequency_day,
            $recurrence->until_date
        );

        return $recurrence->next_date->lt($mostRecentDueDate)
            ? $mostRecentDueDate
            : $recurrence->next_date;
    }

    private function createGeneratedInstance(Recurrence $recurrence, Carbon $generationDate): ?Transaction
    {
        $originalNextDateStr = $recurrence->next_date->toDateString();

        try {
            return DB::transaction(function () use ($recurrence, $generationDate, $originalNextDateStr) {
                $transaction = $this->buildGeneratedTransaction($recurrence, $generationDate);

                $tagIds = $recurrence->tags()->pluck('tags.id')->toArray();
                $transaction->tags()->sync($tagIds);

                $this->advanceNextDate($recurrence, $generationDate, $originalNextDateStr);

                return $transaction;
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::warning("Recurrence {$recurrence->uuid}: optimistic lock prevented duplicate generation");

            return null;
        }
    }

    private function buildGeneratedTransaction(Recurrence $recurrence, Carbon $generationDate): Transaction
    {
        return Transaction::create([
            'uuid' => Str::orderedUuid()->toString(),
            'workspace_id' => $recurrence->workspace_id,
            'account_id' => $recurrence->account_id,
            'category_id' => $recurrence->category_id,
            'type' => $recurrence->type->value,
            'description' => $recurrence->description,
            'value' => $recurrence->value,
            'date' => $generationDate->toDateString(),
            'paid_at' => null,
            'recurrence_id' => $recurrence->id,
            'created_by' => $recurrence->created_by,
        ]);
    }

    private function advanceNextDate(Recurrence $recurrence, Carbon $generationDate, string $originalNextDateStr): void
    {
        $nextNextDate = $this->nextOccurrenceAfter($generationDate, $recurrence->frequency, $recurrence->frequency_day);

        $newNextDate = ($recurrence->until_date && $nextNextDate->gt($recurrence->until_date))
            ? null
            : $nextNextDate->toDateString();

        $affected = Recurrence::where('id', $recurrence->id)
            ->whereDate('next_date', $originalNextDateStr)
            ->update(['next_date' => $newNextDate ? Carbon::parse($newNextDate) : null]);

        if ($affected === 0) {
            throw new \Exception('Optimistic lock failed: another worker generated this instance');
        }
    }

    private function resolveForeignKeyIds(Recurrence $recurrence, array $data): array
    {
        if (isset($data['account_id'])) {
            $data['account_id'] = Account::where('uuid', $data['account_id'])
                ->where('workspace_id', $recurrence->workspace_id)
                ->firstOrFail()
                ->id;
        }

        if (isset($data['category_id'])) {
            $data['category_id'] = Category::where('uuid', $data['category_id'])
                ->where('workspace_id', $recurrence->workspace_id)
                ->firstOrFail()
                ->id;
        }

        return $data;
    }

    private function applyStartDate(Recurrence $recurrence, array $data): void
    {
        if (! isset($data['start_date'])) {
            return;
        }

        if ($recurrence->transactions()->exists()) {
            throw ValidationException::withMessages([
                'start_date' => 'A data de início não pode ser alterada pois já existem transações geradas.',
            ]);
        }

        $recurrence->start_date = Carbon::parse($data['start_date']);
    }

    private function applyEditableFields(Recurrence $recurrence, array $data): void
    {
        foreach (['description', 'value', 'account_id', 'category_id', 'until_date'] as $field) {
            if (! isset($data[$field])) {
                continue;
            }

            if ($field === 'until_date') {
                $recurrence->until_date = $data[$field] ? Carbon::parse($data[$field]) : null;
            } else {
                $recurrence->{$field} = $data[$field];
            }
        }
    }

    private function applyScheduleFields(Recurrence $recurrence, array $data): bool
    {
        $changed = false;

        if (isset($data['frequency'])) {
            $recurrence->frequency = $data['frequency'];
            $changed = true;
        }

        if (isset($data['frequency_day'])) {
            $recurrence->frequency_day = (int) $data['frequency_day'];
            $changed = true;
        }

        return $changed;
    }

    private function exhaustIfUntilDatePassed(Recurrence $recurrence, array $data): void
    {
        if (! isset($data['until_date']) || ! $recurrence->next_date) {
            return;
        }

        if ($recurrence->until_date && $recurrence->next_date->gt($recurrence->until_date)) {
            $recurrence->next_date = null;
        }
    }

    private function recomputeNextDateAfterScheduleChange(Recurrence $recurrence): void
    {
        $frequency = $recurrence->frequency instanceof RecurrenceFrequency
            ? $recurrence->frequency
            : RecurrenceFrequency::from($recurrence->frequency);

        $newNextDate = $this->nextOccurrenceOnOrAfter(Carbon::today(), $frequency, $recurrence->frequency_day);

        if ($recurrence->until_date && $newNextDate->gt($recurrence->until_date)) {
            $recurrence->next_date = null;
        } else {
            $recurrence->next_date = $newNextDate;
        }
    }

    private function requireRecurrence(Transaction $transaction): Recurrence
    {
        $recurrence = $transaction->recurrence;

        if (! $recurrence) {
            throw ValidationException::withMessages([
                'recurrence' => 'Transação não está vinculada a uma recorrência.',
            ]);
        }

        return $recurrence;
    }

    private function updateCurrentTransaction(Transaction $transaction, Recurrence $recurrence, array $data): array
    {
        $wasPaid = $transaction->paid_at !== null;
        $oldAccountId = $transaction->account_id;

        $this->applySharedFields($transaction, $data);
        $transaction->save();

        if (! empty($data['tags'])) {
            $this->syncTags($transaction, $recurrence->workspace, $data['tags']);
        }

        return $this->accountsToRecalculate($transaction, $data, $wasPaid, $oldAccountId);
    }

    private function updateRecurrence(Recurrence $recurrence, array $data): void
    {
        $this->applySharedFields($recurrence, $data);
        $recurrence->save();

        if (! empty($data['tags'])) {
            $this->syncTags($recurrence, $recurrence->workspace, $data['tags']);
        }
    }

    private function updateFutureTransactions(Transaction $transaction, Recurrence $recurrence, array $data): array
    {
        $futureTransactions = Transaction::where('recurrence_id', $recurrence->id)
            ->where('date', '>', $transaction->date)
            ->whereNull('deleted_at')
            ->where('workspace_id', $recurrence->workspace_id)
            ->get();

        $accountIds = [];

        foreach ($futureTransactions as $futureTransaction) {
            $accountIds = array_merge(
                $accountIds,
                $this->updateOneFutureTransaction($futureTransaction, $recurrence, $data)
            );
        }

        return $accountIds;
    }

    private function updateOneFutureTransaction(Transaction $futureTransaction, Recurrence $recurrence, array $data): array
    {
        $wasPaid = $futureTransaction->paid_at !== null;
        $oldAccountId = $futureTransaction->account_id;

        $this->applySharedFields($futureTransaction, $data);
        $futureTransaction->save();

        if (! empty($data['tags'])) {
            $this->syncTags($futureTransaction, $recurrence->workspace, $data['tags']);
        }

        return $this->accountsToRecalculate($futureTransaction, $data, $wasPaid, $oldAccountId);
    }

    private function applySharedFields(Model $model, array $data): void
    {
        foreach (['description', 'value', 'account_id', 'category_id'] as $field) {
            if (isset($data[$field])) {
                $model->{$field} = $data[$field];
            }
        }
    }

    private function accountsToRecalculate(Transaction $transaction, array $data, bool $wasPaid, ?int $oldAccountId): array
    {
        if (! $wasPaid) {
            return [];
        }

        if (isset($data['account_id']) && (int) $data['account_id'] !== $oldAccountId) {
            return [$oldAccountId, (int) $data['account_id']];
        }

        if ($transaction->account) {
            return [$transaction->account_id];
        }

        return [];
    }

    private function recalculateAccounts(array $accountIds): void
    {
        foreach (array_unique($accountIds) as $accountId) {
            $account = Account::find($accountId);
            if ($account) {
                $this->accountService->recalculateBalance($account);
            }
        }
    }

    /**
     * Resolve an account UUID to its internal ID, scoped to workspace.
     */
    private function resolveAccountId(Workspace $workspace, string $uuid): int
    {
        return Account::where('uuid', $uuid)
            ->where('workspace_id', $workspace->id)
            ->firstOrFail()
            ->id;
    }

    /**
     * Resolve a category UUID to its internal ID, scoped to workspace.
     */
    private function resolveCategoryId(Workspace $workspace, string $uuid): int
    {
        return Category::where('uuid', $uuid)
            ->where('workspace_id', $workspace->id)
            ->firstOrFail()
            ->id;
    }

    /**
     * Compute the next occurrence strictly after the given date.
     */
    private function nextOccurrenceAfter(Carbon $date, RecurrenceFrequency $frequency, int $frequencyDay): Carbon
    {
        return match ($frequency) {
            RecurrenceFrequency::Weekly => $date->copy()->next($frequencyDay),
            RecurrenceFrequency::Monthly => (function () use ($date, $frequencyDay) {
                $next = $date->copy()->addMonthNoOverflow();
                $next->day = min($frequencyDay, $next->daysInMonth);

                return $next;
            })(),
        };
    }

    /**
     * Compute the first occurrence on or after the given date.
     */
    private function nextOccurrenceOnOrAfter(Carbon $date, RecurrenceFrequency $frequency, int $frequencyDay): Carbon
    {
        return match ($frequency) {
            RecurrenceFrequency::Weekly => (function () use ($date, $frequencyDay) {
                if ($date->dayOfWeek === $frequencyDay) {
                    return $date;
                }

                return $date->copy()->next($frequencyDay);
            })(),
            RecurrenceFrequency::Monthly => (function () use ($date, $frequencyDay) {
                $targetDay = min($frequencyDay, $date->daysInMonth);

                if ($date->day <= $targetDay) {
                    return $date->copy()->day($targetDay);
                }

                // Past the target day this month, go to next month
                $next = $date->copy()->addMonthNoOverflow();
                $next->day = min($frequencyDay, $next->daysInMonth);

                return $next;
            })(),
        };
    }

    /**
     * Compute the most recent occurrence on or before the given date.
     */
    private function mostRecentOccurrenceOnOrBefore(Carbon $date, RecurrenceFrequency $frequency, int $frequencyDay, ?Carbon $untilDate): Carbon
    {
        $boundary = $untilDate && $untilDate->lt($date) ? $untilDate : $date;

        return match ($frequency) {
            RecurrenceFrequency::Weekly => (function () use ($boundary, $frequencyDay) {
                if ($boundary->dayOfWeek === $frequencyDay) {
                    return $boundary;
                }

                return $boundary->copy()->previous($frequencyDay);
            })(),
            RecurrenceFrequency::Monthly => (function () use ($boundary, $frequencyDay) {
                $targetDay = min($frequencyDay, $boundary->daysInMonth);

                if ($boundary->day >= $targetDay) {
                    return $boundary->copy()->day($targetDay);
                }

                // Hasn't reached the target day this month — use last month's
                $prev = $boundary->copy()->subMonthNoOverflow();
                $prev->day = min($frequencyDay, $prev->daysInMonth);

                return $prev;
            })(),
        };
    }
}
