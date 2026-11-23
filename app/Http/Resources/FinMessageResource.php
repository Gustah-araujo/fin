<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Ai\Models\ConversationMessage;

/**
 * @property ConversationMessage $resource
 * @property string $id
 * @property string $role
 * @property string|null $content
 * @property Carbon|null $created_at
 */
class FinMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
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
        $attachments = [];

        foreach ((array) ($this->resource->attachments ?? []) as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }

            $name = $attachment['name'] ?? '';
            $type = $attachment['type'] ?? '';

            $attachments[] = [
                'name' => is_scalar($name) ? (string) $name : '',
                'kind' => is_string($type) && str_ends_with($type, '-image') ? 'image' : 'document',
            ];
        }

        return $attachments;
    }
}
