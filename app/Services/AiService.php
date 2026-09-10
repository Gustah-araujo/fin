<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class AiService
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api.deepseek.com',
        private readonly string $model = 'deepseek-chat',
    ) {}

    /**
     * Parse CSV content into structured transactions via AI.
     *
     * @return array<int, array{description: string, value: float, date: string, type: string, category_name: string}>
     *
     * @throws RuntimeException When the API returns an error or invalid response.
     */
    public function parse(string $csvContent, string $type): array
    {
        $response = Http::timeout(config('ai.timeout', 120))
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])
            ->post("{$this->baseUrl}/v1/chat/completions", [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Você é um extrator de transações financeiras. Retorne JSON array com objetos {description, value, date (Y-m-d), type (debit/credit), category_name}. Não inclua markdown ou texto adicional.',
                    ],
                    [
                        'role' => 'user',
                        'content' => "Tipo esperado: {$type}\n\nCSV:\n{$csvContent}",
                    ],
                ],
                'response_format' => ['type' => 'json_object'],
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "AI API request failed: {$response->status()} {$response->body()}"
            );
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content)) {
            throw new RuntimeException('AI API returned invalid response structure.');
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('AI API returned invalid JSON: '.json_last_error_msg());
        }

        return $this->normalizeTransactions($data['transactions'] ?? []);
    }

    /**
     * Normalize raw AI output into consistent transaction structure.
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
