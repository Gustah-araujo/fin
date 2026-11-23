<?php

declare(strict_types=1);

namespace App\Services;

use App\Ai\Agents\TransactionParser;
use App\Contracts\AiParserInterface;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Responses\StructuredAgentResponse;
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

        $csvContent = null;

        match ($extension) {
            'csv', 'txt' => $csvContent = (string) file_get_contents($file->getRealPath()),
            'html' => $csvContent = strip_tags((string) file_get_contents($file->getRealPath())),
            'pdf' => $attachments[] = $file,
            'png', 'jpg', 'jpeg', 'webp' => $attachments[] = Image::fromPath($file->getRealPath()),
            default => throw new RuntimeException("Formato de arquivo não suportado: {$extension}."),
        };

        if (is_string($csvContent)) {
            $userMessage .= "\n\nConteúdo:\n".$csvContent;
        }

        try {
            $response = $agent->prompt($userMessage, attachments: $attachments);
        } catch (\Throwable $e) {
            throw new RuntimeException('AI parsing failed: '.$e->getMessage(), 0, $e);
        }

        $structured = $response instanceof StructuredAgentResponse
            ? $response->structured
            : [];

        $transactions = $structured['transactions'] ?? [];

        return $this->normalizeTransactions(is_array($transactions) ? array_values($transactions) : []);
    }

    /**
     * Normalize structured output into consistent transaction structure.
     *
     * @param  array<int, mixed>  $transactions
     * @return array<int, array{uuid: string, description: string, value: float, date: string, type: string, category_name: string, category_uuid: string|null, tags: list<string>, is_duplicate: bool}>
     */
    private function normalizeTransactions(array $transactions): array
    {
        $normalized = [];

        foreach ($transactions as $transaction) {
            if (! is_array($transaction)) {
                continue;
            }

            $normalized[] = [
                'uuid' => Str::uuid()->toString(),
                'description' => self::stringValue($transaction['description'] ?? null, ''),
                'value' => self::floatValue($transaction['value'] ?? null),
                'date' => self::stringValue($transaction['date'] ?? null, now()->format('Y-m-d')),
                'type' => self::stringValue($transaction['type'] ?? null, 'debit'),
                'category_name' => self::stringValue($transaction['category_name'] ?? null, 'Outros'),
                'category_uuid' => is_string($transaction['category_uuid'] ?? null) && $transaction['category_uuid'] !== ''
                    ? $transaction['category_uuid']
                    : null,
                'tags' => is_array($transaction['tags'] ?? null)
                    ? array_values(array_filter(self::stringList($transaction['tags']), fn (string $tag): bool => $tag !== ''))
                    : [],
                'is_duplicate' => (bool) ($transaction['is_duplicate'] ?? false),
            ];
        }

        return $normalized;
    }

    private static function stringValue(mixed $value, string $default): string
    {
        if (is_string($value)) {
            return $value;
        }

        return is_scalar($value) ? (string) $value : $default;
    }

    private static function floatValue(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    /**
     * @param  array<mixed>  $values
     * @return list<string>
     */
    private static function stringList(array $values): array
    {
        $strings = [];

        foreach ($values as $value) {
            if (is_string($value)) {
                $strings[] = $value;
            } elseif (is_numeric($value)) {
                $strings[] = (string) $value;
            }
        }

        return $strings;
    }
}
