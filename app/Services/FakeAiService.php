<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AiParserInterface;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class FakeAiService implements AiParserInterface
{
    /**
     * Deterministic parse — returns structured transactions from CSV content.
     * No HTTP calls, no API key needed.
     *
     * @return array<int, array{uuid: string, description: string, value: float, date: string, type: string, category_name: string, category_uuid: string|null, tags: list<string>, is_duplicate: bool}>
     */
    public function parseFile(Workspace $workspace, UploadedFile $file, string $type): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        // Only CSV/TXT supported in fake mode
        if (! in_array($extension, ['csv', 'txt'])) {
            throw new \RuntimeException(
                "FakeAiService only supports CSV/TXT. Got: {$extension}"
            );
        }

        $content = file_get_contents($file->getRealPath());
        $lines = array_filter(array_map('trim', explode("\n", $content)));

        if (count($lines) <= 1) {
            return [];
        }

        array_shift($lines); // Remove header

        return array_map(function (string $line) use ($type): array {
            $columns = str_getcsv($line);

            return [
                'uuid' => Str::uuid()->toString(),
                'description' => $columns[0] ?? 'Transação Importada',
                'value' => (float) ($columns[1] ?? 0),
                'date' => $columns[2] ?? now()->format('Y-m-d'),
                'type' => $type === 'income' ? 'credit' : 'debit',
                'category_name' => $columns[3] ?? 'Outros',
                'category_uuid' => null,
                'tags' => [],
                'is_duplicate' => false,
            ];
        }, $lines);
    }
}
