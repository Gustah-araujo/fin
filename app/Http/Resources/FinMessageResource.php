<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'content' => $this->content,
            'attachments' => $this->attachments(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /**
     * Expose only the frontend-safe attachment metadata.
     *
     * @return list<array{name: string, kind: string}>
     */
    private function attachments(): array
    {
        return array_map(
            fn (array $attachment): array => [
                'name' => (string) ($attachment['name'] ?? ''),
                'kind' => str_ends_with((string) ($attachment['type'] ?? ''), '-image') ? 'image' : 'document',
            ],
            (array) $this->resource->attachments,
        );
    }
}
