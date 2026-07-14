<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'credit_limit' => (float) $this->credit_limit,
            'available_limit' => (float) $this->available_limit,
            'closing_day' => $this->closing_day,
            'due_day' => $this->due_day,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
