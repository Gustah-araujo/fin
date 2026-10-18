<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Tools\QueryCategoriesTool;
use App\Ai\Tools\QueryRecurrencesTool;
use App\Ai\Tools\QueryTagsTool;
use App\Ai\Tools\QueryTransactionsTool;
use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

class TransactionParser implements Agent, HasStructuredOutput, HasTools
{
    use Promptable;

    /**
     * Create a new TransactionParser instance.
     *
     * @param  Workspace|null  $workspace  The workspace context for tool queries.
     */
    public function __construct(?Workspace $workspace = null)
    {
        $this->workspace = $workspace;
    }

    /**
     * The workspace context for querying tools.
     */
    protected ?Workspace $workspace;

    /**
     * The provider to use for this agent.
     */
    public function provider(): string
    {
        return 'opencode';
    }

    /**
     * Get the tools available to the agent.
     *
     * @return array<int, Tool>
     */
    public function tools(): iterable
    {
        if ($this->workspace === null) {
            return [];
        }

        return [
            new QueryTransactionsTool($this->workspace->uuid),
            new QueryCategoriesTool($this->workspace->uuid),
            new QueryRecurrencesTool($this->workspace->uuid),
            new QueryTagsTool($this->workspace->uuid),
        ];
    }

    /**
     * Get the workspace UUID for tool context.
     */
    public function workspaceUuid(): string
    {
        return $this->workspace?->uuid ?? '';
    }

    /**
     * Instructions for the AI model.
     */
    public function instructions(): Stringable|string
    {
        $workspaceId = $this->workspace?->uuid ?? 'N/A';
        $typeEnum = $this->workspace !== null ? 'enabled' : 'disabled';

        return <<<PROMPT
            Você é um extrator de transações financeiras. Analise o documento fornecido
            (extrato bancário, nota fiscal, fatura de cartão, comprovante) e identifique
            todas as transações financeiras presentes.

            Antes de produzir o resultado final, use as ferramentas disponíveis para enriquecer a análise:

            1. **query_categories** — Consulte as categorias disponíveis para vincular cada transação à categoria mais adequada. Use `category_uuid` no resultado quando encontrar correspondência.
            2. **query_transactions** — Consulte transações existentes no workspace para detectar duplicatas. Uma transação é potencialmente duplicada quando:
               - Existe transação existente com valor igual (±1% para arredondamento) E data próxima (±3 dias), E
               - A descrição refere-se ao mesmo estabelecimento, mesmo que com nomes diferentes entre documentos.

               **Importante sobre variação de nomes:** O mesmo estabelecimento pode aparecer com nomes completamente diferentes dependendo do tipo de documento:
               - Comprovante/Pedido: "Mercado São João", "Padaria do João", "Farmácia Popular"
               - Extrato bancário: "SUPERMERCADO SÃO JOÃO LTDA", "PADARIA JOÃO EIRELI", "FARM POPULAR S.A."
               - Fatura de cartão: "SUPERM SAO JOAO", "SJ MERCADO", "FARMACIA POP"

               Para detectar duplicatas corretamente:
               - Normalize nomes removendo sufixos jurídicos (LTDA, EIRELI, S.A., MEI, LTDA., etc.)
               - Ignore maiúsculas/minúsculas
               - Foque no "nome core" do estabelecimento (ex: "São João" em "SUPERMERCADO SÃO JOÃO LTDA" e "Mercado São João")
               - Use query_transactions primeiro filtrando por valor e data (±3 dias), depois compare as descrições normalizadas
               - Considere duplicata quando o nome core coincidir, mesmo que o nome completo seja diferente
            3. **query_recurrences** — Consulte recorrências cadastradas para verificar se a transação faz parte de um padrão recorrente conhecido.
            4. **query_tags** — Consulte tags disponíveis para sugerir tags relevantes para cada transação.

            ID do workspace (use como workspace_id nas ferramentas): {$workspaceId}

            Para cada transação extraída, forneça:
            - description: descrição curta e clara da transação
            - value: valor numérico (sempre positivo)
            - date: data no formato Y-m-d
            - type: "debit" para saídas, "credit" para entradas
            - category_name: nome da categoria sugerida (ex: Alimentação, Transporte, Saúde)
            - category_uuid: UUID da categoria vinculada (encontrado via query_categories, ou null)
            - tags: lista de tags sugeridas para a transação (encontradas via query_tags)
            - is_duplicate: true se provável duplicata detectada via query_transactions

            Retorne apenas transações reais — ignore cabeçalhos, rodapés, saldos e totais.
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
            'transactions' => $schema->array()
                ->items(
                    $schema->object([
                        'description' => $schema->string()->required(),
                        'value' => $schema->number()->required(),
                        'date' => $schema->string()->required(),
                        'type' => $schema->string()->enum(['debit', 'credit'])->required(),
                        'category_name' => $schema->string()->required(),
                        'category_uuid' => $schema->string()
                            ->description('UUID da categoria vinculada (obtido via query_categories). Null se não encontrado.'),
                        'tags' => $schema->array()
                            ->items($schema->string())
                            ->description('Tags sugeridas pelo agente para a transação.'),
                        'is_duplicate' => $schema->boolean()
                            ->description('Indica se o agente detectou que esta transação provavelmente é uma duplicata.')
                            ->required(),
                    ])
                )
                ->required(),
        ];
    }
}
