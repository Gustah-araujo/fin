<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNewPasswordRequest;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Response;

class NewPasswordController extends Controller
{
    public function create(Request $request): Response
    {
        return inertia("Auth/ResetPassword", [
            "email" => $request->get("email"),
            "token" => $request->route("token"),
        ]);
    }

    public function store(StoreNewPasswordRequest $request, AuthService $authService): RedirectResponse
    {
        $status = $authService->resetPassword($request->validated());

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route("login")->with("status", "Senha redefinida com sucesso.");
        }

        return back()->withErrors(["email" => "Link inválido ou expirado."]);
    }
}
