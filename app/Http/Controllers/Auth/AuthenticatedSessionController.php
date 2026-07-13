<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return inertia("Auth/Login", [
            "status" => session("status"),
        ]);
    }

    public function store(LoginRequest $request, AuthService $authService): RedirectResponse
    {
        $authService->authenticate(
            $request->only("email", "password"),
            $request->boolean("remember"),
        );

        $request->session()->regenerate();

        if (! $request->user()?->hasVerifiedEmail()) {
            return redirect()->route("verification.notice");
        }

        return redirect()->intended(route("workspace.select", absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect("/");
    }
}
