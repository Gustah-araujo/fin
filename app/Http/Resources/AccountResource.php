<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            "uuid" => $this->uuid,
            "name" => $this->name,
            "type" => $this->type,
            "initial_balance" => (float) $this->initial_balance,
            "current_balance" => (float) $this->current_balance,
            "workspace" => $this->whenLoaded("workspace", fn () => new WorkspaceResource($this->workspace)),
            "created_at" => $this->created_at?->toISOString(),
        ];
    }
}
