<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExpenseGroup;
use App\Models\Workspace;
use Illuminate\Support\Str;

class ExpenseGroupService
{
    public function create(Workspace $workspace, array $data): ExpenseGroup
    {
        return ExpenseGroup::create([
            'uuid' => Str::orderedUuid()->toString(),
            'workspace_id' => $workspace->id,
            'name' => $data['name'],
            'color' => $this->normalizeColor($data['color']),
        ]);
    }

    public function update(ExpenseGroup $group, array $data): ExpenseGroup
    {
        if (isset($data['name'])) {
            $group->name = $data['name'];
        }
        if (isset($data['color'])) {
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
