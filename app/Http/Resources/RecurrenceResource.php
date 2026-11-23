<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\RecurrenceFrequency;
use App\Enums\RecurrenceStatus;
use App\Enums\TransactionType;
use App\Models\Recurrence;
use App\Services\RecurrenceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Recurrence $resource
 * @property string $uuid
 * @property string $description
 * @property int|float|string $value
 * @property RecurrenceFrequency $frequency
 * @property int $frequency_day
 * @property Carbon $start_date
 * @property Carbon|null $until_date
 * @property Carbon|null $next_date
 * @property TransactionType $type
 * @property RecurrenceStatus $status
 * @property int $buffer_ahead
 * @property Carbon|null $created_at
 */
class RecurrenceResource extends JsonResource
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
            'frequency' => $this->frequency->value,
            'frequency_day' => (int) $this->frequency_day,
            'start_date' => $this->start_date->toDateString(),
            'until_date' => $this->until_date?->toDateString(),
            'next_date' => $this->next_date?->toDateString(),
            'type' => $this->type->value,
            'status' => $this->status->value,
            'buffer_ahead' => (int) $this->buffer_ahead,
            'account' => new AccountResource($this->whenLoaded('account')),
            'credit_card' => new CreditCardResource($this->whenLoaded('creditCard')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'created_at' => $this->created_at?->toISOString(),
            'period_consumed' => app(RecurrenceService::class)
                ->hasTransactionInPeriod($this->resource, Carbon::today()),
        ];
    }
}
