<?php

declare(strict_types=1);

namespace App\Enums;

enum RecurrenceFrequency: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Semanal',
            self::Monthly => 'Mensal',
        };
    }

    public function dayLabel(int $day): string
    {
        return match ($this) {
            self::Weekly => match ($day) {
                0 => 'Dom',
                1 => 'Seg',
                2 => 'Ter',
                3 => 'Qua',
                4 => 'Qui',
                5 => 'Sex',
                6 => 'Sáb',
                default => "Dia {$day}",
            },
            self::Monthly => "Dia {$day}",
        };
    }
}
