<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    public function view(User $user, Workspace $workspace): bool
    {
        return $workspace->members()->where("user_id", $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function manageMembers(User $user, Workspace $workspace): bool
    {
        return $this->getUserRole($user, $workspace) === WorkspaceRole::Admin;
    }

    public function invite(User $user, Workspace $workspace): bool
    {
        return $this->getUserRole($user, $workspace) === WorkspaceRole::Admin;
    }

    public function manageTransactions(User $user, Workspace $workspace): bool
    {
        $role = $this->getUserRole($user, $workspace);
        return $role === WorkspaceRole::Admin || $role === WorkspaceRole::Editor;
    }

    public function viewMembers(User $user, Workspace $workspace): bool
    {
        return $workspace->members()->where("user_id", $user->id)->exists();
    }

    private function getUserRole(User $user, Workspace $workspace): ?WorkspaceRole
    {
        $pivot = $workspace->members()->where("user_id", $user->id)->first();
        if (! $pivot || ! $pivot->pivot->role) {
            return null;
        }
        return WorkspaceRole::from($pivot->pivot->role);
    }
}
