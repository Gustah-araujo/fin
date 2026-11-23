<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isAdmin($request->user()?->type)) {
            abort(403);
        }

        return $next($request);
    }

    private function isAdmin(mixed $type): bool
    {
        return $type instanceof UserType
            ? $type === UserType::Admin
            : $type === UserType::Admin->value;
    }
}
