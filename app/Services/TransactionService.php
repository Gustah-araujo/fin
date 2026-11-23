<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BillStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\CreditCardBill;
use App\Models\ExpenseGroup;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TransactionService
{
    public function __construct(
        private readonly AccountService $accountService,
        private readonly BillService $billService,
        private readonly CreditCardService $creditCardService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Workspace $workspace, User $creator, array $data): Transaction
    {
        $typeInput = $data['type'] ?? null;
        $type = is_string($typeInput)
            ? TransactionType::tryFrom($typeInput)
            : TransactionType::Expense;

        if ($type === null) {
            throw ValidationException::withMessages([
                'type' => 'Tipo de transação inválido.',
            ]);
        }

        $isPaid = ! empty($data['is_paid']);

        $transactionData = $this->buildCreatePayload($workspace, $creator, $data, $type, $isPaid);

        $transaction = Transaction::create($transactionData);

        $this->syncTagsFromArray($transaction, $data['tags'] ?? null);

        $this->recalculateAfterCreation($transaction, $isPaid);

        return $transaction;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildCreatePayload(Workspace $workspace, User $creator, array $data, TransactionType $type, bool $isPaid): array
    {
        $transactionData = [
            'uuid' => Str::orderedUuid()->toString(),
            'workspace_id' => $workspace->id,
            'created_by' => $creator->id,
            'account_id' => $this->resolveAccountId($workspace, self::optionalString($data['account_id'] ?? null)),
            'category_id' => Category::where('uuid', is_string($data['category_id']) ? $data['category_id'] : '')
                ->where('workspace_id', $workspace->id)
                ->firstOrFail()
                ->id,
            'expense_group_id' => $this->resolveExpenseGroupId($workspace, self::optionalString($data['expense_group_id'] ?? null)),
            'type' => $type->value,
            'description' => $data['description'],
            'value' => $data['value'],
            'date' => $data['date'],
            'paid_at' => $isPaid ? now() : null,
        ];

        if ($type === TransactionType::Income) {
            $transactionData['credit_card_id'] = null;
            $transactionData['credit_card_bill_id'] = null;
            $transactionData['installment_number'] = null;
            $transactionData['installments_total'] = null;
            $transactionData['installment_group_id'] = null;
        }

        return $transactionData;
    }

    private static function optionalString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function syncTagsFromArray(Transaction $transaction, mixed $tags): void
    {
        if (is_array($tags) && $tags !== []) {
            $this->syncTags($transaction, self::stringUuidList($tags));
        }
    }

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

    private function recalculateAfterCreation(Transaction $transaction, bool $isPaid): void
    {
        if (! $isPaid || $transaction->account_id === null) {
            return;
        }

        $account = Account::find($transaction->account_id);
        if ($account) {
            $this->accountService->recalculateBalance($account);
        }
    }

    /**
     * @return array{total: float, paid: float, pending: float}
     */
    public function monthlyTotals(Workspace $workspace, ?string $month): array
    {
        return $this->monthlyTotalsFor($workspace, $month, TransactionType::Expense);
    }

    /**
     * @return array{total: float, paid: float, pending: float}
     */
    public function monthlyIncomeTotals(Workspace $workspace, ?string $month): array
    {
        return $this->monthlyTotalsFor($workspace, $month, TransactionType::Income);
    }

    /**
     * @return array{total: float, paid: float, pending: float}
     */
    private function monthlyTotalsFor(Workspace $workspace, ?string $month, TransactionType $type): array
    {
        $query = $workspace->transactions()
            ->where('type', $type);

        $month = is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month)
            ? $month
            : now()->format('Y-m');

        [$year, $monthNum] = explode('-', $month);
        $query->whereYear('date', (int) $year)
            ->whereMonth('date', (int) $monthNum);

        return [
            'total' => round((float) (clone $query)->sum('value'), 2),
            'paid' => round((float) (clone $query)->whereNotNull('paid_at')->sum('value'), 2),
            'pending' => round((float) (clone $query)->whereNull('paid_at')->sum('value'), 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Transaction $transaction, array $data): Transaction
    {
        $oldAccountId = $transaction->account_id;
        $wasPaid = $transaction->paid_at !== null;
        $oldValue = (float) $transaction->value;

        if (array_key_exists('account_id', $data) && is_string($data['account_id'])) {
            $data['account_id'] = Account::where('uuid', $data['account_id'])
                ->where('workspace_id', $transaction->workspace_id)
                ->firstOrFail()
                ->id;
        }

        if (isset($data['category_id']) && is_string($data['category_id'])) {
            $data['category_id'] = Category::where('uuid', $data['category_id'])
                ->where('workspace_id', $transaction->workspace_id)
                ->firstOrFail()
                ->id;
        }

        $this->applyUpdateFields($transaction, $data);

        $this->applyExpenseGroupUpdateFromModel($transaction, $data);

        if (array_key_exists('paid_at', $data)) {
            $transaction->paid_at = $data['paid_at'] ? now() : null;
        }

        $transaction->save();

        $this->syncTagsFromArray($transaction, $data['tags'] ?? null);

        $this->recalculateAfterUpdate($transaction, $wasPaid, $oldValue, $oldAccountId, $data);

        return $transaction;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyUpdateFields(Transaction $transaction, array $data): void
    {
        $this->applyUpdateScalarFields($transaction, $data);
        $this->applyUpdateRelationFields($transaction, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyUpdateScalarFields(Transaction $transaction, array $data): void
    {
        if (array_key_exists('description', $data) && is_string($data['description'])) {
            $transaction->description = $data['description'];
        }

        if (array_key_exists('value', $data) && is_numeric($data['value'])) {
            $transaction->value = (float) $data['value'];
        }

        if (array_key_exists('date', $data) && is_string($data['date'])) {
            $transaction->date = Carbon::parse($data['date']);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyUpdateRelationFields(Transaction $transaction, array $data): void
    {
        if (array_key_exists('account_id', $data) && (is_int($data['account_id']) || $data['account_id'] === null)) {
            $transaction->account_id = $data['account_id'];
        }

        if (array_key_exists('category_id', $data) && is_int($data['category_id'])) {
            $transaction->category_id = $data['category_id'];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyExpenseGroupUpdateFromModel(Transaction $transaction, array $data): void
    {
        $workspace = $transaction->workspace;

        if ($workspace instanceof Workspace) {
            $this->applyExpenseGroupUpdate($transaction, $workspace, $data);
        }
    }

    public function pay(Transaction $transaction): void
    {
        if ($transaction->credit_card_id !== null) {
            throw ValidationException::withMessages([
                'transaction' => 'Despesas de cartão são pagas através da fatura do cartão.',
            ]);
        }

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
        if ($transaction->credit_card_id !== null) {
            throw ValidationException::withMessages([
                'transaction' => 'Despesas de cartão são pagas através da fatura do cartão.',
            ]);
        }

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

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCardSingle(Transaction $transaction, array $data): void
    {
        $bill = $transaction->bill;

        if ($bill !== null) {
            $this->ensureBillNotPaid($bill);
        }

        DB::transaction(function () use ($transaction, $data) {
            $card = $this->resolveCardContext($transaction);
            $workspace = $transaction->workspace;

            if (! $card instanceof CreditCard || ! $workspace instanceof Workspace) {
                throw ValidationException::withMessages([
                    'transaction' => 'Despesa de cartão inválida.',
                ]);
            }

            $oldBillId = $transaction->credit_card_bill_id;

            $this->applyCardSingleFields($transaction, $card, $workspace, $data);

            $transaction->save();

            $this->syncTags($transaction, self::stringUuidList($data['tags'] ?? []));

            $this->recalculateBillsAfterCardMove($oldBillId, $transaction->credit_card_bill_id);
            $this->recalculateCardLimit($card);
        });
    }

    private function resolveCardContext(Transaction $transaction): ?CreditCard
    {
        $card = $transaction->creditCard;

        return $card instanceof CreditCard ? $card : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyCardSingleFields(Transaction $transaction, CreditCard $card, Workspace $workspace, array $data): void
    {
        if (isset($data['category_id']) && is_string($data['category_id'])) {
            $transaction->category_id = $this->resolveCategoryId($workspace, $data['category_id']);
        }
        $this->applyExpenseGroupUpdate($transaction, $workspace, $data);
        if (isset($data['description']) && is_string($data['description'])) {
            $transaction->description = $data['description'];
        }
        if (isset($data['value']) && is_numeric($data['value'])) {
            $transaction->value = (float) $data['value'];
        }
        if (isset($data['date']) && is_string($data['date'])) {
            $transaction->date = Carbon::parse($data['date']);
            $newBill = $this->resolveBill($card, Carbon::parse($data['date']), $transaction->created_by);
            $transaction->credit_card_bill_id = $newBill->id;
        }
    }

    private function recalculateBillsAfterCardMove(?int $oldBillId, ?int $newBillId): void
    {
        if ($oldBillId !== $newBillId) {
            $oldBill = CreditCardBill::find($oldBillId);
            if ($oldBill instanceof CreditCardBill) {
                $this->billService->recalculateBillTotal($oldBill);
            }
        }

        $currentBill = CreditCardBill::find($newBillId);

        if ($currentBill instanceof CreditCardBill) {
            $this->billService->recalculateBillTotal($currentBill);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCardGroup(Transaction $installment, array $data): void
    {
        $bill = $installment->bill;

        if ($bill !== null) {
            $this->ensureBillNotPaid($bill);
        }

        DB::transaction(function () use ($installment, $data) {
            $card = $installment->creditCard;
            $workspace = $installment->workspace;

            if (! $card instanceof CreditCard || ! $workspace instanceof Workspace) {
                throw ValidationException::withMessages([
                    'transaction' => 'Despesa de cartão inválida.',
                ]);
            }
            $affectedBills = collect([$installment->credit_card_bill_id]);

            $group = Transaction::where('installment_group_id', $installment->installment_group_id)
                ->where('installment_number', '>=', $installment->installment_number)
                ->whereNull('deleted_at')
                ->get();

            foreach ($group as $row) {
                $this->applyCardGroupFields($row, $installment, $card, $workspace, $data, $affectedBills);
                $row->save();

                $this->syncTags($row, self::stringUuidList($data['tags'] ?? []));
            }

            $this->recalculateBills($affectedBills->unique()->all());
            $this->recalculateCardLimit($card);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  Collection<int, int|null>  $affectedBills
     */
    private function applyCardGroupFields(Transaction $row, Transaction $installment, CreditCard $card, Workspace $workspace, array $data, Collection $affectedBills): void
    {
        if (isset($data['description']) && is_string($data['description'])) {
            $total = (int) ($row->installments_total ?? 1);
            $base = preg_replace('/ \(\d+\/\d+\)$/u', '', $data['description']) ?? '';

            $row->description = $total > 1
                ? $this->installmentDescription($base, (int) ($row->installment_number ?? 1), $total)
                : $base;
        }
        if (isset($data['category_id']) && is_string($data['category_id'])) {
            $row->category_id = $this->resolveCategoryId($workspace, $data['category_id']);
        }
        $this->applyExpenseGroupUpdate($row, $workspace, $data);
        if (isset($data['date']) && is_string($data['date']) && (int) $row->installment_number === (int) $installment->installment_number) {
            $row->date = Carbon::parse($data['date']);
            $newBill = $this->resolveBill($card, Carbon::parse($data['date']), $installment->created_by);
            $affectedBills->push($row->credit_card_bill_id);
            $row->credit_card_bill_id = $newBill->id;
        }
    }

    /**
     * @param  array<int, int|null>  $billIds
     */
    private function recalculateBills(array $billIds): void
    {
        foreach ($billIds as $billId) {
            $bill = CreditCardBill::find($billId);

            if ($bill instanceof CreditCardBill) {
                $this->billService->recalculateBillTotal($bill);
            }
        }
    }

    public function deleteCardSingle(Transaction $transaction): void
    {
        $bill = $transaction->bill;

        if ($bill !== null) {
            $this->ensureBillNotPaid($bill);
        }

        DB::transaction(function () use ($transaction) {
            $card = $transaction->creditCard;
            $bill = $transaction->bill;

            $transaction->delete();

            if ($bill instanceof CreditCardBill) {
                $this->billService->recalculateBillTotal($bill);
            }
            $this->recalculateCardLimit($card);
        });
    }

    public function deleteCardGroup(Transaction $installment): void
    {
        $bill = $installment->bill;

        if ($bill !== null) {
            $this->ensureBillNotPaid($bill);
        }

        DB::transaction(function () use ($installment) {
            $card = $installment->creditCard;
            $affectedBills = collect([$installment->credit_card_bill_id]);

            $group = Transaction::where('installment_group_id', $installment->installment_group_id)
                ->where('installment_number', '>=', $installment->installment_number)
                ->whereNull('deleted_at')
                ->get();

            foreach ($group as $row) {
                $affectedBills->push($row->credit_card_bill_id);
                $row->delete();
            }

            foreach ($affectedBills->unique() as $billId) {
                $bill = CreditCardBill::find($billId);

                if ($bill instanceof CreditCardBill) {
                    $this->billService->recalculateBillTotal($bill);
                }
            }

            $this->recalculateCardLimit($card);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createCardExpense(Workspace $workspace, User $creator, array $data): Transaction
    {
        return DB::transaction(function () use ($workspace, $creator, $data) {
            $card = CreditCard::where('uuid', is_string($data['credit_card_id']) ? $data['credit_card_id'] : '')
                ->where('workspace_id', $workspace->id)
                ->firstOrFail();

            $categoryId = $this->resolveCategoryId($workspace, is_string($data['category_id']) ? $data['category_id'] : '');
            $date = Carbon::parse(is_string($data['date']) ? $data['date'] : 'now');
            $bill = $this->resolveBill($card, $date, $creator->id);

            $transaction = Transaction::create([
                'uuid' => Str::orderedUuid()->toString(),
                'workspace_id' => $workspace->id,
                'account_id' => null,
                'credit_card_id' => $card->id,
                'credit_card_bill_id' => $bill->id,
                'category_id' => $categoryId,
                'expense_group_id' => $this->resolveExpenseGroupId($workspace, is_string($data['expense_group_id'] ?? null) ? $data['expense_group_id'] : null),
                'type' => 'expense',
                'description' => $data['description'],
                'value' => $data['value'],
                'date' => $data['date'],
                'paid_at' => null,
                'created_by' => $creator->id,
            ]);

            $this->syncTags($transaction, self::stringUuidList($data['tags'] ?? []));

            $this->billService->recalculateBillTotal($bill);
            $this->recalculateCardLimit($card);

            return $transaction;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createCardInstallment(Workspace $workspace, User $creator, array $data): string
    {
        $groupId = Str::orderedUuid()->toString();
        $totalValue = self::installmentTotalValue($data);
        $count = self::installmentCount($data);
        $baseValue = round($totalValue / $count, 2);
        $remainder = round($totalValue - ($baseValue * $count), 2);
        $firstDate = Carbon::parse(self::installmentDateString($data));
        $categoryId = $this->resolveCategoryId($workspace, is_string($data['category_id']) ? $data['category_id'] : '');

        DB::transaction(function () use ($workspace, $creator, $data, $groupId, $baseValue, $remainder, $count, $firstDate, $categoryId) {
            $card = CreditCard::where('uuid', is_string($data['credit_card_id']) ? $data['credit_card_id'] : '')
                ->where('workspace_id', $workspace->id)
                ->firstOrFail();

            $installmentDay = self::installmentDay($data);

            for ($i = 1; $i <= $count; $i++) {
                $installmentDate = self::installmentDate($firstDate, $installmentDay, $i);

                $value = $i === $count ? $baseValue + $remainder : $baseValue;
                $bill = $this->resolveBill($card, $installmentDate, $creator->id);

                $transaction = Transaction::create([
                    'uuid' => Str::orderedUuid()->toString(),
                    'workspace_id' => $workspace->id,
                    'account_id' => null,
                    'credit_card_id' => $card->id,
                    'credit_card_bill_id' => $bill->id,
                    'category_id' => $categoryId,
                    'expense_group_id' => $this->resolveExpenseGroupId($workspace, self::optionalString($data['expense_group_id'] ?? null)),
                    'type' => 'expense',
                    'description' => $this->installmentDescription(self::installmentDescriptionText($data), $i, $count),
                    'value' => $value,
                    'date' => $installmentDate->toDateString(),
                    'installment_number' => $i,
                    'installments_total' => $count,
                    'installment_group_id' => $groupId,
                    'paid_at' => null,
                    'created_by' => $creator->id,
                ]);

                $this->syncTags($transaction, self::stringUuidList($data['tags'] ?? []));

                $this->billService->recalculateBillTotal($bill);
            }

            $this->recalculateCardLimit($card);
        });

        return $groupId;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createAccountInstallment(Workspace $workspace, User $creator, array $data): string
    {
        $groupId = Str::orderedUuid()->toString();
        $totalValue = self::installmentTotalValue($data);
        $count = self::installmentCount($data);
        $baseValue = round($totalValue / $count, 2);
        $remainder = round($totalValue - ($baseValue * $count), 2);
        $firstDate = Carbon::parse(self::installmentDateString($data));
        $categoryId = $this->resolveCategoryId($workspace, is_string($data['category_id']) ? $data['category_id'] : '');

        DB::transaction(function () use ($workspace, $creator, $data, $groupId, $baseValue, $remainder, $count, $firstDate, $categoryId) {
            $accountId = $this->resolveAccountId($workspace, self::optionalString($data['account_id'] ?? null));

            $installmentDay = self::installmentDay($data);

            for ($i = 1; $i <= $count; $i++) {
                $installmentDate = self::installmentDate($firstDate, $installmentDay, $i);

                $value = $i === $count ? $baseValue + $remainder : $baseValue;

                $transaction = Transaction::create([
                    'uuid' => Str::orderedUuid()->toString(),
                    'workspace_id' => $workspace->id,
                    'account_id' => $accountId,
                    'credit_card_id' => null,
                    'credit_card_bill_id' => null,
                    'category_id' => $categoryId,
                    'expense_group_id' => $this->resolveExpenseGroupId($workspace, self::optionalString($data['expense_group_id'] ?? null)),
                    'type' => 'expense',
                    'description' => $this->installmentDescription(self::installmentDescriptionText($data), $i, $count),
                    'value' => $value,
                    'date' => $installmentDate->toDateString(),
                    'installment_number' => $i,
                    'installments_total' => $count,
                    'installment_group_id' => $groupId,
                    'paid_at' => null,
                    'created_by' => $creator->id,
                ]);

                $this->syncTags($transaction, self::stringUuidList($data['tags'] ?? []));
            }
        });

        return $groupId;
    }

    /**
     * @param  array<int, string>  $tagUuids
     */
    public function syncTags(Transaction $transaction, array $tagUuids): void
    {
        if (empty($tagUuids)) {
            return;
        }

        $tagIds = Tag::whereIn('uuid', $tagUuids)
            ->where('workspace_id', $transaction->workspace_id)
            ->pluck('id')
            ->toArray();

        $transaction->tags()->sync($tagIds);
    }

    protected function resolveBill(CreditCard $card, Carbon $date, int $createdBy): CreditCardBill
    {
        return $this->billService->findOrCreateBill($card, $date, $createdBy);
    }

    protected function ensureBillNotPaid(CreditCardBill $bill): void
    {
        if ($bill->status === BillStatus::Paid) {
            throw ValidationException::withMessages([
                'bill' => 'Não é possível alterar uma despesa de fatura já paga.',
            ]);
        }
    }

    private function resolveCategoryId(Workspace $workspace, string $uuid): int
    {
        return Category::where('uuid', $uuid)
            ->where('workspace_id', $workspace->id)
            ->firstOrFail()
            ->id;
    }

    private function resolveExpenseGroupId(Workspace $workspace, ?string $uuid): ?int
    {
        if (empty($uuid)) {
            return null;
        }

        return ExpenseGroup::where('uuid', $uuid)
            ->where('workspace_id', $workspace->id)
            ->first()?->id;
    }

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
     * @return list<string>
     */
    private static function stringUuidList(mixed $values): array
    {
        $uuids = [];

        if (is_array($values)) {
            foreach ($values as $value) {
                if (is_string($value)) {
                    $uuids[] = $value;
                }
            }
        }

        return $uuids;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyExpenseGroupUpdate(Transaction $transaction, Workspace $workspace, array $data): void
    {
        if (array_key_exists('expense_group_id', $data)) {
            $transaction->expense_group_id = $this->resolveExpenseGroupId(
                $workspace,
                is_string($data['expense_group_id']) ? $data['expense_group_id'] : null,
            );
        }
    }

    /**
     * @param  Builder<Transaction>  $query
     * @return array{total: float, paid: float, pending: float}
     */
    public function totalsForQuery(Builder $query): array
    {
        return [
            'total' => round((float) (clone $query)->sum('value'), 2),
            'paid' => round((float) (clone $query)->whereNotNull('paid_at')->sum('value'), 2),
            'pending' => round((float) (clone $query)->whereNull('paid_at')->sum('value'), 2),
        ];
    }

    private function installmentDescription(string $base, int $number, int $total): string
    {
        $suffix = " ({$number}/{$total})";
        $maxBaseLen = 255 - mb_strlen($suffix);

        return rtrim(mb_substr($base, 0, $maxBaseLen)).$suffix;
    }

    private static function installmentDate(Carbon $firstDate, ?int $installmentDay, int $index): Carbon
    {
        $installmentDate = $firstDate->copy()->addMonthsNoOverflow($index - 1);

        if ($installmentDay !== null) {
            $installmentDate->day = min($installmentDay, $installmentDate->daysInMonth);
        }

        return $installmentDate;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function installmentDay(array $data): ?int
    {
        return isset($data['installment_day']) && is_numeric($data['installment_day'])
            ? (int) $data['installment_day']
            : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function installmentTotalValue(array $data): float
    {
        $value = $data['total_value'] ?? null;

        return is_numeric($value) ? (float) $value : 0.0;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function installmentCount(array $data): int
    {
        $value = $data['installments'] ?? null;

        return is_numeric($value) ? (int) $value : 1;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function installmentDateString(array $data): string
    {
        return is_string($data['date']) ? $data['date'] : 'now';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function installmentDescriptionText(array $data): string
    {
        return is_string($data['description']) ? $data['description'] : '';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function recalculateAfterUpdate(Transaction $transaction, bool $wasPaid, float $oldValue, ?int $oldAccountId, array $data): void
    {
        $isNowPaid = $transaction->paid_at !== null;

        if (! $isNowPaid) {
            if ($wasPaid) {
                $this->recalculateCurrentAccount($transaction);
            }

            return;
        }

        if ($this->paidTransactionChanged($oldValue, $oldAccountId, $data)) {
            $this->recalculateAffectedAccounts($transaction, $oldAccountId);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function paidTransactionChanged(float $oldValue, ?int $oldAccountId, array $data): bool
    {
        $valueChanged = isset($data['value']) && is_numeric($data['value']) && (float) $data['value'] !== $oldValue;
        $accountChanged = array_key_exists('account_id', $data) && $data['account_id'] !== $oldAccountId;

        return $valueChanged || $accountChanged;
    }

    private function recalculateCurrentAccount(Transaction $transaction): void
    {
        if ($transaction->account) {
            $this->accountService->recalculateBalance($transaction->account);
        }
    }

    private function recalculateAffectedAccounts(Transaction $transaction, ?int $oldAccountId): void
    {
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
