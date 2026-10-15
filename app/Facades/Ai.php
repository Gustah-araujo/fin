<?php

declare(strict_types=1);

namespace App\Facades;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array parseFile(UploadedFile $file, string $type)
 *
 * @see \App\Services\AiService
 * @see \App\Contracts\AiParserInterface
 */
class Ai extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \App\Contracts\AiParserInterface::class;
    }
}
