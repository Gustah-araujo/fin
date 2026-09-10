<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\RecurrenceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecurrenceResource extends JsonResource
{
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
            'category' => new CategoryResource($this->whenLoaded('category')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'created_at' => $this->created_at?->toISOString(),
            'period_consumed' => app(RecurrenceService::class)
                ->hasTransactionInPeriod($this->resource, Carbon::today()),
        ];
    }
}
