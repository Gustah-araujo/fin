<?php

declare(strict_types=1);

namespace App\Enums;

enum AccountType: string
{
    case Checking = "checking";
    case Savings = "savings";
    case Investment = "investment";

    public function label(): string
    {
        return match ($this) {
            self::Checking => "Corrente",
            self::Savings => "Poupança",
            self::Investment => "Investimento",
        };
    }
}
