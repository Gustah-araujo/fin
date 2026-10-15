<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;

class FakeAiService extends AiService
{
    public function __construct()
    {
        // No constructor dependencies — fake skips HTTP entirely
        // Bypass parent constructor which requires apiKey
    }

    /**
     * Deterministic parse — returns structured transactions based on file content.
     * No HTTP calls, no API key needed.
     *
     * @return array<int, array{description: string, value: float, date: string, type: string, category_name: string}>
     */
    public function parseFile(UploadedFile $file, string $type): array
    {
        $content = file_get_contents($file->getRealPath());

        // For non-CSV files (images, PDFs), return a fake transaction for testing
        $mimeType = $file->getMimeType();
        if (! str_starts_with($mimeType ?? '', 'text/') && $mimeType !== 'application/csv') {
            return [
                [
                    'description' => 'Transação Extraída via IA (fake)',
                    'value' => 99.90,
                    'date' => now()->format('Y-m-d'),
                    'type' => $type === 'income' ? 'credit' : 'debit',
                    'category_name' => 'Importadas',
                ],
            ];
        }

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
