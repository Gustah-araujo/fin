<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkspaceRequest;
use App\Services\WorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function create(): Response
    {
        return inertia("Workspace/Create");
    }

    public function store(StoreWorkspaceRequest $request, WorkspaceService $workspaceService): RedirectResponse
    {
        $workspace = $workspaceService->create($request->user(), $request->validated());

        return redirect()->route("dashboard", ["workspace" => $workspace->uuid]);
    }

    public function select(Request $request, WorkspaceService $workspaceService): Response
    {
        $workspaces = $workspaceService->getUserWorkspaces($request->user());

        return inertia("Workspace/Select", [
            "workspaces" => $workspaces->map(fn ($w) => [
                "uuid" => $w->uuid,
                "name" => $w->name,
                "description" => $w->description,
                "members_count" => $w->members()->count(),
                "role" => $w->pivot->role ?? "admin",
            ])->toArray(),
        ]);
    }

    public function activate(Request $request, WorkspaceService $workspaceService): RedirectResponse
    {
        $workspaceUuid = $request->input("workspace_uuid");
        $workspace = $request->user()->workspaces()->where("uuid", $workspaceUuid)->firstOrFail();
        $workspaceService->setLastVisited($workspace, $request->user());
        return redirect()->route("dashboard", ["workspace" => $workspace->uuid]);
    }
}
