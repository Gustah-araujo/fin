<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Workspace;
use Illuminate\Http\UploadedFile;

interface AiParserInterface
{
    /**
     * Parse an uploaded file into structured transactions.
     *
     * @return array<int, array{uuid: string, description: string, value: float, date: string, type: string, category_name: string, category_uuid: string|null, tags: list<string>, is_duplicate: bool}>
     *
     * @throws \RuntimeException When parsing fails.
     */
    public function parseFile(Workspace $workspace, UploadedFile $file): array;
}
