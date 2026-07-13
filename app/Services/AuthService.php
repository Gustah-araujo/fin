<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class AuthService
{
    public function register(array $data): User
    {
        $user = User::create([
            "uuid" => Str::orderedUuid()->toString(),
            "name" => $data["name"],
            "email" => $data["email"],
            "password" => Hash::make($data["password"]),
        ]);

        event(new Registered($user));

        return $user;
    }

    public function authenticate(array $credentials, bool $remember = false): void
    {
        if (! Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                "email" => ["Credenciais inválidas."],
            ]);
        }
    }

    public function sendVerificationEmail(User $user): void
    {
        $user->sendEmailVerificationNotification();
    }

    public function resendVerificationEmail(User $user): void
    {
        $user->sendEmailVerificationNotification();
    }

    public function verifyEmail(User $user): void
    {
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }
    }

    public function sendPasswordResetLink(string $email): string
    {
        return Password::sendResetLink(["email" => $email]);
    }

    public function resetPassword(array $data): string
    {
        return Password::reset(
            $data,
            function (User $user, string $password) {
                $user->forceFill([
                    "password" => Hash::make($password),
                ])->save();
            }
        );
    }

    public function changePassword(User $user, string $newPassword): void
    {
        $user->forceFill([
            "password" => Hash::make($newPassword),
        ])->save();
    }

    public function handleGoogleCallback(SocialiteUser $googleUser): User
    {
        $existingUser = User::where("email", $googleUser->getEmail())->first();

        if ($existingUser) {
            if (! $existingUser->google_id) {
                $existingUser->forceFill([
                    "google_id" => $googleUser->getId(),
                    "avatar" => $googleUser->getAvatar(),
                    "email_verified_at" => $existingUser->email_verified_at ?? now(),
                ])->save();
            }
            return $existingUser;
        }

        return User::create([
            "uuid" => Str::orderedUuid()->toString(),
            "name" => $googleUser->getName() ?? $googleUser->getEmail(),
            "email" => $googleUser->getEmail(),
            "google_id" => $googleUser->getId(),
            "avatar" => $googleUser->getAvatar(),
            "email_verified_at" => now(),
            "password" => null,
        ]);
    }
}
