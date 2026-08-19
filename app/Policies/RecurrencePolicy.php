<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\Recurrence;
use App\Models\User;
use App\Models\Workspace;

class RecurrencePolicy
{
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $workspace->members()->where('user_id', $user->id)->exists();
    }

    public function view(User $user, Recurrence $recurrence, Workspace $workspace): bool
    {
        if (! $this->belongsToWorkspace($recurrence, $workspace)) {
            return false;
        }

        return $this->isWorkspaceMember($user, $workspace);
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return $this->hasWriteAccess($user, $workspace);
    }

    public function update(User $user, Recurrence $recurrence, Workspace $workspace): bool
    {
        if (! $this->belongsToWorkspace($recurrence, $workspace)) {
            return false;
        }

        return $this->hasWriteAccess($user, $workspace);
    }

    public function delete(User $user, Recurrence $recurrence, Workspace $workspace): bool
    {
        if (! $this->belongsToWorkspace($recurrence, $workspace)) {
            return false;
        }

        return $this->hasWriteAccess($user, $workspace);
    }

    public function pause(User $user, Recurrence $recurrence, Workspace $workspace): bool
    {
        if (! $this->belongsToWorkspace($recurrence, $workspace)) {
            return false;
        }

        return $this->hasWriteAccess($user, $workspace);
    }

    public function restore(User $user, Recurrence $recurrence, Workspace $workspace): bool
    {
        if (! $this->belongsToWorkspace($recurrence, $workspace)) {
            return false;
        }

        return $this->hasWriteAccess($user, $workspace);
    }

    public function generateNow(User $user, Recurrence $recurrence, Workspace $workspace): bool
    {
        if (! $this->belongsToWorkspace($recurrence, $workspace)) {
            return false;
        }

        return $this->hasWriteAccess($user, $workspace);
    }

    private function hasWriteAccess(User $user, Workspace $workspace): bool
    {
        $role = $this->getUserRole($user, $workspace);

        return $role === WorkspaceRole::Admin || $role === WorkspaceRole::Editor;
    }

    private function isWorkspaceMember(User $user, Workspace $workspace): bool
    {
        return $workspace->members()->where('user_id', $user->id)->exists();
    }

    private function belongsToWorkspace(Recurrence $recurrence, Workspace $workspace): bool
    {
        return $recurrence->workspace_id === $workspace->id;
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
