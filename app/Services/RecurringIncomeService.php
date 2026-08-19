<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecurringIncomeService
{
    public function __construct(
        private readonly IncomeService $incomeService,
    ) {}

    public function createTemplate(Workspace $workspace, User $creator, array $data): Transaction
    {
        $accountId = Account::where('uuid', $data['account_id'])
            ->where('workspace_id', $workspace->id)
            ->firstOrFail()
            ->id;

        $categoryId = Category::where('uuid', $data['category_id'])
            ->where('workspace_id', $workspace->id)
            ->firstOrFail()
            ->id;

        $template = null;

        DB::transaction(function () use ($workspace, $creator, $data, $accountId, $categoryId, &$template) {
            $template = Transaction::create([
                'uuid' => Str::orderedUuid()->toString(),
                'workspace_id' => $workspace->id,
                'created_by' => $creator->id,
                'account_id' => $accountId,
                'category_id' => $categoryId,
                'type' => TransactionType::Income->value,
                'description' => $data['description'],
                'value' => $data['value'],
                'date' => $data['date'],
                'paid_at' => null,
                'is_recurring' => true,
                'recurring_parent_uuid' => null,
                'recurring_ends_at' => $data['recurring_ends_at'] ?? null,
                'recurring_year_month' => null,
            ]);

            if (! empty($data['tags'])) {
                $this->incomeService->syncTags($template, $data['tags']);
            }

            $this->generateOccurrencesUpTo($template, Carbon::now()->startOfMonth());
        });

        return $template;
    }

    public function generateOccurrencesUpTo(Transaction $template, Carbon $untilMonth): int
    {
        $startMonth = Carbon::parse($template->date)->startOfMonth();
        $end = $template->recurring_ends_at
            ? Carbon::parse($template->recurring_ends_at)->startOfMonth()
            : null;
        if ($end && $end < $untilMonth) {
            $untilMonth = $end;
        }

        $generated = 0;

        $cursor = $startMonth->copy();
        while ($cursor <= $untilMonth) {
            $bucket = $cursor->format('Y-m');
            $exists = Transaction::where('recurring_parent_uuid', $template->uuid)
                ->where('recurring_year_month', $bucket)
                ->exists();
            if (! $exists) {
                try {
                    DB::transaction(function () use ($template, $cursor, $bucket, &$generated) {
                        $occurrenceDate = $cursor->copy();
                        $dayOfMonth = min(Carbon::parse($template->date)->day, $occurrenceDate->daysInMonth);
                        $occurrenceDate->day($dayOfMonth);

                        $occurrence = Transaction::create([
                            'uuid' => Str::orderedUuid()->toString(),
                            'workspace_id' => $template->workspace_id,
                            'created_by' => $template->created_by,
                            'account_id' => $template->account_id,
                            'category_id' => $template->category_id,
                            'type' => TransactionType::Income->value,
                            'description' => $template->description,
                            'value' => $template->value,
                            'date' => $occurrenceDate->toDateString(),
                            'paid_at' => null,
                            'is_recurring' => true,
                            'recurring_parent_uuid' => $template->uuid,
                            'recurring_ends_at' => null,
                            'recurring_year_month' => $bucket,
                        ]);

                        $templateTags = $template->tags->pluck('id')->toArray();
                        if (! empty($templateTags)) {
                            $occurrence->tags()->sync($templateTags);
                        }

                        $generated++;
                    });
                } catch (QueryException $e) {
                    // unique violation - silently skip (idempotência)
                }
            }
            $cursor->addMonthNoOverflow();
        }

        return $generated;
    }

    public function updateTemplate(Transaction $template, array $data, bool $propagate): void
    {
        DB::transaction(function () use ($template, $data, $propagate) {
            $this->applyTemplateChanges($template, $data);
            $hasTags = $this->syncTemplateTags($template, $data);

            if ($propagate) {
                $this->propagateToOccurrences($template, $data, $hasTags);
            }
        });
    }

    private function applyTemplateChanges(Transaction $template, array $data): void
    {
        if (isset($data['description'])) {
            $template->description = $data['description'];
        }
        if (isset($data['value'])) {
            $template->value = $data['value'];
        }
        if (isset($data['account_id'])) {
            $template->account_id = Account::where('uuid', $data['account_id'])
                ->where('workspace_id', $template->workspace_id)
                ->firstOrFail()
                ->id;
        }
        if (isset($data['category_id'])) {
            $template->category_id = Category::where('uuid', $data['category_id'])
                ->where('workspace_id', $template->workspace_id)
                ->firstOrFail()
                ->id;
        }
        $template->save();
    }

    private function syncTemplateTags(Transaction $template, array $data): bool
    {
        if (! empty($data['tags'])) {
            $this->incomeService->syncTags($template, $data['tags']);

            return true;
        }

        return false;
    }

    private function propagateToOccurrences(Transaction $template, array $data, bool $hasTags): void
    {
        $occurrences = Transaction::where('recurring_parent_uuid', $template->uuid)
            ->whereNull('paid_at')
            ->whereNull('deleted_at')
            ->get();

        foreach ($occurrences as $occurrence) {
            $this->applyOccurrenceChanges($occurrence, $template, $data);
            $occurrence->save();

            if ($hasTags) {
                $occurrence->tags()->sync($template->tags->pluck('id')->toArray());
            }
        }
    }

    private function applyOccurrenceChanges(Transaction $occurrence, Transaction $template, array $data): void
    {
        if (isset($data['description'])) {
            $occurrence->description = $data['description'];
        }
        if (isset($data['value'])) {
            $occurrence->value = $data['value'];
        }
        if (isset($data['account_id'])) {
            $occurrence->account_id = $template->account_id;
        }
        if (isset($data['category_id'])) {
            $occurrence->category_id = $template->category_id;
        }
    }

    public function cancelTemplate(Transaction $template): void
    {
        $template->recurring_ends_at = today();
        $template->save();
    }

    public function deleteTemplate(Transaction $template, bool $orphanOccurrences): void
    {
        DB::transaction(function () use ($template, $orphanOccurrences) {
            if ($orphanOccurrences) {
                Transaction::where('recurring_parent_uuid', $template->uuid)
                    ->update([
                        'recurring_parent_uuid' => null,
                        'is_recurring' => false,
                    ]);
                $template->delete();
            } else {
                $template->delete();
                Transaction::where('recurring_parent_uuid', $template->uuid)
                    ->whereNull('paid_at')
                    ->whereNull('deleted_at')
                    ->delete();
            }
        });
    }
}
