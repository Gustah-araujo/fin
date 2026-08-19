<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionType;
use App\Exceptions\TransferSameAccountException;
use App\Models\Account;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransferService
{
    public function __construct(
        private readonly AccountService $accountService,
        private readonly CategoryService $categoryService,
    ) {}

    public function create(Workspace $workspace, User $creator, array $data): string
    {
        $transferGroupId = Str::orderedUuid()->toString();
        $transferCategoryId = $this->categoryService->ensureSystemCategory(
            $workspace,
            'Transferência',
            TransactionType::Both
        );

        $fromAccount = Account::where('uuid', $data['from_account_id'])
            ->where('workspace_id', $workspace->id)
            ->firstOrFail();
        $toAccount = Account::where('uuid', $data['to_account_id'])
            ->where('workspace_id', $workspace->id)
            ->firstOrFail();

        if ($fromAccount->id === $toAccount->id) {
            throw new TransferSameAccountException;
        }

        DB::transaction(function () use ($workspace, $creator, $data, $transferGroupId, $transferCategoryId, $fromAccount, $toAccount) {
            $expenseLeg = Transaction::create([
                'uuid' => Str::orderedUuid()->toString(),
                'workspace_id' => $workspace->id,
                'created_by' => $creator->id,
                'account_id' => $fromAccount->id,
                'category_id' => $transferCategoryId,
                'type' => TransactionType::Expense->value,
                'description' => $data['description'],
                'value' => $data['value'],
                'date' => $data['date'],
                'paid_at' => now(),
                'transfer_group_id' => $transferGroupId,
            ]);

            $incomeLeg = Transaction::create([
                'uuid' => Str::orderedUuid()->toString(),
                'workspace_id' => $workspace->id,
                'created_by' => $creator->id,
                'account_id' => $toAccount->id,
                'category_id' => $transferCategoryId,
                'type' => TransactionType::Income->value,
                'description' => $data['description'],
                'value' => $data['value'],
                'date' => $data['date'],
                'paid_at' => now(),
                'transfer_group_id' => $transferGroupId,
            ]);

            if (! empty($data['tags'])) {
                $tagIds = Tag::whereIn('uuid', $data['tags'])
                    ->where('workspace_id', $workspace->id)
                    ->pluck('id')
                    ->toArray();
                $expenseLeg->tags()->sync($tagIds);
                $incomeLeg->tags()->sync($tagIds);
            }

            $this->accountService->recalculateBalance($fromAccount);
            $this->accountService->recalculateBalance($toAccount);
        });

        return $transferGroupId;
    }

    public function updateGroup(string $transferGroupId, Workspace $workspace, array $data): void
    {
        $legs = Transaction::where('transfer_group_id', $transferGroupId)
            ->where('workspace_id', $workspace->id)
            ->whereNull('deleted_at')
            ->get();

        if ($legs->isEmpty()) {
            abort(404);
        }

        DB::transaction(function () use ($legs, $data, $workspace) {
            $expenseLeg = $legs->where('type', TransactionType::Expense)->first();
            $incomeLeg = $legs->where('type', TransactionType::Income)->first();

            $affectedAccounts = collect([$expenseLeg->account_id, $incomeLeg->account_id]);

            $this->applyCommonFieldUpdates($data, $expenseLeg, $incomeLeg);
            $this->applyFromAccountUpdate($data, $workspace, $expenseLeg, $affectedAccounts);
            $this->applyToAccountUpdate($data, $workspace, $incomeLeg, $affectedAccounts);

            $expenseLeg->save();
            $incomeLeg->save();

            $this->syncTags($data, $workspace, $expenseLeg, $incomeLeg);
            $this->recalculateBalances($affectedAccounts);
        });
    }

    private function applyCommonFieldUpdates(array $data, Transaction $expenseLeg, Transaction $incomeLeg): void
    {
        if (isset($data['description'])) {
            $expenseLeg->description = $data['description'];
            $incomeLeg->description = $data['description'];
        }
        if (isset($data['value'])) {
            $expenseLeg->value = $data['value'];
            $incomeLeg->value = $data['value'];
        }
        if (isset($data['date'])) {
            $expenseLeg->date = $data['date'];
            $incomeLeg->date = $data['date'];
        }
    }

    private function applyFromAccountUpdate(array $data, Workspace $workspace, Transaction $expenseLeg, Collection $affectedAccounts): void
    {
        if (isset($data['from_account_id'])) {
            $newFromId = Account::where('uuid', $data['from_account_id'])
                ->where('workspace_id', $workspace->id)
                ->firstOrFail()
                ->id;
            $expenseLeg->account_id = $newFromId;
            $affectedAccounts->push($newFromId);
        }
    }

    private function applyToAccountUpdate(array $data, Workspace $workspace, Transaction $incomeLeg, Collection $affectedAccounts): void
    {
        if (isset($data['to_account_id'])) {
            $newToId = Account::where('uuid', $data['to_account_id'])
                ->where('workspace_id', $workspace->id)
                ->firstOrFail()
                ->id;
            $incomeLeg->account_id = $newToId;
            $affectedAccounts->push($newToId);
        }
    }

    private function syncTags(array $data, Workspace $workspace, Transaction $expenseLeg, Transaction $incomeLeg): void
    {
        if (! empty($data['tags'])) {
            $tagIds = Tag::whereIn('uuid', $data['tags'])
                ->where('workspace_id', $workspace->id)
                ->pluck('id')
                ->toArray();
            $expenseLeg->tags()->sync($tagIds);
            $incomeLeg->tags()->sync($tagIds);
        }
    }

    private function recalculateBalances(Collection $affectedAccounts): void
    {
        foreach ($affectedAccounts->unique() as $accountId) {
            $account = Account::find($accountId);
            if ($account) {
                $this->accountService->recalculateBalance($account);
            }
        }
    }

    public function deleteGroup(string $transferGroupId, Workspace $workspace): void
    {
        $legs = Transaction::where('transfer_group_id', $transferGroupId)
            ->where('workspace_id', $workspace->id)
            ->whereNull('deleted_at')
            ->get();

        if ($legs->isEmpty()) {
            abort(404);
        }

        DB::transaction(function () use ($legs) {
            $affectedAccounts = collect();
            foreach ($legs as $leg) {
                if ($leg->account_id) {
                    $affectedAccounts->push($leg->account_id);
                }
                $leg->delete();
            }

            foreach ($affectedAccounts->unique() as $accountId) {
                $account = Account::find($accountId);
                if ($account) {
                    $this->accountService->recalculateBalance($account);
                }
            }
        });
    }
}
