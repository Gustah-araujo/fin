<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRegisteredUserRequest;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return inertia("Auth/Register");
    }

    public function store(StoreRegisteredUserRequest $request, AuthService $authService): RedirectResponse
    {
        $user = $authService->register($request->validated());

        Auth::login($user);

        $authService->sendVerificationEmail($user);

        return redirect()->route("verification.notice")->with("status", "Um link de verificação foi enviado para seu email.");
    }
}
