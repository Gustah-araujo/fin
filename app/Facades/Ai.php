<?php

declare(strict_types=1);

namespace App\Facades;

use App\Contracts\AiParserInterface;
use App\Models\Workspace;
use App\Services\AiService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array<int, array{uuid: string, description: string, value: float, date: string, type: string, category_name: string, category_uuid: string|null, tags: list<string>, is_duplicate: bool}> parseFile(Workspace $workspace, UploadedFile $file)
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
