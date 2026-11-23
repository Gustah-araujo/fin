<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $uuid
 * @property string $name
 * @property string $email
 * @property string|null $avatar
 * @property object{role: string} $pivot
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->when((bool) $this->avatar, $this->avatar),
            'workspace_role' => $this->whenPivotLoaded('workspace_user', function () {
                return $this->pivot->role;
            }),
        ];
    }
}
