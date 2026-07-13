<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreForgotPasswordRequest;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return inertia("Auth/ForgotPassword", [
            "status" => session("status"),
        ]);
    }

    public function store(StoreForgotPasswordRequest $request, AuthService $authService): RedirectResponse
    {
        $status = $authService->sendPasswordResetLink($request->validated()["email"]);

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with("status", "Se o email existir, um link de recuperação foi enviado.");
        }

        return back()->with("status", "Se o email existir, um link de recuperação foi enviado.");
    }
}
