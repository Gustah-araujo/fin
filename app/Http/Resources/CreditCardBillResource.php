<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditCardBillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'period_year' => $this->period_year,
            'period_month' => $this->period_month,
            'period_label' => $this->period_year . '/' . str_pad((string) $this->period_month, 2, '0', STR_PAD_LEFT),
            'closing_date' => $this->closing_date->format('Y-m-d'),
            'due_date' => $this->due_date->format('Y-m-d'),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'total_amount' => (float) $this->total_amount,
            'closed_at' => $this->closed_at?->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
            'payment_account' => new AccountResource($this->whenLoaded('paymentAccount')),
            'expenses' => TransactionResource::collection($this->whenLoaded('transactions')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
