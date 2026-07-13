<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\Category;
use App\Models\User;
use App\Models\Workspace;

class CategoryPolicy
{
    private const DEFAULT_CATEGORY_NAME = 'Sem Categoria';

    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $workspace->members()->where('user_id', $user->id)->exists();
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return $this->hasWriteAccess($user, $workspace);
    }

    public function update(User $user, Category $category, Workspace $workspace): bool
    {
        return $this->hasWriteAccess($user, $workspace);
    }

    public function delete(User $user, Category $category, Workspace $workspace): bool
    {
        if ($category->name === self::DEFAULT_CATEGORY_NAME) {
            return false;
        }

        return $this->hasWriteAccess($user, $workspace);
    }

    private function hasWriteAccess(User $user, Workspace $workspace): bool
    {
        $role = $this->getUserRole($user, $workspace);

        return $role === WorkspaceRole::Admin || $role === WorkspaceRole::Editor;
    }

    private function getUserRole(User $user, Workspace $workspace): ?WorkspaceRole
    {
        $pivot = $workspace->members()->where('user_id', $user->id)->first();

        if (! $pivot || ! $pivot->pivot->role) {
            return null;
        }

        return WorkspaceRole::from($pivot->pivot->role);
    }
}
