<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\BillStatus;
use App\Models\CreditCard;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $uuid
 * @property int $period_year
 * @property int $period_month
 * @property CreditCard $creditCard
 * @property Carbon $closing_date
 * @property Carbon $due_date
 * @property BillStatus $status
 * @property int|float|string $total_amount
 * @property Carbon|null $closed_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 */
class CreditCardBillResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'period_year' => $this->period_year,
            'period_month' => $this->period_month,
            'period_label' => str_pad((string) $this->period_month, 2, '0', STR_PAD_LEFT).'/'.$this->period_year,
            'credit_card_name' => $this->creditCard->name,
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
