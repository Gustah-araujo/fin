<?php

declare(strict_types=1);

namespace App\Services;

use App\Ai\Agents\TransactionParser;
use App\Contracts\AiParserInterface;
use Illuminate\Http\UploadedFile;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\Image;
use RuntimeException;

class AiService implements AiParserInterface
{
    /**
     * Parse an uploaded file into structured transactions via AI.
     *
     * @return array<int, array{description: string, value: float, date: string, type: string, category_name: string}>
     *
     * @throws RuntimeException When parsing fails.
     */
    public function parseFile(UploadedFile $file, string $type): array
    {
        $agent = new TransactionParser;
        $extension = strtolower($file->getClientOriginalExtension());

        $userMessage = "Tipo esperado: {$type}. Extraia as transações financeiras.";
        $attachments = [];

        match ($extension) {
            'csv', 'txt' => $userMessage .= "\n\nConteúdo:\n" . file_get_contents($file->getRealPath()),
            'html' => $userMessage .= "\n\nConteúdo:\n" . strip_tags(file_get_contents($file->getRealPath())),
            'pdf' => $attachments[] = Document::fromPath($file->getRealPath()),
            'png', 'jpg', 'jpeg', 'webp' => $attachments[] = Image::fromPath($file->getRealPath()),
            default => throw new RuntimeException("Formato de arquivo não suportado: {$extension}."),
        };

        try {
            $response = $agent->prompt($userMessage, attachments: $attachments);
        } catch (\Throwable $e) {
            throw new RuntimeException('AI parsing failed: ' . $e->getMessage(), 0, $e);
        }

        return $this->normalizeTransactions($response['transactions']);
    }

    /**
     * Normalize structured output into consistent transaction structure.
     *
     * @param  array<int, array>  $transactions
     * @return array<int, array{description: string, value: float, date: string, type: string, category_name: string}>
     */
    private function normalizeTransactions(array $transactions): array
    {
        return array_map(fn (array $t): array => [
            'description' => (string) ($t['description'] ?? ''),
            'value' => (float) ($t['value'] ?? 0),
            'date' => (string) ($t['date'] ?? now()->format('Y-m-d')),
            'type' => (string) ($t['type'] ?? 'debit'),
            'category_name' => (string) ($t['category_name'] ?? 'Outros'),
        ], $transactions);
    }
}
