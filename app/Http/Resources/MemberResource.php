<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            "user" => new UserResource($this->whenLoaded("user", $this)),
            "role" => $this->whenPivotLoaded("workspace_user", function () {
                return $this->pivot->role;
            }, $this->role ?? null),
            "joined_at" => $this->whenPivotLoaded("workspace_user", function () {
                return $this->pivot->created_at?->toISOString();
            }),
        ];
    }
}
