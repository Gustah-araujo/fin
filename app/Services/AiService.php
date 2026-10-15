<?php

declare(strict_types=1);

namespace App\Services;

use App\Ai\Agents\TransactionParserAgent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Ai;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\Image;
use RuntimeException;

class AiService
{
    /**
     * Parse an uploaded file into structured transactions via AI Agent.
     *
     * @return array<int, array{description: string, value: float, date: string, type: string, category_name: string}>
     *
     * @throws RuntimeException When the Agent returns an error or invalid response.
     */
    public function parseFile(UploadedFile $file, string $type): array
    {
        $agent = TransactionParserAgent::make(type: $type);

        $mimeType = $file->getMimeType();
        $attachments = $this->buildAttachments($file, $mimeType);

        try {
            $response = $agent->prompt(
                "Tipo esperado: {$type}. Analise o anexo e extraia todas as transações.",
                attachments: $attachments,
            );
        } catch (\Throwable $e) {
            Log::error('AI Agent parse failed: '.$e->getMessage(), [
                'type' => $type,
                'mime' => $mimeType,
            ]);
            throw new RuntimeException('AI Agent parse failed: '.$e->getMessage(), 0, $e);
        }

        return $this->normalizeTransactions($response['transactions'] ?? []);
    }

    /**
     * Build appropriate attachment array based on file MIME type.
     *
     * @return array<int, Document|Image>
     */
    private function buildAttachments(UploadedFile $file, string $mimeType): array
    {
        $path = $file->getRealPath();

        if (str_starts_with($mimeType, 'image/')) {
            return [Image::fromPath($path)];
        }

        return [Document::fromPath($path)];
    }

    /**
     * Normalize raw Agent output into consistent transaction structure.
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
