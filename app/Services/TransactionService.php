<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TransactionService
{
    public function __construct(
        private readonly AccountService $accountService,
    ) {}

    public function create(Workspace $workspace, User $creator, array $data): Transaction
    {
        $type = isset($data['type'])
            ? TransactionType::tryFrom($data['type'])
            : TransactionType::Expense;

        if ($type === null) {
            throw ValidationException::withMessages([
                'type' => 'Tipo de transação inválido.',
            ]);
        }

        $accountId = Account::where('uuid', $data['account_id'])
            ->where('workspace_id', $workspace->id)
            ->firstOrFail()
            ->id;

        $categoryId = \App\Models\Category::where('uuid', $data['category_id'])
            ->where('workspace_id', $workspace->id)
            ->firstOrFail()
            ->id;

        $transactionData = [
            'uuid' => Str::orderedUuid()->toString(),
            'workspace_id' => $workspace->id,
            'created_by' => $creator->id,
            'account_id' => $accountId,
            'category_id' => $categoryId,
            'type' => $type->value,
            'description' => $data['description'],
            'value' => $data['value'],
            'date' => $data['date'],
            'paid_at' => null,
        ];

        if ($type === TransactionType::Income) {
            $transactionData['credit_card_id'] = null;
            $transactionData['credit_card_bill_id'] = null;
            $transactionData['installment_number'] = null;
            $transactionData['installments_total'] = null;
            $transactionData['installment_group_id'] = null;
        }

        $transaction = Transaction::create($transactionData);

        if (! empty($data['tags'])) {
            $this->syncTags($transaction, $data['tags']);
        }

        return $transaction;
    }

    public function update(Transaction $transaction, array $data): Transaction
    {
        $fieldsToUpdate = ['description', 'value', 'date', 'account_id', 'category_id'];
        $oldAccountId = $transaction->account_id;
        $wasPaid = $transaction->paid_at !== null;
        $oldValue = (float) $transaction->value;

        if (isset($data['account_id'])) {
            $data['account_id'] = Account::where('uuid', $data['account_id'])
                ->where('workspace_id', $transaction->workspace_id)
                ->firstOrFail()
                ->id;
        }

        if (isset($data['category_id'])) {
            $data['category_id'] = \App\Models\Category::where('uuid', $data['category_id'])
                ->where('workspace_id', $transaction->workspace_id)
                ->firstOrFail()
                ->id;
        }

        foreach ($fieldsToUpdate as $field) {
            if (isset($data[$field])) {
                $transaction->{$field} = $data[$field];
            }
        }

        if (array_key_exists('paid_at', $data)) {
            $transaction->paid_at = $data['paid_at'] ? now() : null;
        }

        $transaction->save();

        if (! empty($data['tags'])) {
            $this->syncTags($transaction, $data['tags']);
        }

        $this->recalculateAfterUpdate($transaction, $wasPaid, $oldValue, $oldAccountId, $data);

        return $transaction;
    }

    public function pay(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $account = $transaction->account_id
                ? Account::withTrashed()->find($transaction->account_id)
                : null;

            if ($account && $account->trashed()) {
                throw ValidationException::withMessages([
                    'account' => 'A conta vinculada foi arquivada. Restaure a conta antes de confirmar o recebimento.',
                ]);
            }

            $transaction->paid_at = now();
            $transaction->save();

            if ($account) {
                $this->accountService->recalculateBalance($account);
            }
        });
    }

    public function unpay(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $account = $transaction->account;

            $transaction->paid_at = null;
            $transaction->save();

            if ($account) {
                $this->accountService->recalculateBalance($account);
            }
        });
    }

    public function archive(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $account = $transaction->account;

            $transaction->delete();

            if ($transaction->paid_at && $account) {
                $this->accountService->recalculateBalance($account);
            }
        });
    }

    public function syncTags(Transaction $transaction, array $tagUuids): void
    {
        $tagIds = \App\Models\Tag::whereIn('uuid', $tagUuids)
            ->where('workspace_id', $transaction->workspace_id)
            ->pluck('id')
            ->toArray();

        $transaction->tags()->sync($tagIds);
    }

    private function recalculateAfterUpdate(Transaction $transaction, bool $wasPaid, float $oldValue, ?int $oldAccountId, array $data): void
    {
        $isNowPaid = $transaction->paid_at !== null;
        $valueChanged = isset($data['value']) && (float) $data['value'] !== $oldValue;
        $accountChanged = isset($data['account_id']) && (int) $data['account_id'] !== $oldAccountId;

        if (! $wasPaid && ! $isNowPaid) {
            return;
        }

        if ($wasPaid && ! $isNowPaid) {
            if ($transaction->account) {
                $this->accountService->recalculateBalance($transaction->account);
            }
            return;
        }

        if ($isNowPaid && ($valueChanged || $accountChanged)) {
            DB::transaction(function () use ($transaction, $oldAccountId) {
                if ($oldAccountId && $oldAccountId !== $transaction->account_id) {
                    $oldAccount = Account::find($oldAccountId);
                    if ($oldAccount) {
                        $this->accountService->recalculateBalance($oldAccount);
                    }
                }

                if ($transaction->account) {
                    $this->accountService->recalculateBalance($transaction->account);
                }
            });
        }
    }
}
