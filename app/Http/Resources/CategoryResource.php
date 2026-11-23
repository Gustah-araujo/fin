<?php

namespace App\Http\Resources;

use App\Enums\TransactionType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $uuid
 * @property string $name
 * @property TransactionType $type
 * @property string $color
 * @property string|null $icon
 * @property int|null $position
 * @property bool $is_system
 * @property Carbon|null $created_at
 */
class CategoryResource extends JsonResource
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
            'color' => $this->color,
            'icon' => $this->icon,
            'position' => $this->position,
            'is_system' => $this->is_system,
            'workspace' => new WorkspaceResource($this->whenLoaded('workspace')),
            'expense_group' => new ExpenseGroupResource($this->whenLoaded('expenseGroup')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
