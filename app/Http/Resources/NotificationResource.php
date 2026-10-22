<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->data['title'] ?? null,
            'message' => $this->data['message'] ?? null,
            'action_url' => $this->data['action_url'] ?? null,
            'action_text' => $this->data['action_text'] ?? null,
            'is_read' => $this->read_at !== null,
            'created_at' => $this->created_at->toISOString(),
            'read_at' => $this->read_at?->toISOString(),
        ];
    }
}
