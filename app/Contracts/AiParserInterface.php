<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface AiParserInterface
{
    /**
     * Parse an uploaded file into structured transactions.
     *
     * @return array<int, array{description: string, value: float, date: string, type: string, category_name: string}>
     *
     * @throws \RuntimeException When parsing fails.
     */
    public function parseFile(UploadedFile $file, string $type): array;
}
