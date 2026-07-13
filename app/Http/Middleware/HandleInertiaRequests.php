<?php

declare(strict_types=1);

namespace App\Http\Middleware;

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
        } else {
            $shared["auth"] = ["user" => null];
            $shared["workspaces"] = [];
        }

        $shared["status"] = session("status");

        return $shared;
    }
}
