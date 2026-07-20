<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
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
            'recurrence_id' => $this->whenLoaded('recurrence', fn () => $this->recurrence->uuid),
            'recurrence' => new RecurrenceResource($this->whenLoaded('recurrence')),
            'account' => new AccountResource($this->whenLoaded('account')),
            'credit_card' => new CreditCardResource($this->whenLoaded('creditCard')),
            'installment_number' => $this->installment_number,
            'installments_total' => $this->installments_total,
            'is_installment' => $this->installments_total !== null && $this->installments_total > 1,
            'installment_label' => $this->installments_total !== null && $this->installments_total > 1
                ? "{$this->installment_number}/{$this->installments_total}"
                : null,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'created_by' => new UserResource($this->whenLoaded('creator')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
