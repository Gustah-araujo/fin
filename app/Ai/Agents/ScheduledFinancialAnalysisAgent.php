<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Tools\QueryRecurrencesTool;
use App\Ai\Tools\QueryTransactionsTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\Tool;
use Stringable;

class ScheduledFinancialAnalysisAgent extends BaseAgent implements HasStructuredOutput
{
    /**
     * Workspace-specific tools injected alongside the memory tools.
     *
     * @return iterable<int, Tool>
     */
    protected function agentTools(): iterable
    {
        return [
            new QueryTransactionsTool($this->workspace->uuid),
            new QueryRecurrencesTool($this->workspace->uuid),
        ];
    }

    /**
     * Instructions for the AI model.
     */
    public function instructions(): Stringable|string
    {
        return <<<PROMPT
            Você é um analista financeiro. Analise as transações e recorrências dos últimos 30 dias
            do workspace para identificar padrões, anomalias e insights relevantes.

            ## Mapa de Memória (conhecimento já identificado em execuções anteriores):
            {$this->memoryMap()}

            Instruções:
            - Use query_transactions para buscar os dados dos últimos 30 dias
            - Use query_recurrences para verificar padrões ativos
            - Compare os dados atuais com as memórias existentes para evitar duplicatas
            - Identifique: anomalias de gastos, vencimentos próximos, tendências por categoria, mudanças em padrões recorrentes
            - Use create_memory para persistir novos insights para execuções futuras
            - Retorne um array estruturado de insights
        PROMPT;
    }

    /**
     * Structured output schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'insights' => $schema->array()
                ->items(
                    $schema->object([
                        'type' => $schema->string()->enum(['anomaly', 'pattern', 'insight'])->required(),
                        'title' => $schema->string()->required(),
                        'description' => $schema->string()->required(),
                        'severity' => $schema->string()->enum(['low', 'medium', 'high'])->required(),
                        'data' => $schema->object()->nullable(),
                    ])
                )
                ->required(),
        ];
    }
}
