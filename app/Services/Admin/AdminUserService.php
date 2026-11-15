<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AdminUserService
{
    public static function activeUsersCount(): int
    {
        return User::query()
            ->whereNotNull('email_verified_at')
            ->count();
    }

    public static function resendVerification(User $user): void
    {
        self::assertUnverified($user);

        $user->notifyNow(new VerifyEmail);
    }

    public static function manuallyVerify(User $user): void
    {
        self::assertUnverified($user);

        $user->forceFill(['email_verified_at' => now()])->save();
    }

    private static function assertUnverified(User $user): void
    {
        if ($user->email_verified_at !== null) {
            throw new HttpException(422, 'Usuário já verificado.');
        }
    }
}
