<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $uuid
 * @property string $name
 * @property string $email
 * @property string|null $avatar
 * @property string|null $role
 * @property object{role: string, created_at: Carbon|null} $pivot
 */
class MemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => new UserResource($this),
            'role' => $this->whenPivotLoaded('workspace_user', function () {
                return $this->pivot->role;
            }, $this->role ?? null),
            'joined_at' => $this->whenPivotLoaded('workspace_user', function () {
                return $this->pivot->created_at?->toISOString();
            }),
        ];
    }
}
