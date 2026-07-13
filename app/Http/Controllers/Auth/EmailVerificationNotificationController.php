<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    public function store(Request $request, AuthService $authService): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route("login");
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route("workspace.select", absolute: false));
        }

        $authService->resendVerificationEmail($user);

        return back()->with("status", "Email de verificação reenviado.");
    }
}
