<?php

declare(strict_types=1);

namespace App\Enums;

enum MemoryType: string
{
    case Insight = 'insight';
    case Pattern = 'pattern';
    case Anomaly = 'anomaly';
    case Summary = 'summary';

    public function label(): string
    {
        return match ($this) {
            self::Insight => 'Insight',
            self::Pattern => 'Padrão',
            self::Anomaly => 'Anomalia',
            self::Summary => 'Resumo',
        };
    }
}
