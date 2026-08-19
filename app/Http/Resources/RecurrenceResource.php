<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecurrenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'description' => $this->description,
            'value' => (float) $this->value,
            'frequency' => $this->frequency->value,
            'frequency_day' => (int) $this->frequency_day,
            'start_date' => $this->start_date->toDateString(),
            'until_date' => $this->until_date?->toDateString(),
            'next_date' => $this->next_date?->toDateString(),
            'status' => $this->status->value,
            'account' => new AccountResource($this->whenLoaded('account')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
