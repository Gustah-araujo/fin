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
        $authService->verifyEmail($request->user());

        $user = $request->user();

        if ($user && $user->workspaces()->count() === 0) {
            return redirect()->route("workspace.create");
        }

        return redirect()->route("workspace.select")->with("verified", true);
    }
}
