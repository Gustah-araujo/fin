<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InviteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            "uuid" => $this->uuid,
            "email" => $this->email,
            "role" => $this->role instanceof \App\Enums\WorkspaceRole ? $this->role->value : $this->role,
            "status" => $this->status instanceof \App\Enums\InviteStatus ? $this->status->value : $this->status,
            "inviter" => new UserResource($this->whenLoaded("inviter")),
            "workspace" => new WorkspaceResource($this->whenLoaded("workspace")),
        ];
    }
}
