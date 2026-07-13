<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->workspaces()->count() === 0) {
            $exemptRoutes = [
                "workspace.create",
                "workspace.store",
                "logout",
                "verification.notice",
                "verification.verify",
                "verification.send",
                "password.edit",
                "password.update",
            ];

            if (! in_array($request->route()?->getName(), $exemptRoutes, true)) {
                return redirect()->route("workspace.create");
            }
        }

        return $next($request);
    }
}
