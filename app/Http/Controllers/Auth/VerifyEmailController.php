<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    public function __invoke(EmailVerificationRequest $request, AuthService $authService): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user !== null, 401);

        $authService->verifyEmail($user);

        if ($user->workspaces()->count() === 0) {
            return redirect()->route('workspace.create');
        }

        return redirect()->route('workspace.select')->with('verified', true);
    }
}
