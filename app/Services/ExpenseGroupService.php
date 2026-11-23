<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExpenseGroup;
use App\Models\Workspace;
use Illuminate\Support\Str;

class ExpenseGroupService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Workspace $workspace, array $data): ExpenseGroup
    {
        $color = $data['color'];

        return ExpenseGroup::create([
            'uuid' => Str::orderedUuid()->toString(),
            'workspace_id' => $workspace->id,
            'name' => $data['name'],
            'color' => $this->normalizeColor(is_string($color) ? $color : ''),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ExpenseGroup $group, array $data): ExpenseGroup
    {
        if (isset($data['name']) && is_string($data['name'])) {
            $group->name = $data['name'];
        }
        if (isset($data['color']) && is_string($data['color'])) {
            $group->color = $this->normalizeColor($data['color']);
        }

        $group->save();

        return $group;
    }

    public function delete(ExpenseGroup $group): void
    {
        $group->delete();
    }

    private function normalizeColor(string $color): string
    {
        $color = trim($color);

        if (! str_starts_with($color, '#')) {
            $color = '#'.$color;
        }

        return strtoupper($color);
    }
}
