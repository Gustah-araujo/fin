<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class TransactionParser implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * The provider to use for this agent.
     */
    public function provider(): string
    {
        return 'opencode';
    }

    /**
     * Instructions for the AI model.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
            Você é um extrator de transações financeiras. Analise o documento fornecido
            (extrato bancário, nota fiscal, fatura de cartão, comprovante) e identifique
            todas as transações financeiras presentes.

            Para cada transação, extraia:
            - description: descrição clara da transação
            - value: valor numérico (sempre positivo)
            - date: data no formato Y-m-d
            - type: "debit" para saídas, "credit" para entradas
            - category_name: categoria sugerida (ex: Alimentação, Transporte, Saúde)

            Retorne apenas transações reais — ignore cabeçalhos, rodapés, saldos e totais.
        PROMPT;
    }

    /**
     * Structured output schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'transactions' => $schema->array()
                ->items(
                    $schema->object([
                        'description' => $schema->string()->required(),
                        'value' => $schema->number()->required(),
                        'date' => $schema->string()->required(),
                        'type' => $schema->string()->enum(['debit', 'credit'])->required(),
                        'category_name' => $schema->string()->required(),
                    ])
                )
                ->required(),
        ];
    }
}
