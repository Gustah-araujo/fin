<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $uuid
 * @property string $name
 * @property int|float|string $credit_limit
 * @property int|float|string $available_limit
 * @property int $closing_day
 * @property int $due_day
 * @property Carbon|null $created_at
 */
class CreditCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'credit_limit' => (float) $this->credit_limit,
            'available_limit' => (float) $this->available_limit,
            'closing_day' => $this->closing_day,
            'due_day' => $this->due_day,
            'bills' => CreditCardBillResource::collection($this->whenLoaded('bills')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
