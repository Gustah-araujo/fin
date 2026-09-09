<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
use App\Http\Requests\StoreWorkspaceRequest;
use App\Http\Resources\InviteResource;
use App\Http\Resources\MemberResource;
use App\Models\Workspace;
use App\Services\WorkspaceService;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function create(): Response
    {
        return inertia('Workspace/Create');
    }

    public function store(StoreWorkspaceRequest $request, WorkspaceService $workspaceService): RedirectResponse
    {
        $workspace = $workspaceService->create($request->user(), $request->validated());

        Toast::success('Workspace criado com sucesso.');

        return redirect()->route('dashboard', ['workspace' => $workspace->uuid]);
    }

    public function select(Request $request, WorkspaceService $workspaceService): Response
    {
        $workspaces = $workspaceService->getUserWorkspaces($request->user());

        return inertia('Workspace/Select', [
            'workspaces' => $workspaces->map(fn ($w) => [
                'uuid' => $w->uuid,
                'name' => $w->name,
                'description' => $w->description,
                'members_count' => $w->members()->count(),
                'role' => $w->pivot->role ?? 'admin',
            ])->toArray(),
        ]);
    }

    public function activate(Request $request, WorkspaceService $workspaceService): RedirectResponse
    {
        $workspaceUuid = $request->input('workspace_uuid');
        $workspace = $request->user()->workspaces()->where('uuid', $workspaceUuid)->firstOrFail();
        $workspaceService->setLastVisited($workspace, $request->user());

        Toast::success('Workspace ativado.');

        return redirect()->route('dashboard', ['workspace' => $workspace->uuid]);
    }

    public function settings(Workspace $workspace): Response
    {
        Gate::authorize('viewMembers', $workspace);

        return inertia('Workspace/Settings', [
            'members' => MemberResource::collection(
                $workspace->members()->withPivot('role', 'created_at')->get()
            ),
            'invites' => InviteResource::collection(
                $workspace->invites()->with('inviter')->get()
            ),
            'isAdmin' => $workspace->members()
                ->where('user_id', auth()->id())
                ->wherePivot('role', WorkspaceRole::Admin->value)
                ->exists(),
        ]);
    }
}
