<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

class CreditCardService
{
    private const PAYMENT_CATEGORY_NAME = 'Pagamento de Cartão';

    public function create(Workspace $workspace, User $creator, array $data): CreditCard
    {
        return CreditCard::create([
            'uuid' => Str::orderedUuid()->toString(),
            'workspace_id' => $workspace->id,
            'created_by' => $creator->id,
            'name' => $data['name'],
            'credit_limit' => $data['credit_limit'],
            'available_limit' => $data['credit_limit'],
            'closing_day' => $data['closing_day'],
            'due_day' => $data['due_day'],
        ]);
    }

    public function update(CreditCard $card, array $data): CreditCard
    {
        if (isset($data['name'])) {
            $card->name = $data['name'];
        }
        if (isset($data['closing_day'])) {
            $card->closing_day = $data['closing_day'];
        }
        if (isset($data['due_day'])) {
            $card->due_day = $data['due_day'];
        }
        if (isset($data['credit_limit'])) {
            $card->credit_limit = $data['credit_limit'];
            $this->recalculateAvailableLimit($card);
        }

        $card->save();

        return $card;
    }

    public function recalculateAvailableLimit(CreditCard $card): void
    {
        $usedLimit = Transaction::where('transactions.credit_card_id', $card->id)
            ->leftJoin('credit_card_bills', 'transactions.credit_card_bill_id', '=', 'credit_card_bills.id')
            ->where(function ($q) {
                $q->where('credit_card_bills.status', '!=', 'paid')
                  ->orWhereNull('transactions.credit_card_bill_id');
            })
            ->sum('transactions.value');

        $card->available_limit = (float) $card->credit_limit - (float) $usedLimit;
        $card->save();
    }

    public function ensurePaymentCategory(Workspace $workspace): Category
    {
        $category = Category::where('workspace_id', $workspace->id)
            ->where('name', self::PAYMENT_CATEGORY_NAME)
            ->first();

        if ($category) {
            return $category;
        }

        return Category::create([
            'uuid' => Str::orderedUuid()->toString(),
            'workspace_id' => $workspace->id,
            'name' => self::PAYMENT_CATEGORY_NAME,
            'type' => TransactionType::Expense,
            'color' => '#6B7280',
            'is_system' => true,
        ]);
    }

    public function archive(CreditCard $card): void
    {
        $card->delete();
    }
}
