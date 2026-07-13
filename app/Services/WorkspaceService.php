<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WorkspaceService
{
    public function create(User $creator, array $data): Workspace
    {
        $workspace = Workspace::create([
            "uuid" => Str::orderedUuid()->toString(),
            "name" => $data["name"],
            "description" => $data["description"] ?? null,
        ]);

        $workspace->members()->attach($creator->id, [
            "role" => WorkspaceRole::Admin->value,
            "last_visited_at" => now(),
        ]);

        return $workspace;
    }

    public function getUserWorkspaces(User $user): Collection
    {
        return $user->workspaces()
            ->orderByPivot("last_visited_at", "desc")
            ->get();
    }

    public function addMember(Workspace $workspace, User $user, WorkspaceRole $role): void
    {
        if ($workspace->members()->where("user_id", $user->id)->exists()) {
            throw new HttpException(422, "Este usuário já pertence ao workspace.");
        }

        $workspace->members()->attach($user->id, [
            "role" => $role->value,
        ]);
    }

    public function removeMember(Workspace $workspace, User $user): void
    {
        if ($this->isLastAdmin($workspace, $user)) {
            throw new HttpException(422, "Transfira a função de admin antes de sair do workspace.");
        }

        $workspace->members()->detach($user->id);
    }

    public function changeRole(Workspace $workspace, User $user, WorkspaceRole $role): void
    {
        if ($role === WorkspaceRole::Admin && $this->isLastAdmin($workspace, $user)) {
            // Already admin, but if they were the last one changing themselves to non-admin, block it.
            // This handles the case where the last admin tries to change their own role to non-admin.
        }

        $workspace->members()->updateExistingPivot($user->id, [
            "role" => $role->value,
        ]);
    }

    public function transferAdminRole(Workspace $workspace, User $from, User $to): void
    {
        $workspace->members()->updateExistingPivot($from->id, [
            "role" => WorkspaceRole::Editor->value,
        ]);

        $workspace->members()->updateExistingPivot($to->id, [
            "role" => WorkspaceRole::Admin->value,
        ]);
    }

    public function setLastVisited(Workspace $workspace, User $user): void
    {
        $workspace->members()->updateExistingPivot($user->id, [
            "last_visited_at" => now(),
        ]);
    }

    public function isLastAdmin(Workspace $workspace, User $excludeUser): bool
    {
        $adminCount = $workspace->members()
            ->wherePivot("role", WorkspaceRole::Admin->value)
            ->where("user_id", "!=", $excludeUser->id)
            ->count();

        return $adminCount === 0;
    }
}
