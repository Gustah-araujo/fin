<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\UserType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $uuid
 * @property string $name
 * @property string $email
 * @property UserType $type
 * @property Carbon|null $email_verified_at
 * @property Carbon $created_at
 */
class AdminUserResource extends JsonResource
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
            'type' => $this->type->value,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'workspaces_count' => $this->whenCounted('workspaces'),
        ];
    }
}
