<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Resources\WorkspaceResource;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user();
        $shared = parent::share($request);

        if ($user) {
            $shared['auth'] = [
                'user' => [
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                ],
            ];
            $shared['workspaces'] = WorkspaceResource::collection(
                $user->workspaces()->withPivot('role')->get()
            );

            $shared['workspace'] = $this->resolveCurrentWorkspace($request, $user);
        } else {
            $shared['auth'] = ['user' => null];
            $shared['workspaces'] = [];
            $shared['workspace'] = null;
        }

        $shared['status'] = session('status');
        $shared['flash'] = [
            'success' => session('success'),
            'error' => session('error'),
        ];

        return $shared;
    }

    private function resolveCurrentWorkspace(Request $request, User $user): ?WorkspaceResource
    {
        $routeWorkspace = $request->route()?->parameter('workspace');

        if (! $routeWorkspace instanceof Workspace) {
            return null;
        }

        $workspace = $user->workspaces()
            ->wherePivot('workspace_id', $routeWorkspace->id)
            ->first();

        return $workspace ? new WorkspaceResource($workspace) : null;
    }
}
