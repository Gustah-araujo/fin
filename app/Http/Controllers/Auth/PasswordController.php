<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePasswordRequest;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class PasswordController extends Controller
{
    public function edit(): Response
    {
        return inertia("Auth/ChangePassword");
    }

    public function update(UpdatePasswordRequest $request, AuthService $authService): RedirectResponse
    {
        $authService->changePassword(
            $request->user(),
            $request->validated()["password"],
        );

        return back()->with("status", "Senha alterada com sucesso.");
    }
}
