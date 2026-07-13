<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialiteController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver("google")->redirect();
    }

    public function callback(AuthService $authService): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver("google")->user();
        } catch (\Exception $e) {
            return redirect()->route("login")->withErrors([
                "email" => "Falha na autenticação com Google.",
            ]);
        }

        $user = $authService->handleGoogleCallback($googleUser);

        Auth::login($user, true);
        request()->session()->regenerate();

        return redirect()->route("workspace.select");
    }
}
