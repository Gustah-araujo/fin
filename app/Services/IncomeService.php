<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IncomeService
{
    public function __construct(
        private readonly AccountService $accountService,
    ) {}

    public function createInstallment(Workspace $workspace, User $creator, array $data): string
    {
        $groupId = Str::orderedUuid()->toString();
        $totalValue = (float) $data['value'];
        $count = (int) $data['installments_total'];
        $baseValue = round($totalValue / $count, 2);
        $remainder = round($totalValue - ($baseValue * $count), 2);
        $firstDate = Carbon::parse($data['date']);

        $categoryId = Category::where('uuid', $data['category_id'])
            ->where('workspace_id', $workspace->id)
            ->firstOrFail()
            ->id;

        $accountId = Account::where('uuid', $data['account_id'])
            ->where('workspace_id', $workspace->id)
            ->firstOrFail()
            ->id;

        DB::transaction(function () use ($workspace, $creator, $data, $groupId, $baseValue, $remainder, $count, $firstDate, $categoryId, $accountId) {
            for ($i = 1; $i <= $count; $i++) {
                $installmentDate = $firstDate->copy()->addMonthsNoOverflow($i - 1);
                $value = $i === $count ? $baseValue + $remainder : $baseValue;

                $transaction = Transaction::create([
                    'uuid' => Str::orderedUuid()->toString(),
                    'workspace_id' => $workspace->id,
                    'created_by' => $creator->id,
                    'account_id' => $accountId,
                    'category_id' => $categoryId,
                    'type' => TransactionType::Income->value,
                    'description' => $data['description'],
                    'value' => $value,
                    'date' => $installmentDate->toDateString(),
                    'installment_number' => $i,
                    'installments_total' => $count,
                    'installment_group_id' => $groupId,
                    'paid_at' => null,
                ]);

                if (! empty($data['tags'])) {
                    $this->syncTags($transaction, $data['tags']);
                }
            }
        });

        return $groupId;
    }

    public function deleteGroup(Transaction $income): void
    {
        DB::transaction(function () use ($income) {
            $group = Transaction::where('installment_group_id', $income->installment_group_id)
                ->whereNull('deleted_at')
                ->get();

            $affectedAccounts = collect();

            foreach ($group as $row) {
                if ($row->paid_at && $row->account) {
                    $affectedAccounts->push($row->account->id);
                }
                $row->delete();
            }

            foreach ($affectedAccounts->unique() as $accountId) {
                $account = Account::find($accountId);
                if ($account) {
                    $this->accountService->recalculateBalance($account);
                }
            }
        });
    }

    public function create(Workspace $workspace, User $creator, array $data): Transaction
    {
        $accountId = Account::where('uuid', $data['account_id'])
            ->where('workspace_id', $workspace->id)
            ->firstOrFail()
            ->id;

        $categoryId = Category::where('uuid', $data['category_id'])
            ->where('workspace_id', $workspace->id)
            ->firstOrFail()
            ->id;

        $transaction = Transaction::create([
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
        ]);

        if (! empty($data['tags'])) {
            $this->syncTags($transaction, $data['tags']);
        }

        return $transaction;
    }

    public function update(Transaction $income, array $data): Transaction
    {
        $fieldsToUpdate = ['description', 'value', 'date'];
        $oldAccountId = $income->account_id;
        $wasReceived = $income->paid_at !== null;
        $oldValue = (float) $income->value;

        if (isset($data['account_id'])) {
            $data['account_id'] = Account::where('uuid', $data['account_id'])
                ->where('workspace_id', $income->workspace_id)
                ->firstOrFail()
                ->id;
        }

        if (isset($data['category_id'])) {
            $data['category_id'] = Category::where('uuid', $data['category_id'])
                ->where('workspace_id', $income->workspace_id)
                ->firstOrFail()
                ->id;
        }

        foreach ($fieldsToUpdate as $field) {
            if (isset($data[$field])) {
                $income->{$field} = $data[$field];
            }
        }
        if (array_key_exists('account_id', $data)) {
            $income->account_id = $data['account_id'];
        }
        if (array_key_exists('category_id', $data)) {
            $income->category_id = $data['category_id'];
        }

        $income->save();

        if (! empty($data['tags'])) {
            $this->syncTags($income, $data['tags']);
        }

        $this->recalculateAfterUpdate($income, $wasReceived, $oldValue, $oldAccountId, $data);

        $income->refresh();

        return $income;
    }

    public function deleteSingle(Transaction $income): void
    {
        DB::transaction(function () use ($income) {
            $account = $income->account;

            $income->delete();

            if ($income->paid_at && $account) {
                $this->accountService->recalculateBalance($account);
            }
        });
    }

    public function receive(Transaction $income): void
    {
        if ($income->paid_at !== null) {
            return;
        }

        DB::transaction(function () use ($income) {
            $income->paid_at = now();
            $income->save();

            if ($income->account) {
                $this->accountService->recalculateBalance($income->account);
            }
        });
    }

    public function unreceive(Transaction $income): void
    {
        if ($income->paid_at === null) {
            return;
        }

        DB::transaction(function () use ($income) {
            $account = $income->account;

            $income->paid_at = null;
            $income->save();

            if ($account) {
                $this->accountService->recalculateBalance($account);
            }
        });
    }

    private function recalculateAfterUpdate(Transaction $income, bool $wasReceived, float $oldValue, ?int $oldAccountId, array $data): void
    {
        $isNowReceived = $income->paid_at !== null;
        $valueChanged = isset($data['value']) && (float) $data['value'] !== $oldValue;
        $accountChanged = array_key_exists('account_id', $data) && (int) $data['account_id'] !== $oldAccountId;
        $statusChanged = $wasReceived !== $isNowReceived;

        if (! $wasReceived && ! $isNowReceived) {
            return;
        }

        $this->recalculateOldAccountBalance($oldAccountId, $accountChanged);
        $this->recalculateCurrentAccountBalance($income, $isNowReceived, $valueChanged, $accountChanged, $statusChanged);
    }

    private function recalculateOldAccountBalance(?int $oldAccountId, bool $accountChanged): void
    {
        if (! $accountChanged || ! $oldAccountId) {
            return;
        }

        $oldAccount = Account::find($oldAccountId);

        if ($oldAccount) {
            $this->accountService->recalculateBalance($oldAccount);
        }
    }

    private function recalculateCurrentAccountBalance(Transaction $income, bool $isNowReceived, bool $valueChanged, bool $accountChanged, bool $statusChanged): void
    {
        if (! $isNowReceived || ! ($valueChanged || $accountChanged || $statusChanged)) {
            return;
        }

        if ($income->account) {
            $this->accountService->recalculateBalance($income->account);
        }
    }

    public function syncTags(Transaction $transaction, array $tagUuids): void
    {
        $tagIds = Tag::whereIn('uuid', $tagUuids)
            ->where('workspace_id', $transaction->workspace_id)
            ->pluck('id')
            ->toArray();

        $transaction->tags()->sync($tagIds);
    }
}
