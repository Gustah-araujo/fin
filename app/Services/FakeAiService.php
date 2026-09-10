<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;

class FakeAiService extends AiService
{
    public function __construct()
    {
        // No constructor dependencies — fake skips HTTP entirely
        // Bypass parent constructor which requires apiKey
    }

    /**
     * Deterministic parse — returns structured transactions based on CSV content.
     * No HTTP calls, no API key needed.
     *
     * @return array<int, array{description: string, value: float, date: string, type: string, category_name: string}>
     */
    public function parse(string $csvContent, string $type): array
    {
        $lines = array_filter(array_map('trim', explode("\n", $csvContent)));

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
