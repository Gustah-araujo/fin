<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
use App\Http\Requests\UpdateMemberRoleRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;

class WorkspaceMemberController extends Controller
{
    public function index(Workspace $workspace): Response
    {
        Gate::authorize("viewMembers", $workspace);

        $members = $workspace->members()->withPivot("role", "created_at")->get()
            ->map(fn ($user) => [
                "user" => [
                    "uuid" => $user->uuid,
                    "name" => $user->name,
                    "email" => $user->email,
                    "avatar" => $user->avatar,
                ],
                "role" => $user->pivot->role,
                "joined_at" => $user->pivot->created_at?->toISOString(),
            ])->values()->toArray();

        $invites = $workspace->invites()
            ->where("status", "pending")
            ->with("inviter")
            ->get()
            ->map(fn ($invite) => [
                "uuid" => $invite->uuid,
                "email" => $invite->email,
                "role" => $invite->role instanceof WorkspaceRole ? $invite->role->value : $invite->role,
                "status" => $invite->status instanceof \App\Enums\InviteStatus ? $invite->status->value : $invite->status,
                "inviter" => [
                    "uuid" => $invite->inviter->uuid,
                    "name" => $invite->inviter->name,
                ],
                "workspace" => [
                    "uuid" => $workspace->uuid,
                    "name" => $workspace->name,
                ],
            ])->values()->toArray();

        return inertia("Workspace/Members", [
            "members" => $members,
            "invites" => $invites,
            "workspace" => [
                "uuid" => $workspace->uuid,
                "name" => $workspace->name,
            ],
        ]);
    }

    public function destroy(Workspace $workspace, User $user, WorkspaceService $workspaceService): RedirectResponse
    {
        Gate::authorize("manageMembers", $workspace);
        $workspaceService->removeMember($workspace, $user);
        return back()->with("status", "Membro removido do workspace.");
    }

    public function updateRole(UpdateMemberRoleRequest $request, Workspace $workspace, User $user, WorkspaceService $workspaceService): RedirectResponse
    {
        Gate::authorize("manageMembers", $workspace);
        $workspaceService->changeRole($workspace, $user, WorkspaceRole::from($request->validated()["role"]));
        return back()->with("status", "Papel atualizado com sucesso.");
    }
}
