<?php

declare(strict_types=1);

namespace App\Services;

use App\Ai\Agents\TransactionParser;
use App\Contracts\AiParserInterface;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Laravel\Ai\Files\Image;
use RuntimeException;

class AiService implements AiParserInterface
{
    /**
     * Parse an uploaded file into structured transactions via AI.
     *
     * @return array<int, array{uuid: string, description: string, value: float, date: string, type: string, category_name: string, category_uuid: string|null, tags: list<string>, is_duplicate: bool}>
     *
     * @throws RuntimeException When parsing fails.
     */
    public function parseFile(Workspace $workspace, UploadedFile $file): array
    {
        $agent = new TransactionParser($workspace);
        $extension = strtolower($file->getClientOriginalExtension());

        $userMessage = 'Extraia as transações financeiras. Classifique cada transação como debit (saída) ou crédito (entrada) baseado no contexto do documento.';
        $attachments = [];

        match ($extension) {
            'csv', 'txt' => $userMessage .= "\n\nConteúdo:\n".file_get_contents($file->getRealPath()),
            'html' => $userMessage .= "\n\nConteúdo:\n".strip_tags(file_get_contents($file->getRealPath())),
            'pdf' => $attachments[] = $file,
            'png', 'jpg', 'jpeg', 'webp' => $attachments[] = Image::fromPath($file->getRealPath()),
            default => throw new RuntimeException("Formato de arquivo não suportado: {$extension}."),
        };

        try {
            $response = $agent->prompt($userMessage, attachments: $attachments);
        } catch (\Throwable $e) {
            throw new RuntimeException('AI parsing failed: '.$e->getMessage(), 0, $e);
        }

        return $this->normalizeTransactions($response['transactions']);
    }

    /**
     * Normalize structured output into consistent transaction structure.
     *
     * @param  array<int, array>  $transactions
     * @return array<int, array{uuid: string, description: string, value: float, date: string, type: string, category_name: string, category_uuid: string|null, tags: list<string>, is_duplicate: bool}>
     */
    private function normalizeTransactions(array $transactions): array
    {
        return array_map(fn (array $t): array => [
            'uuid' => Str::uuid()->toString(),
            'description' => (string) ($t['description'] ?? ''),
            'value' => (float) ($t['value'] ?? 0),
            'date' => (string) ($t['date'] ?? now()->format('Y-m-d')),
            'type' => (string) ($t['type'] ?? 'debit'),
            'category_name' => (string) ($t['category_name'] ?? 'Outros'),
            'category_uuid' => isset($t['category_uuid']) && is_string($t['category_uuid']) && $t['category_uuid'] !== ''
                ? $t['category_uuid']
                : null,
            'tags' => isset($t['tags']) && is_array($t['tags'])
                ? array_values(array_filter(array_map('strval', $t['tags']), fn (string $tag): bool => $tag !== ''))
                : [],
            'is_duplicate' => (bool) ($t['is_duplicate'] ?? false),
        ], $transactions);
    }
}
