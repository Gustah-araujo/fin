<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BillStatus;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CardExpenseService
{
    public function __construct(
        private readonly BillService $billService,
        private readonly CreditCardService $creditCardService,
    ) {}

    public function createSingle(Workspace $workspace, User $creator, CreditCard $card, array $data): Transaction
    {
        return DB::transaction(function () use ($workspace, $creator, $card, $data) {
            $categoryId = $this->resolveCategoryId($workspace, $data['category_id']);
            $date = Carbon::parse($data['date']);
            $bill = $this->billService->findOrCreateBill($card, $date, $creator->id);

            $transaction = Transaction::create([
                'uuid' => Str::orderedUuid()->toString(),
                'workspace_id' => $workspace->id,
                'account_id' => null,
                'credit_card_id' => $card->id,
                'credit_card_bill_id' => $bill->id,
                'category_id' => $categoryId,
                'type' => 'expense',
                'description' => $data['description'],
                'value' => $data['value'],
                'date' => $data['date'],
                'paid_at' => null,
                'created_by' => $creator->id,
            ]);

            if (! empty($data['tags'])) {
                $this->syncTags($transaction, $data['tags']);
            }

            $this->billService->recalculateBillTotal($bill);
            $this->creditCardService->recalculateAvailableLimit($card->fresh());

            return $transaction;
        });
    }

    public function createInstallment(Workspace $workspace, User $creator, CreditCard $card, array $data): string
    {
        $groupId = Str::orderedUuid()->toString();
        $totalValue = (float) $data['total_value'];
        $count = (int) $data['installments'];
        $baseValue = round($totalValue / $count, 2);
        $remainder = round($totalValue - ($baseValue * $count), 2);
        $firstDate = Carbon::parse($data['date']);
        $categoryId = $this->resolveCategoryId($workspace, $data['category_id']);

        DB::transaction(function () use ($workspace, $creator, $card, $data, $groupId, $baseValue, $remainder, $count, $firstDate, $categoryId) {
            for ($i = 1; $i <= $count; $i++) {
                $installmentDate = $firstDate->copy()->addMonthsNoOverflow($i - 1);
                $value = $i === $count ? $baseValue + $remainder : $baseValue;
                $bill = $this->billService->findOrCreateBill($card, $installmentDate, $creator->id);

                $transaction = Transaction::create([
                    'uuid' => Str::orderedUuid()->toString(),
                    'workspace_id' => $workspace->id,
                    'account_id' => null,
                    'credit_card_id' => $card->id,
                    'credit_card_bill_id' => $bill->id,
                    'category_id' => $categoryId,
                    'type' => 'expense',
                    'description' => $data['description'],
                    'value' => $value,
                    'date' => $installmentDate->toDateString(),
                    'installment_number' => $i,
                    'installments_total' => $count,
                    'installment_group_id' => $groupId,
                    'paid_at' => null,
                    'created_by' => $creator->id,
                ]);

                if (! empty($data['tags'])) {
                    $this->syncTags($transaction, $data['tags']);
                }

                $this->billService->recalculateBillTotal($bill);
            }

            $this->creditCardService->recalculateAvailableLimit($card->fresh());
        });

        return $groupId;
    }

    public function updateSingle(Transaction $transaction, array $data): void
    {
        $this->ensureBillNotPaid($transaction);

        DB::transaction(function () use ($transaction, $data) {
            $oldBillId = $transaction->credit_card_bill_id;
            $card = $transaction->creditCard;

            if (isset($data['category_id'])) {
                $transaction->category_id = $this->resolveCategoryId($transaction->workspace, $data['category_id']);
            }
            if (isset($data['description'])) {
                $transaction->description = $data['description'];
            }
            if (isset($data['value'])) {
                $transaction->value = $data['value'];
            }
            if (isset($data['date'])) {
                $transaction->date = $data['date'];
                $newBill = $this->billService->findOrCreateBill($card, Carbon::parse($data['date']), $transaction->created_by);
                $transaction->credit_card_bill_id = $newBill->id;
            }

            $transaction->save();

            if (! empty($data['tags'])) {
                $this->syncTags($transaction, $data['tags']);
            }

            if ($oldBillId !== $transaction->credit_card_bill_id) {
                $oldBill = \App\Models\CreditCardBill::find($oldBillId);
                if ($oldBill) {
                    $this->billService->recalculateBillTotal($oldBill);
                }
            }
            $this->billService->recalculateBillTotal($transaction->bill);
            $this->creditCardService->recalculateAvailableLimit($card->fresh());
        });
    }

    public function updateGroup(Transaction $installment, array $data): void
    {
        $this->ensureBillNotPaid($installment);

        DB::transaction(function () use ($installment, $data) {
            $card = $installment->creditCard;
            $affectedBills = collect([$installment->credit_card_bill_id]);

            $group = Transaction::where('installment_group_id', $installment->installment_group_id)
                ->where('installment_number', '>=', $installment->installment_number)
                ->whereNull('deleted_at')
                ->get();

            foreach ($group as $row) {
                if (isset($data['description'])) {
                    $row->description = $data['description'];
                }
                if (isset($data['category_id'])) {
                    $row->category_id = $this->resolveCategoryId($installment->workspace, $data['category_id']);
                }
                if (isset($data['date']) && (int) $row->installment_number === (int) $installment->installment_number) {
                    $row->date = $data['date'];
                    $newBill = $this->billService->findOrCreateBill($card, Carbon::parse($data['date']), $installment->created_by);
                    $affectedBills->push($row->credit_card_bill_id);
                    $row->credit_card_bill_id = $newBill->id;
                }
                $row->save();

                if (! empty($data['tags'])) {
                    $this->syncTags($row, $data['tags']);
                }
            }

            foreach ($affectedBills->unique() as $billId) {
                $bill = \App\Models\CreditCardBill::find($billId);
                if ($bill) {
                    $this->billService->recalculateBillTotal($bill);
                }
            }

            $this->creditCardService->recalculateAvailableLimit($card->fresh());
        });
    }

    public function deleteSingle(Transaction $transaction): void
    {
        $this->ensureBillNotPaid($transaction);

        DB::transaction(function () use ($transaction) {
            $card = $transaction->creditCard;
            $bill = $transaction->bill;

            $transaction->delete();

            if ($bill) {
                $this->billService->recalculateBillTotal($bill);
            }
            $this->creditCardService->recalculateAvailableLimit($card->fresh());
        });
    }

    public function deleteGroup(Transaction $installment): void
    {
        $this->ensureBillNotPaid($installment);

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
                $bill = \App\Models\CreditCardBill::find($billId);
                if ($bill) {
                    $this->billService->recalculateBillTotal($bill);
                }
            }

            $this->creditCardService->recalculateAvailableLimit($card->fresh());
        });
    }

    public function resolveCategoryId(Workspace $workspace, string $uuid): int
    {
        return Category::where('uuid', $uuid)
            ->where('workspace_id', $workspace->id)
            ->firstOrFail()
            ->id;
    }

    public function syncTags(Transaction $transaction, array $tagUuids): void
    {
        $tagIds = Tag::whereIn('uuid', $tagUuids)
            ->where('workspace_id', $transaction->workspace_id)
            ->pluck('id')
            ->toArray();

        $transaction->tags()->sync($tagIds);
    }

    private function ensureBillNotPaid(Transaction $transaction): void
    {
        $bill = $transaction->bill;
        if ($bill && $bill->status === BillStatus::Paid) {
            throw ValidationException::withMessages([
                'bill' => ['Não é possível editar/excluir parcelas de faturas já pagas.'],
            ]);
        }
    }
}
