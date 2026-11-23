<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\ExpenseGroup;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoryService
{
    private const DEFAULT_CATEGORY = 'Sem Categoria';

    public function ensureSystemCategory(Workspace $workspace, string $name, TransactionType $type): int
    {
        $category = Category::where('workspace_id', $workspace->id)
            ->where('name', $name)
            ->where('is_system', true)
            ->first();

        if ($category) {
            return $category->id;
        }

        return Category::create([
            'uuid' => Str::orderedUuid()->toString(),
            'workspace_id' => $workspace->id,
            'name' => $name,
            'type' => $type->value,
            'color' => '#6B7280',
            'icon' => 'arrow-left-right',
            'is_system' => true,
            'position' => 999,
        ])->id;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Workspace $workspace, User $creator, array $data): Category
    {
        $color = $data['color'];
        $expenseGroupUuid = $data['expense_group_id'] ?? null;

        return Category::create([
            'uuid' => Str::orderedUuid()->toString(),
            'workspace_id' => $workspace->id,
            'created_by' => $creator->id,
            'name' => $data['name'],
            'type' => $data['type'],
            'color' => $this->normalizeColor(is_string($color) ? $color : ''),
            'icon' => $data['icon'] ?? null,
            'position' => $data['position'] ?? null,
            'expense_group_id' => $this->resolveExpenseGroupId($workspace->id, is_string($expenseGroupUuid) ? $expenseGroupUuid : null),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Category $category, array $data): Category
    {
        $this->applyCategoryBasics($category, $data);
        $this->applyCategoryPlacement($category, $data);

        $category->save();

        return $category;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyCategoryBasics(Category $category, array $data): void
    {
        if (isset($data['name']) && is_string($data['name'])) {
            $category->name = $data['name'];
        }
        if (isset($data['type']) && is_string($data['type'])) {
            $category->type = TransactionType::from($data['type']);
        }
        if (isset($data['color']) && is_string($data['color'])) {
            $category->color = $this->normalizeColor($data['color']);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyCategoryPlacement(Category $category, array $data): void
    {
        if (array_key_exists('icon', $data)) {
            $category->icon = (is_string($data['icon']) && $data['icon'] !== '') ? $data['icon'] : null;
        }
        if (array_key_exists('position', $data)) {
            $category->position = is_numeric($data['position']) ? (int) $data['position'] : null;
        }
        if (array_key_exists('expense_group_id', $data)) {
            $category->expense_group_id = $this->resolveExpenseGroupId(
                $category->workspace_id,
                is_string($data['expense_group_id']) ? $data['expense_group_id'] : null,
            );
        }
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

    private function resolveExpenseGroupId(int $workspaceId, ?string $uuid): ?int
    {
        if (empty($uuid)) {
            return null;
        }

        return ExpenseGroup::where('uuid', $uuid)
            ->where('workspace_id', $workspaceId)
            ->first()?->id;
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
