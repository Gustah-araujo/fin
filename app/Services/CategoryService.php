<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoryService
{
    private const DEFAULT_CATEGORY = 'Sem Categoria';

    public function create(Workspace $workspace, User $creator, array $data): Category
    {
        return Category::create([
            'uuid' => Str::orderedUuid()->toString(),
            'workspace_id' => $workspace->id,
            'created_by' => $creator->id,
            'name' => $data['name'],
            'type' => $data['type'],
            'color' => $this->normalizeColor($data['color']),
            'icon' => $data['icon'] ?? null,
            'position' => $data['position'] ?? null,
        ]);
    }

    public function update(Category $category, array $data): Category
    {
        if (isset($data['name'])) {
            $category->name = $data['name'];
        }
        if (isset($data['type'])) {
            $category->type = $data['type'];
        }
        if (isset($data['color'])) {
            $category->color = $this->normalizeColor($data['color']);
        }
        if (array_key_exists('icon', $data)) {
            $category->icon = $data['icon'] ?: null;
        }
        if (array_key_exists('position', $data)) {
            $category->position = $data['position'];
        }

        $category->save();

        return $category;
    }

    public function archive(Category $category): void
    {
        if ($category->is_system) {
            throw ValidationException::withMessages([
                'category' => ['Esta categoria é gerenciada pelo sistema e não pode ser excluída.'],
            ]);
        }

        if ($category->name === self::DEFAULT_CATEGORY) {
            throw ValidationException::withMessages([
                'category' => ['A categoria padrão não pode ser excluída.'],
            ]);
        }

        $category->delete();
    }

    public function ensureDefaultExists(Workspace $workspace): Category
    {
        $category = Category::withTrashed()->where('workspace_id', $workspace->id)
            ->where('name', self::DEFAULT_CATEGORY)
            ->first();

        if ($category) {
            if ($category->trashed()) {
                $category->restore();
            }

            return $category;
        }

        return Category::create([
            'uuid' => Str::orderedUuid()->toString(),
            'workspace_id' => $workspace->id,
            'name' => self::DEFAULT_CATEGORY,
            'type' => 'both',
            'color' => '#9CA3AF',
            'icon' => 'folder',
            'position' => 0,
        ]);
    }

    private function normalizeColor(string $color): string
    {
        $color = trim($color);

        if (! str_starts_with($color, '#')) {
            $color = '#' . $color;
        }

        return strtoupper($color);
    }
}
