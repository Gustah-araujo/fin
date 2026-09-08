<?php

declare(strict_types=1);

namespace App\Support;

class Toast
{
    public const KEY_SUCCESS = 'success';

    public const KEY_ERROR = 'error';

    public static function success(string $message): void
    {
        session()->flash(self::KEY_SUCCESS, $message);
    }

    public static function error(string $message): void
    {
        session()->flash(self::KEY_ERROR, $message);
    }
}
