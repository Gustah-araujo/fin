<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'type' => $this->type,
            'color' => $this->color,
            'icon' => $this->icon,
            'position' => $this->position,
            'workspace' => new WorkspaceResource($this->whenLoaded('workspace')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
