<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $uuid
 * @property string $name
 * @property string $color
 * @property Carbon|null $created_at
 */
class TagResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'color' => $this->color,
            'workspace' => new WorkspaceResource($this->whenLoaded('workspace')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
