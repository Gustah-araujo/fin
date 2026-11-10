<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'status' => $this->status->value,
            'error_message' => $this->error_message,
            'result_message_id' => $this->result_message_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
