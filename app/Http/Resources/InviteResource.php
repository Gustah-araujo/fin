<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\InviteStatus;
use App\Enums\WorkspaceRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $uuid
 * @property string $email
 * @property WorkspaceRole $role
 * @property InviteStatus $status
 */
class InviteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'email' => $this->email,
            'role' => $this->role->value,
            'status' => $this->status->value,
            'inviter' => new UserResource($this->whenLoaded('inviter')),
            'workspace' => new WorkspaceResource($this->whenLoaded('workspace')),
        ];
    }
}
