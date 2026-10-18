<?php

declare(strict_types=1);

namespace App\Facades;

use App\Contracts\AiParserInterface;
use App\Services\AiService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array parseFile(UploadedFile $file)
 *
 * @see AiService
 * @see AiParserInterface
 */
class Ai extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AiParserInterface::class;
    }
}
