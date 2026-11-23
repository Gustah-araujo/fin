<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\TransactionType;
use App\Models\Recurrence;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Transaction $resource
 * @property string $uuid
 * @property string $description
 * @property int|float|string $value
 * @property TransactionType $type
 * @property Carbon $date
 * @property Carbon|null $paid_at
 * @property Recurrence|null $recurrence
 * @property int|null $credit_card_id
 * @property int|null $credit_card_bill_id
 * @property int|null $installment_number
 * @property int|null $installments_total
 * @property Carbon|null $created_at
 */
class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'description' => $this->description,
            'value' => (float) $this->value,
            'type' => $this->type,
            'date' => $this->date->format('Y-m-d'),
            'paid_at' => $this->paid_at?->toISOString(),
            'is_paid' => $this->paid_at !== null,
            'recurrence_id' => $this->whenLoaded('recurrence', fn () => $this->recurrence?->uuid),
            'recurrence' => new RecurrenceResource($this->whenLoaded('recurrence')),
            'account' => new AccountResource($this->whenLoaded('account')),
            'credit_card_id' => $this->credit_card_id,
            'credit_card_bill_id' => $this->credit_card_bill_id,
            'credit_card' => new CreditCardResource($this->whenLoaded('creditCard')),
            'bill' => new CreditCardBillResource($this->whenLoaded('bill')),
            'installment_number' => $this->installment_number,
            'installments_total' => $this->installments_total,
            'is_installment' => $this->installments_total !== null && $this->installments_total > 1,
            'installment_label' => $this->installments_total !== null && $this->installments_total > 1
                ? "{$this->installment_number}/{$this->installments_total}"
                : null,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'expense_group' => new ExpenseGroupResource($this->whenLoaded('expenseGroup')),
            'effective_expense_group' => ($eg = $this->resource->effectiveExpenseGroup()) ? new ExpenseGroupResource($eg) : null,
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'created_by' => new UserResource($this->whenLoaded('creator')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
