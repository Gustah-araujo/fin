<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
use App\Http\Requests\UpdateMemberRoleRequest;
use App\Http\Resources\InviteResource;
use App\Http\Resources\MemberResource;
use App\Http\Resources\WorkspaceResource;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceService;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;

class WorkspaceMemberController extends Controller
{
    public function index(Workspace $workspace): Response
    {
        Gate::authorize('viewMembers', $workspace);

        return inertia('Workspace/Members', [
            'members' => MemberResource::collection(
                $workspace->members()->withPivot('role', 'created_at')->get()
            ),
            'invites' => InviteResource::collection(
                $workspace->invites()->with('inviter')->get()
            ),
            'workspace' => new WorkspaceResource($workspace),
        ]);
    }

    public function destroy(Workspace $workspace, User $user, WorkspaceService $workspaceService): RedirectResponse
    {
        Gate::authorize('manageMembers', $workspace);
        $workspaceService->removeMember($workspace, $user);

        Toast::success('Membro removido do workspace.');

        return back();
    }

    public function updateRole(UpdateMemberRoleRequest $request, Workspace $workspace, User $user, WorkspaceService $workspaceService): RedirectResponse
    {
        Gate::authorize('manageMembers', $workspace);
        $workspaceService->changeRole($workspace, $user, WorkspaceRole::from($request->validated()['role']));

        Toast::success('Papel atualizado com sucesso.');

        return back();
    }
}
