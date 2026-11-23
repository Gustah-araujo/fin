<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $uuid
 * @property string $name
 * @property string|null $description
 * @property object{role: string} $pivot
 */
class WorkspaceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'description' => $this->when((bool) $this->description, $this->description),
            'members_count' => $this->whenCounted('members'),
            'role' => $this->whenPivotLoaded('workspace_user', function () {
                return $this->pivot->role;
            }),
        ];
    }
}
