<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $uuid
 * @property string $name
 * @property string $type
 * @property int|float|string $initial_balance
 * @property int|float|string $current_balance
 * @property Workspace $workspace
 * @property Carbon|null $created_at
 */
class AccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'type' => $this->type,
            'initial_balance' => (float) $this->initial_balance,
            'current_balance' => (float) $this->current_balance,
            'workspace' => $this->whenLoaded('workspace', fn () => new WorkspaceResource($this->workspace)),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
