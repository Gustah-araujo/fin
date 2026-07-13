<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = "app";

    public function share(Request $request): array
    {
        $user = $request->user();
        $shared = parent::share($request);

        if ($user) {
            $shared["auth"] = [
                "user" => [
                    "uuid" => $user->uuid,
                    "name" => $user->name,
                    "email" => $user->email,
                    "avatar" => $user->avatar,
                ],
            ];
            $shared["workspaces"] = $user->workspaces()->get()->map(fn ($w) => [
                "uuid" => $w->uuid,
                "name" => $w->name,
                "description" => $w->description,
            ])->values()->toArray();

            $shared["workspace"] = $this->resolveCurrentWorkspace($request, $user);
        } else {
            $shared["auth"] = ["user" => null];
            $shared["workspaces"] = [];
            $shared["workspace"] = null;
        }

        $shared["status"] = session("status");

        return $shared;
    }

    private function resolveCurrentWorkspace(Request $request, $user): ?array
    {
        $workspace = $request->route()?->parameter("workspace");

        if (! $workspace instanceof Workspace) {
            return null;
        }

        $member = $workspace->members()->where("user_id", $user->id)->first();

        return [
            "uuid" => $workspace->uuid,
            "name" => $workspace->name,
            "role" => $member?->pivot?->role,
        ];
    }
}
