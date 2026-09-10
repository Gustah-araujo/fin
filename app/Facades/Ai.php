<?php

declare(strict_types=1);

namespace App\Facades;

use App\Services\AiService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array parse(string $csvContent, string $type)
 *
 * @see AiService
 */
class Ai extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'ai';
    }
}
