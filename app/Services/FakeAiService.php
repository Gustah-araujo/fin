<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AiParserInterface;
use Illuminate\Http\UploadedFile;

class FakeAiService implements AiParserInterface
{
    /**
     * Deterministic parse — returns structured transactions from CSV content.
     * No HTTP calls, no API key needed.
     *
     * @return array<int, array{description: string, value: float, date: string, type: string, category_name: string}>
     */
    public function parseFile(UploadedFile $file, string $type): array
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
                'description' => $columns[0] ?? 'Transação Importada',
                'value' => (float) ($columns[1] ?? 0),
                'date' => $columns[2] ?? now()->format('Y-m-d'),
                'type' => $type === 'income' ? 'credit' : 'debit',
                'category_name' => $columns[3] ?? 'Outros',
            ];
        }, $lines);
    }
}
