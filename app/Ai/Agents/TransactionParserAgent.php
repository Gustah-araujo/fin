<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class TransactionParserAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        private readonly string $transactionType = 'expense',
    ) {}

    public function instructions(): Stringable|string
    {
        return implode("\n", [
            'Você é um extrator especializado de transações financeiras.',
            'Analise o documento anexado (extrato bancário, fatura de cartão, comprovante) e extraia todas as transações financeiras visíveis.',
            'Para cada transação, identifique: descrição, valor, data, tipo (debit para despesas/saídas, credit para receitas/entradas) e uma categoria sugerida.',
            'Retorne exclusivamente o JSON estruturado, sem markdown ou texto adicional.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'transactions' => $schema->array()
                ->items($schema->object(fn (JsonSchema $s): array => [
                    'description' => $s->string()->required(),
                    'value' => $s->number()->required(),
                    'date' => $s->string()->required(),
                    'type' => $s->string()->enum(['debit', 'credit'])->required(),
                    'category_name' => $s->string()->required(),
                ]))
                ->required(),
        ];
    }
}
