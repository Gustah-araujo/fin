<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BillStatus;
use App\Enums\RecurrenceFrequency;
use App\Enums\RecurrenceStatus;
use App\Enums\TransactionType;
use App\Exceptions\RecurrenceGenerationException;
use App\Jobs\ApplyRecurrenceScopeChangeJob;
use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\CreditCardBill;
use App\Models\Recurrence;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecurrenceService
{
    public function __construct(
        private readonly AccountService $accountService,
        private readonly BillService $billService,
        private readonly CreditCardService $creditCardService,
    ) {}

    /**
     * Create a recurrence rule without generating the first transaction.
     * Used when start_date > today.
     */
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Workspace $workspace, array $data, User $user): Recurrence
    {
        $accountId = $this->resolveAccountId($workspace, is_string($data['account_id'] ?? null) ? $data['account_id'] : null);
        $categoryId = $this->resolveCategoryId($workspace, is_string($data['category_id']) ? $data['category_id'] : '');

        $startDate = self::toDate($data['start_date']);
        $today = Carbon::today();
        $frequency = RecurrenceFrequency::from(self::toStringValue($data['frequency']));
        $frequencyDay = self::toIntValue($data['frequency_day']);

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
            'until_date' => isset($data['until_date']) && $data['until_date'] ? self::toDate($data['until_date']) : null,
            'next_date' => $nextDate,
            'status' => RecurrenceStatus::Active,
            'created_by' => $user->id,
        ]);

        if (! empty($data['tags'])) {
            $this->syncTags($recurrence, $workspace, self::toStringList($data['tags']));
        }

        return $recurrence;
    }

    /**
     * Create a recurrence and immediately generate the first transaction.
     * Used when start_date <= today.
     *
     * @return array{recurrence: Recurrence, transaction: Transaction}
     */
    /**
     * @param  array<string, mixed>  $data
     */
    /**
     * @param  array<string, mixed>  $data
     * @return array{recurrence: Recurrence, transaction: Transaction}
     */
    public function createWithFirstInstance(Workspace $workspace, array $data, User $user): array
    {
        return DB::transaction(function () use ($workspace, $data, $user) {
            $accountId = $this->resolveAccountId($workspace, is_string($data['account_id'] ?? null) ? $data['account_id'] : null);
            $categoryId = $this->resolveCategoryId($workspace, is_string($data['category_id']) ? $data['category_id'] : '');

            $startDate = self::toDate($data['start_date']);
            $startDateStr = $startDate->toDateString();
            $frequency = RecurrenceFrequency::from(self::toStringValue($data['frequency']));
            $frequencyDay = self::toIntValue($data['frequency_day']);

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
                'until_date' => isset($data['until_date']) && $data['until_date'] ? self::toDate($data['until_date']) : null,
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
            $this->syncFirstInstanceTags($recurrence, $transaction, $workspace, $data['tags'] ?? null);

            // Advance next_date to next occurrence
            $this->advanceFirstNextDate($recurrence, $startDate, $frequency, $frequencyDay, $startDateStr);

            return [
                'recurrence' => $recurrence->fresh() ?? $recurrence,
                'transaction' => $transaction,
            ];
        });
    }

    /**
     * Create a recurrence and generate its buffer of real instances.
     *
     * Generates every retroactive occurrence before today plus $bufferAhead
     * future occurrences as real Transaction rows. Replaces create() and
     * createWithFirstInstance().
     */
    /**
     * @param  array<string, mixed>  $data
     */
    public function createWithBuffer(Workspace $workspace, array $data, User $user): Recurrence
    {
        return DB::transaction(function () use ($workspace, $data, $user) {
            $categoryId = $this->resolveCategoryId($workspace, is_string($data['category_id']) ? $data['category_id'] : '');

            $startDate = self::toDate($data['start_date']);
            $frequency = RecurrenceFrequency::from(self::toStringValue($data['frequency']));
            $frequencyDay = self::toIntValue($data['frequency_day']);
            $bufferAhead = self::toIntValue($data['buffer_ahead'] ?? 12);
            $untilDate = isset($data['until_date']) && $data['until_date']
                ? self::toDate($data['until_date'])
                : null;

            $type = $data['type'] ?? TransactionType::Income;
            $typeValue = $type instanceof TransactionType
                ? $type->value
                : (is_string($type) ? $type : TransactionType::Income->value);

            [$accountId, $card] = $this->resolveRecurrenceTarget($workspace, $data, $startDate);

            $dates = $this->computeBufferDates($startDate, $frequency, $frequencyDay, $bufferAhead, $untilDate);

            $recurrence = Recurrence::create([
                'uuid' => Str::orderedUuid()->toString(),
                'workspace_id' => $workspace->id,
                'account_id' => $accountId,
                'credit_card_id' => $card?->uuid,
                'category_id' => $categoryId,
                'type' => $type,
                'description' => $data['description'],
                'value' => $data['value'],
                'frequency' => $data['frequency'],
                'frequency_day' => $frequencyDay,
                'start_date' => $startDate,
                'until_date' => $untilDate,
                'next_date' => $this->nextDateAfterBuffer($dates, $frequency, $frequencyDay, $untilDate),
                'status' => RecurrenceStatus::Active,
                'buffer_ahead' => $bufferAhead,
                'created_by' => $user->id,
            ]);

            $this->insertBufferForRecurrence($recurrence, $card, $typeValue, $dates);

            if (! empty($data['tags'])) {
                $this->syncTags($recurrence, $workspace, self::toStringList($data['tags']));
                $this->syncTagsToInstances($recurrence, $workspace, self::toStringList($data['tags']));
            }

            return $recurrence;
        });
    }

    private function syncFirstInstanceTags(Recurrence $recurrence, Transaction $transaction, Workspace $workspace, mixed $tags): void
    {
        if (! is_array($tags) || $tags === []) {
            return;
        }

        $this->syncTags($recurrence, $workspace, self::toStringList($tags));
        $this->syncTags($transaction, $workspace, self::toStringList($tags));
    }

    private function advanceFirstNextDate(Recurrence $recurrence, Carbon $startDate, RecurrenceFrequency $frequency, int $frequencyDay, string $startDateStr): void
    {
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
    }

    /**
     * Count the recurrence's future instances (date >= today), excluding soft-deleted ones.
     */
    public function countFutureInstances(Recurrence $recurrence): int
    {
        return Transaction::where('recurrence_id', $recurrence->id)
            ->whereNull('deleted_at')
            ->whereDate('date', '>=', today())
            ->count();
    }

    /**
     * Replenish the recurrence's buffer of future instances up to buffer_ahead.
     */
    public function generateBufferInstances(Recurrence $recurrence): void
    {
        $needed = (int) $recurrence->buffer_ahead - $this->countFutureInstances($recurrence);

        if ($needed <= 0) {
            return;
        }

        $frequency = $recurrence->frequency;

        $card = $this->resolveBufferCard($recurrence);
        if ($card === null && $recurrence->credit_card_id) {
            // Card-based recurrence but card is archived — skip
            return;
        }

        $startDate = $this->computeBufferStartDate($recurrence);
        $date = $this->nextOccurrenceOnOrAfter($startDate, $frequency, $recurrence->frequency_day);

        $this->fillBufferInstances($recurrence, $card, $frequency, $date, $needed);

        $this->updateNextDateFromToday($recurrence, $frequency, $recurrence->frequency_day);
    }

    /**
     * Resolve the credit card for buffer generation. Returns null for account-based or if card is archived.
     */
    private function resolveBufferCard(Recurrence $recurrence): ?CreditCard
    {
        if (! $recurrence->credit_card_id) {
            return null;
        }

        $card = CreditCard::withTrashed()->where('uuid', $recurrence->credit_card_id)->first();

        if (! $card || $card->trashed()) {
            return null;
        }

        return $card;
    }

    /**
     * Compute the start date for buffer generation.
     */
    private function computeBufferStartDate(Recurrence $recurrence): Carbon
    {
        $latestDate = Transaction::where('recurrence_id', $recurrence->id)
            ->whereNull('deleted_at')
            ->whereDate('date', '>=', today())
            ->max('date');

        return is_string($latestDate) || is_numeric($latestDate)
            ? Carbon::parse($latestDate)->addDay()
            : Carbon::today();
    }

    /**
     * Fill the buffer with new instances up to the needed count.
     */
    private function fillBufferInstances(Recurrence $recurrence, ?CreditCard $card, RecurrenceFrequency $frequency, Carbon $date, int $needed): void
    {
        $tagIds = $recurrence->tags()->pluck('tags.id')->toArray();
        $affectedBillIds = [];

        for ($i = 0; $i < $needed; $i++) {
            if ($recurrence->until_date && $date->gt($recurrence->until_date)) {
                break;
            }

            $transaction = $this->createBufferTransaction($recurrence, $date, $card);
            $transaction->tags()->sync($tagIds);

            if ($transaction->credit_card_bill_id !== null) {
                $affectedBillIds[] = $transaction->credit_card_bill_id;
            }

            $date = $this->nextOccurrenceAfter($date, $frequency, $recurrence->frequency_day);
        }

        if ($affectedBillIds !== [] && $card !== null) {
            $this->recalculateAffectedBills(array_values(array_unique($affectedBillIds)));
            $this->recalculateCardLimit($card);
        }
    }

    /**
     * Propagate rule field changes to all future instances of the recurrence.
     */
    /**
     * @param  array<string, mixed>  $data
     */
    public function propagateToFuture(Recurrence $recurrence, array $data): void
    {
        DB::transaction(function () use ($recurrence, $data) {
            $workspace = $recurrence->workspace;

            if (! $workspace instanceof Workspace) {
                return;
            }

            $data = $this->resolveForeignKeyIds($recurrence, $data);

            $futureTransactions = Transaction::where('recurrence_id', $recurrence->id)
                ->whereNull('deleted_at')
                ->whereDate('date', '>=', today())
                ->get();

            /** @var list<int> $accountIds */
            $accountIds = [];

            foreach ($futureTransactions as $transaction) {
                $wasPaid = $transaction->paid_at !== null;
                $oldAccountId = $transaction->account_id;

                $this->applySharedFields($transaction, $data);
                $transaction->save();

                if (! empty($data['tags'])) {
                    $this->syncTags($transaction, $workspace, self::toStringList($data['tags']));
                }

                $accountIds = array_merge(
                    $accountIds,
                    $this->accountsToRecalculate($transaction, $data, $wasPaid, $oldAccountId),
                );
            }

            $this->recalculateAccounts(array_unique($accountIds));
        });
    }

    /**
     * Generate a transaction for the current period of a recurrence.
     * Idempotent: blocked if a transaction already exists for the period.
     */
    public function generateNextInstance(Recurrence $recurrence, bool $advanceNextDate = false): Transaction
    {
        $today = Carbon::today();

        if ($recurrence->status !== RecurrenceStatus::Active) {
            throw new RecurrenceGenerationException('A recorrência não está ativa.');
        }

        if ($recurrence->trashed()) {
            throw new RecurrenceGenerationException('A recorrência foi removida.');
        }

        if ($recurrence->next_date === null) {
            throw new RecurrenceGenerationException('A recorrência não possui próxima data.');
        }

        $this->assertLinkedAccountAvailable($recurrence);

        $generationDate = $this->scheduledDateForPeriod($today, $recurrence->frequency, $recurrence->frequency_day);

        $this->assertWithinUntilDate($recurrence, $today, $generationDate);

        if ($this->hasTransactionInPeriod($recurrence, $today)) {
            throw new RecurrenceGenerationException('Já existe uma transação gerada para este período.');
        }

        $instance = $this->createGeneratedInstance($recurrence, $generationDate, $advanceNextDate);

        if ($instance === null) {
            throw new RecurrenceGenerationException('Não foi possível gerar a transação deste período.');
        }

        return $instance;
    }

    private function assertLinkedAccountAvailable(Recurrence $recurrence): void
    {
        $recurrence->load(['account' => fn ($q) => $q->withTrashed()]);

        if ($recurrence->account?->trashed()) {
            throw new RecurrenceGenerationException('A conta vinculada foi arquivada.');
        }
    }

    private function assertWithinUntilDate(Recurrence $recurrence, Carbon $today, Carbon $generationDate): void
    {
        if ($recurrence->until_date && ($today->gt($recurrence->until_date) || $generationDate->gt($recurrence->until_date))) {
            throw new RecurrenceGenerationException('A recorrência já atingiu sua data final.');
        }
    }

    /**
     * Advance next_date past periods that already have a transaction.
     * Used by the job to skip consumed periods.
     */
    public function skipConsumedPeriods(Recurrence $recurrence): void
    {
        $today = Carbon::today();
        $maxIterations = 100;

        for ($i = 0; $i < $maxIterations; $i++) {
            if ($recurrence->next_date === null) {
                break;
            }

            if ($recurrence->next_date->gt($today)) {
                break;
            }

            if (! $this->hasTransactionInPeriod($recurrence, $recurrence->next_date)) {
                break;
            }

            $frequency = $recurrence->frequency;

            $nextNext = $this->nextOccurrenceAfter($recurrence->next_date, $frequency, $recurrence->frequency_day);

            $recurrence->next_date = ($recurrence->until_date && $nextNext->gt($recurrence->until_date))
                ? null
                : $nextNext;
        }

        $recurrence->save();
    }

    /**
     * Compute [start, end] bounds of the period containing the given date.
     *
     * @return array{Carbon, Carbon}
     */
    private function periodBounds(Carbon $date, RecurrenceFrequency $frequency, int $frequencyDay): array
    {
        return match ($frequency) {
            RecurrenceFrequency::Weekly => (function () use ($date, $frequencyDay) {
                if ($date->dayOfWeek === $frequencyDay) {
                    $start = $date->copy();
                } else {
                    $start = $date->copy()->previous($frequencyDay);
                    if ($start->gt($date)) {
                        $start->subWeek();
                    }
                }

                return [$start, $start->copy()->addDays(6)];
            })(),
            RecurrenceFrequency::Monthly => [
                $date->copy()->startOfMonth(),
                $date->copy()->endOfMonth(),
            ],
        };
    }

    /**
     * Compute the scheduled date (frequency_day) of the period containing the given date.
     */
    private function scheduledDateForPeriod(Carbon $date, RecurrenceFrequency $frequency, int $frequencyDay): Carbon
    {
        return match ($frequency) {
            RecurrenceFrequency::Weekly => (function () use ($date, $frequencyDay) {
                if ($date->dayOfWeek === $frequencyDay) {
                    return $date->copy();
                }

                $day = $date->copy()->previous($frequencyDay);
                if ($day->gt($date)) {
                    $day->subWeek();
                }

                return $day;
            })(),
            RecurrenceFrequency::Monthly => $date->copy()->day(min($frequencyDay, $date->daysInMonth)),
        };
    }

    /**
     * Check if a transaction already exists for this recurrence within the period containing the given date.
     */
    public function hasTransactionInPeriod(Recurrence $recurrence, Carbon $date): bool
    {
        [$start, $end] = $this->periodBounds($date, $recurrence->frequency, $recurrence->frequency_day);

        return Transaction::where('recurrence_id', $recurrence->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->exists();
    }

    /**
     * Recompute the next_date from today respecting frequency and until_date.
     */
    public function recomputeNextDate(Recurrence $recurrence): ?\Illuminate\Support\Carbon
    {
        $today = Carbon::today();
        $frequency = $recurrence->frequency;
        $frequencyDay = $recurrence->frequency_day;

        $nextDate = $this->nextOccurrenceOnOrAfter($today, $frequency, $frequencyDay);

        if ($recurrence->until_date && $nextDate->gt($recurrence->until_date)) {
            return null;
        }

        return $nextDate;
    }

    /**
     * Update recurrence rule fields and recompute next_date when needed.
     */
    /**
     * @param  array<string, mixed>  $data
     */
    public function updateRule(Recurrence $recurrence, array $data): Recurrence
    {
        $data = $this->resolveForeignKeyIds($recurrence, $data);

        $this->validateUpdatePaidBillCollision($recurrence, $data);

        $this->applyStartDate($recurrence, $data);

        $this->applyEditableFields($recurrence, $data);

        $scheduleChanged = $this->applyScheduleFields($recurrence, $data);

        $this->exhaustIfUntilDatePassed($recurrence, $data);

        if ($scheduleChanged) {
            $this->recomputeNextDateAfterScheduleChange($recurrence);
        }

        $recurrence->save();

        if (! empty($data['tags'])) {
            $this->syncTagsForRecurrence($recurrence, $recurrence, self::toStringList($data['tags']));
        }

        return $recurrence->fresh() ?? $recurrence;
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
    /**
     * @param  array<string, mixed>  $data
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
    /**
     * @param  array<string, mixed>  $data
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
     * Sync tags on a given model using the recurrence's workspace, when it exists.
     *
     * @param  array<int, string>|null  $tagUuids
     */
    private function syncTagsForRecurrence(Recurrence|Transaction $model, Recurrence $recurrence, ?array $tagUuids): void
    {
        $workspace = $recurrence->workspace;

        if ($workspace === null) {
            return;
        }

        $this->syncTags($model, $workspace, $tagUuids);
    }

    /**
     * Sync tags on a given model (Recurrence or Transaction) by resolving
     * tag UUIDs within the workspace.
     *
     * @param  array<int, string>|null  $tagUuids
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

    private function createGeneratedInstance(Recurrence $recurrence, Carbon $generationDate, bool $advanceNextDate = false): ?Transaction
    {
        $originalNextDate = $recurrence->next_date;

        if ($originalNextDate === null) {
            return null;
        }

        $originalNextDateStr = $originalNextDate->toDateString();

        try {
            return DB::transaction(function () use ($recurrence, $generationDate, $originalNextDateStr, $advanceNextDate) {
                $transaction = $this->buildGeneratedTransaction($recurrence, $generationDate);

                $tagIds = $recurrence->tags()->pluck('tags.id')->toArray();
                $transaction->tags()->sync($tagIds);

                if ($advanceNextDate) {
                    $this->advanceNextDate($recurrence, $generationDate, $originalNextDateStr);
                }

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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolveForeignKeyIds(Recurrence $recurrence, array $data): array
    {
        if (array_key_exists('account_id', $data)) {
            $data['account_id'] = empty($data['account_id'])
                ? null
                : Account::where('uuid', is_string($data['account_id']) ? $data['account_id'] : '')
                    ->where('workspace_id', $recurrence->workspace_id)
                    ->firstOrFail()
                    ->id;
        }

        if (isset($data['category_id']) && is_string($data['category_id'])) {
            $data['category_id'] = Category::where('uuid', $data['category_id'])
                ->where('workspace_id', $recurrence->workspace_id)
                ->firstOrFail()
                ->id;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyStartDate(Recurrence $recurrence, array $data): void
    {
        if (! isset($data['start_date'])) {
            return;
        }

        $newStartDate = self::toDate($data['start_date']);

        if ($recurrence->start_date->isSameDay($newStartDate)) {
            return;
        }

        if ($recurrence->transactions()->exists()) {
            throw ValidationException::withMessages([
                'start_date' => 'A data de início não pode ser alterada pois já existem transações geradas.',
            ]);
        }

        $recurrence->start_date = $newStartDate;
    }

    /**
     * Reject changing a card recurrence's start_date when any occurrence between
     * the new start_date and today would fall on a bill that is already PAID.
     */
    /**
     * @param  array<string, mixed>  $data
     */
    private function validateUpdatePaidBillCollision(Recurrence $recurrence, array $data): void
    {
        if (! isset($data['start_date']) || ! $recurrence->credit_card_id) {
            return;
        }

        $newStartDate = self::toDate($data['start_date']);

        if ($recurrence->start_date->isSameDay($newStartDate)) {
            return;
        }

        $card = CreditCard::where('uuid', $recurrence->credit_card_id)->first();

        if (! $card) {
            return;
        }

        $this->validatePaidBillCollision($card, $newStartDate);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyEditableFields(Recurrence $recurrence, array $data): void
    {
        $this->applyEditableContentFields($recurrence, $data);
        $this->applyEditableScheduleFields($recurrence, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyEditableContentFields(Recurrence $recurrence, array $data): void
    {
        if (isset($data['description']) && is_string($data['description'])) {
            $recurrence->description = $data['description'];
        }

        if (isset($data['value']) && is_numeric($data['value'])) {
            $recurrence->value = (float) $data['value'];
        }

        if (isset($data['buffer_ahead'])) {
            $recurrence->buffer_ahead = self::toIntValue($data['buffer_ahead']);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyEditableScheduleFields(Recurrence $recurrence, array $data): void
    {
        if (array_key_exists('account_id', $data) && (is_int($data['account_id']) || $data['account_id'] === null)) {
            $recurrence->account_id = $data['account_id'];
        }

        if (isset($data['category_id']) && is_int($data['category_id'])) {
            $recurrence->category_id = $data['category_id'];
        }

        if (isset($data['until_date'])) {
            $recurrence->until_date = $data['until_date'] ? self::toDate($data['until_date']) : null;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyScheduleFields(Recurrence $recurrence, array $data): bool
    {
        $changed = false;

        if (isset($data['frequency']) && is_string($data['frequency'])) {
            $recurrence->frequency = RecurrenceFrequency::from($data['frequency']);
            $changed = true;
        }

        if (isset($data['frequency_day']) && is_numeric($data['frequency_day'])) {
            $recurrence->frequency_day = (int) $data['frequency_day'];
            $changed = true;
        }

        return $changed;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function exhaustIfUntilDatePassed(Recurrence $recurrence, array $data): void
    {
        if (! isset($data['until_date']) || $recurrence->next_date === null) {
            return;
        }

        if ($recurrence->until_date && $recurrence->next_date->gt($recurrence->until_date)) {
            $recurrence->next_date = null;
        }
    }

    private function recomputeNextDateAfterScheduleChange(Recurrence $recurrence): void
    {
        $frequency = $recurrence->frequency;

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

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function updateCurrentTransaction(Transaction $transaction, Recurrence $recurrence, array $data): array
    {
        $wasPaid = $transaction->paid_at !== null;
        $oldAccountId = $transaction->account_id;

        $this->applySharedFields($transaction, $data);
        $transaction->save();

        if (! empty($data['tags'])) {
            $this->syncTagsForRecurrence($transaction, $recurrence, self::toStringList($data['tags']));
        }

        return $this->accountsToRecalculate($transaction, $data, $wasPaid, $oldAccountId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateRecurrence(Recurrence $recurrence, array $data): void
    {
        $this->applySharedFields($recurrence, $data);
        $recurrence->save();

        if (! empty($data['tags'])) {
            $this->syncTagsForRecurrence($recurrence, $recurrence, self::toStringList($data['tags']));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
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

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function updateOneFutureTransaction(Transaction $futureTransaction, Recurrence $recurrence, array $data): array
    {
        $wasPaid = $futureTransaction->paid_at !== null;
        $oldAccountId = $futureTransaction->account_id;

        $this->applySharedFields($futureTransaction, $data);
        $futureTransaction->save();

        if (! empty($data['tags'])) {
            $this->syncTagsForRecurrence($futureTransaction, $recurrence, self::toStringList($data['tags']));
        }

        return $this->accountsToRecalculate($futureTransaction, $data, $wasPaid, $oldAccountId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applySharedFields(Recurrence|Transaction $model, array $data): void
    {
        $this->applySharedContentFields($model, $data);
        $this->applySharedRelationFields($model, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applySharedContentFields(Recurrence|Transaction $model, array $data): void
    {
        if (isset($data['description'])) {
            $model->description = is_string($data['description']) ? $data['description'] : '';
        }

        if (isset($data['value']) && is_numeric($data['value'])) {
            $model->value = (float) $data['value'];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applySharedRelationFields(Recurrence|Transaction $model, array $data): void
    {
        if (array_key_exists('account_id', $data) && (is_int($data['account_id']) || $data['account_id'] === null)) {
            $model->account_id = $data['account_id'];
        }

        if (isset($data['category_id']) && is_int($data['category_id'])) {
            $model->category_id = $data['category_id'];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function accountsToRecalculate(Transaction $transaction, array $data, bool $wasPaid, ?int $oldAccountId): array
    {
        if (! $wasPaid) {
            return [];
        }

        if (array_key_exists('account_id', $data)) {
            $newAccountId = $data['account_id'];

            if ($newAccountId !== $oldAccountId) {
                return array_values(array_filter(
                    [$oldAccountId, is_int($newAccountId) ? $newAccountId : null],
                    static fn (?int $accountId): bool => $accountId !== null,
                ));
            }
        }

        if ($transaction->account && $transaction->account_id !== null) {
            return [$transaction->account_id];
        }

        return [];
    }

    /**
     * @param  array<int, int|null>  $accountIds
     */
    private function recalculateAccounts(array $accountIds): void
    {
        foreach (array_unique($accountIds) as $accountId) {
            if ($accountId === null) {
                continue;
            }

            $account = Account::find($accountId);

            if ($account instanceof Account) {
                $this->accountService->recalculateBalance($account);
            }
        }
    }

    /**
     * Resolve an account UUID to its internal ID, scoped to workspace.
     * Returns null when no UUID is provided (transactions without account).
     */
    private function resolveAccountId(Workspace $workspace, ?string $uuid): ?int
    {
        if (empty($uuid)) {
            return null;
        }

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
     * Build the list of dates to generate for a new recurrence: every
     * retroactive occurrence before today plus $bufferAhead future occurrences.
     *
     * @return array<int, Carbon>
     */
    private function computeBufferDates(Carbon $startDate, RecurrenceFrequency $frequency, int $frequencyDay, int $bufferAhead, ?Carbon $untilDate): array
    {
        $today = Carbon::today();
        $dates = [];
        $iterations = 0;
        $maxIterations = 1000;

        $date = $startDate->copy();

        if ($startDate->lte($today)) {
            // Retroactive instances: every occurrence strictly before today.
            while ($date->lt($today) && $iterations++ < $maxIterations) {
                if ($this->exceedsUntilDate($date, $untilDate)) {
                    return $dates;
                }

                $dates[] = $date->copy();
                $date = $this->nextOccurrenceAfter($date, $frequency, $frequencyDay);
            }
        }

        // Buffer instances: $bufferAhead occurrences starting at the first date >= today.
        for ($i = 0; $i < $bufferAhead && $iterations++ < $maxIterations; $i++) {
            if ($this->exceedsUntilDate($date, $untilDate)) {
                break;
            }

            $dates[] = $date->copy();
            $date = $this->nextOccurrenceAfter($date, $frequency, $frequencyDay);
        }

        return $dates;
    }

    /**
     * Determine whether a date falls after the recurrence's until_date.
     */
    private function exceedsUntilDate(Carbon $date, ?Carbon $untilDate): bool
    {
        return $untilDate !== null && $date->gt($untilDate);
    }

    /**
     * Compute the next_date that follows the last generated buffer instance.
     *
     * @param  array<int, Carbon>  $dates
     */
    private function nextDateAfterBuffer(array $dates, RecurrenceFrequency $frequency, int $frequencyDay, ?Carbon $untilDate): ?Carbon
    {
        if (empty($dates)) {
            return null;
        }

        $candidate = $this->nextOccurrenceAfter($dates[array_key_last($dates)], $frequency, $frequencyDay);

        if ($untilDate && $candidate->gt($untilDate)) {
            return null;
        }

        return $candidate;
    }

    /**
     * Bulk insert the buffer transactions for a freshly created recurrence.
     *
     * @param  array<int, Carbon>  $dates
     */
    private function insertBufferTransactions(Recurrence $recurrence, string $type, array $dates): void
    {
        if (empty($dates)) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($dates as $date) {
            $rows[] = [
                'uuid' => Str::orderedUuid()->toString(),
                'workspace_id' => $recurrence->workspace_id,
                'account_id' => $recurrence->account_id,
                'category_id' => $recurrence->category_id,
                'type' => $type,
                'description' => $recurrence->description,
                'value' => $recurrence->value,
                'date' => $date->toDateString(),
                'paid_at' => null,
                'recurrence_id' => $recurrence->id,
                'created_by' => $recurrence->created_by,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        Transaction::insert($rows);
    }

    /**
     * Bulk insert card-based buffer transactions, each linked to the appropriate bill.
     *
     * @param  array<int, Carbon>  $dates
     */
    private function insertCardBufferTransactions(Recurrence $recurrence, CreditCard $card, string $type, array $dates): void
    {
        if (empty($dates)) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($dates as $date) {
            $bill = $this->resolveCardBill($card, $date);

            $rows[] = [
                'uuid' => Str::orderedUuid()->toString(),
                'workspace_id' => $recurrence->workspace_id,
                'account_id' => null,
                'credit_card_id' => $card->id,
                'credit_card_bill_id' => $bill->id,
                'category_id' => $recurrence->category_id,
                'type' => $type,
                'description' => $recurrence->description,
                'value' => $recurrence->value,
                'date' => $date->toDateString(),
                'paid_at' => null,
                'recurrence_id' => $recurrence->id,
                'created_by' => $recurrence->created_by,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        Transaction::insert($rows);
    }

    /**
     * Resolve whether this recurrence targets an account or a credit card.
     *
     * @return array{int|null, CreditCard|null}
     */
    /**
     * @param  array<string, mixed>  $data
     * @return array{int|null, CreditCard|null}
     */
    private function resolveRecurrenceTarget(Workspace $workspace, array $data, Carbon $startDate): array
    {
        $creditCardId = $data['credit_card_id'] ?? null;

        if (! $creditCardId) {
            return [
                $this->resolveAccountId($workspace, is_string($data['account_id'] ?? null) ? $data['account_id'] : null),
                null,
            ];
        }

        if (! empty($data['account_id'])) {
            throw ValidationException::withMessages([
                'account_id' => 'Não é possível vincular uma conta e um cartão de crédito simultaneamente.',
            ]);
        }

        $installments = isset($data['installments']) && is_numeric($data['installments']) ? (int) $data['installments'] : 1;
        if ($installments > 1) {
            throw ValidationException::withMessages([
                'installments' => 'Despesas recorrentes em cartão não podem ser parceladas.',
            ]);
        }

        $card = CreditCard::where('uuid', $creditCardId)
            ->where('workspace_id', $workspace->id)
            ->firstOrFail();

        $this->validatePaidBillCollision($card, $startDate);

        return [null, $card];
    }

    /**
     * Insert buffer transactions using the appropriate strategy for the recurrence type.
     *
     * @param  array<int, Carbon>  $dates
     */
    private function insertBufferForRecurrence(Recurrence $recurrence, ?CreditCard $card, string $type, array $dates): void
    {
        if ($card) {
            $this->insertCardBufferTransactions($recurrence, $card, $type, $dates);
            $billIds = [];

            foreach (Transaction::where('recurrence_id', $recurrence->id)
                ->whereNotNull('credit_card_bill_id')
                ->distinct()
                ->pluck('credit_card_bill_id') as $billId) {
                if (is_int($billId)) {
                    $billIds[] = $billId;
                }
            }

            $this->recalculateAffectedBills($billIds);
            $this->recalculateCardLimit($card);
        } else {
            $this->insertBufferTransactions($recurrence, $type, $dates);
        }
    }

    /**
     * Create a single buffer transaction for a recurrence instance.
     */
    private function createBufferTransaction(Recurrence $recurrence, Carbon $date, ?CreditCard $card): Transaction
    {
        if ($card) {
            $bill = $this->resolveCardBill($card, $date);

            return Transaction::create([
                'uuid' => Str::orderedUuid()->toString(),
                'workspace_id' => $recurrence->workspace_id,
                'account_id' => null,
                'credit_card_id' => $card->id,
                'credit_card_bill_id' => $bill->id,
                'category_id' => $recurrence->category_id,
                'type' => $recurrence->type->value,
                'description' => $recurrence->description,
                'value' => $recurrence->value,
                'date' => $date->toDateString(),
                'paid_at' => null,
                'recurrence_id' => $recurrence->id,
                'created_by' => $recurrence->created_by,
            ]);
        }

        return Transaction::create([
            'uuid' => Str::orderedUuid()->toString(),
            'workspace_id' => $recurrence->workspace_id,
            'account_id' => $recurrence->account_id,
            'category_id' => $recurrence->category_id,
            'type' => $recurrence->type->value,
            'description' => $recurrence->description,
            'value' => $recurrence->value,
            'date' => $date->toDateString(),
            'paid_at' => null,
            'recurrence_id' => $recurrence->id,
            'created_by' => $recurrence->created_by,
        ]);
    }

    /**
     * Recalculate bill totals for the given bill IDs.
     *
     * @param  array<int>  $billIds
     */
    private function recalculateAffectedBills(array $billIds): void
    {
        foreach ($billIds as $billId) {
            $bill = CreditCardBill::find($billId);

            if ($bill instanceof CreditCardBill) {
                $this->billService->recalculateBillTotal($bill);
            }
        }
    }

    /**
     * Sync the given tags to every instance of the recurrence.
     *
     * @param  array<int, string>  $tagUuids
     */
    private function syncTagsToInstances(Recurrence $recurrence, Workspace $workspace, array $tagUuids): void
    {
        $transactions = Transaction::where('recurrence_id', $recurrence->id)->get();

        foreach ($transactions as $transaction) {
            $this->syncTags($transaction, $workspace, $tagUuids);
        }
    }

    /**
     * Recompute next_date from today respecting frequency and until_date.
     */
    private function updateNextDateFromToday(Recurrence $recurrence, RecurrenceFrequency $frequency, int $frequencyDay): void
    {
        $nextDate = $this->nextOccurrenceOnOrAfter(Carbon::today(), $frequency, $frequencyDay);

        $recurrence->next_date = ($recurrence->until_date && $nextDate->gt($recurrence->until_date))
            ? null
            : $nextDate;

        $recurrence->save();
    }

    /**
     * Compute the next occurrence strictly after the given date.
     */
    private function nextOccurrenceAfter(Carbon $date, RecurrenceFrequency $frequency, int $frequencyDay): \Illuminate\Support\Carbon
    {
        return \Illuminate\Support\Carbon::instance(match ($frequency) {
            RecurrenceFrequency::Weekly => $date->copy()->next($frequencyDay),
            RecurrenceFrequency::Monthly => (function () use ($date, $frequencyDay) {
                $next = $date->copy()->addMonthNoOverflow();
                $next->day = min($frequencyDay, $next->daysInMonth);

                return $next;
            })(),
        });
    }

    /**
     * Compute the first occurrence on or after the given date.
     */
    private function nextOccurrenceOnOrAfter(Carbon $date, RecurrenceFrequency $frequency, int $frequencyDay): \Illuminate\Support\Carbon
    {
        return \Illuminate\Support\Carbon::instance(match ($frequency) {
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
        });
    }

    /**
     * Validate that no occurrence between start_date and today falls on a PAID bill.
     * Uses billing periods (based on closing_day) instead of raw calendar months.
     *
     * @throws ValidationException
     */
    protected function validatePaidBillCollision(CreditCard $card, Carbon $startDate): void
    {
        $today = Carbon::today();

        // Compute the actual billing periods for start_date and today,
        // since closing_day logic may shift dates to different months.
        $startPeriod = $this->billService->computeBillPeriod($card, $startDate);
        $endPeriod = $this->billService->computeBillPeriod($card, $today);

        $period = Carbon::createFromDate($startPeriod['year'], $startPeriod['month'], 1);
        $end = Carbon::createFromDate($endPeriod['year'], $endPeriod['month'], 1);

        while ($period->lte($end)) {
            $bill = $card->bills()
                ->where('period_year', $period->year)
                ->where('period_month', $period->month)
                ->where('status', BillStatus::Paid)
                ->first();

            if ($bill) {
                throw ValidationException::withMessages([
                    'start_date' => 'A data de início colide com faturas já pagas do cartão.',
                ]);
            }

            $period->addMonth();
        }
    }

    /**
     * Recalculate the available limit of a card after buffer inserts.
     */
    private function recalculateCardLimit(?CreditCard $card): void
    {
        if ($card === null) {
            return;
        }

        $freshCard = $card->fresh();

        if ($freshCard !== null) {
            $this->creditCardService->recalculateAvailableLimit($freshCard);
        }
    }

    /**
     * Find or create the bill for a card + date.
     */
    protected function resolveCardBill(CreditCard $card, Carbon $date): CreditCardBill
    {
        return $this->billService->findOrCreateBill($card, $date, $card->created_by ?? 0);
    }

    private static function toDate(mixed $value): \Illuminate\Support\Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return \Illuminate\Support\Carbon::instance($value);
        }

        if (is_string($value) || is_numeric($value)) {
            return \Illuminate\Support\Carbon::parse($value);
        }

        throw new \InvalidArgumentException('Invalid date value.');
    }

    private static function toStringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function toIntValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @return list<string>
     */
    private static function toStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $strings = [];

        foreach ($value as $item) {
            if (is_string($item)) {
                $strings[] = $item;
            }
        }

        return $strings;
    }
}
