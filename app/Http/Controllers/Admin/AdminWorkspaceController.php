<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminWorkspaceResource;
use App\Http\Resources\MemberResource;
use App\Models\Workspace;
use Inertia\Response;

class AdminWorkspaceController extends Controller
{
    public function index(): Response
    {
        $workspaces = Workspace::query()
            ->withCount('members')
            ->orderBy('created_at', 'desc')
            ->get();

        return inertia('Admin/Workspaces/Index', [
            'workspaces' => AdminWorkspaceResource::collection($workspaces),
        ]);
    }

    public function show(Workspace $workspace): Response
    {
        $members = $workspace->members()
            ->withPivot('role', 'created_at')
            ->orderBy('workspace_user.created_at')
            ->get();

        return inertia('Admin/Workspaces/Show', [
            'workspace' => new AdminWorkspaceResource($workspace),
            'members' => MemberResource::collection($members),
        ]);
    }
}
