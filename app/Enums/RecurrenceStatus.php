<?php

declare(strict_types=1);

namespace App\Enums;

enum RecurrenceStatus: string
{
    case Active = 'active';
    case Paused = 'paused';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Ativa',
            self::Paused => 'Pausada',
        };
    }
}
