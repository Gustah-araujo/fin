<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Tools\ConfirmTransactionTool;
use App\Ai\Tools\QueryAccountsTool;
use App\Ai\Tools\QueryCategoriesTool;
use App\Ai\Tools\QueryCreditCardsTool;
use App\Ai\Tools\QueryRecurrencesTool;
use App\Ai\Tools\QueryTagsTool;
use App\Ai\Tools\QueryTransactionsTool;
use App\Ai\Tools\ValidateTransactionTool;
use App\Models\Workspace;
use Laravel\Ai\Contracts\Tool;
use Stringable;

class FinancialAssistantAgent extends BaseAgent
{
    public function __construct(
        Workspace $workspace,
        private readonly string $conversationId,
        private readonly int $userId,
    ) {
        parent::__construct($workspace);
    }

    /**
     * Workspace-specific read tools injected alongside the memory tools.
     *
     * @return iterable<int, Tool>
     */
    protected function agentTools(): iterable
    {
        return [
            new QueryTransactionsTool($this->workspace->uuid),
            new QueryRecurrencesTool($this->workspace->uuid),
            new QueryCategoriesTool($this->workspace->uuid),
            new QueryTagsTool($this->workspace->uuid),
            new QueryAccountsTool($this->workspace->uuid),
            new QueryCreditCardsTool($this->workspace->uuid),
            new ValidateTransactionTool($this->workspace->uuid, $this->conversationId, $this->userId),
            new ConfirmTransactionTool($this->workspace->uuid, $this->conversationId, $this->userId),
        ];
    }

    /**
     * The model used to power the financial assistant.
     */
    public function model(): string
    {
        return (string) config('ai.fin.model');
    }

    /**
     * Instructions for the AI model.
     */
    public function instructions(): Stringable|string
    {
        return <<<PROMPT
            Você é o "Fin", o assistente financeiro do workspace. Sua missão é ajudar o usuário a
            entender e gerenciar as finanças do workspace, sempre com respostas em pt-BR.

            ## Escopo
            Responda APENAS sobre finanças do workspace e funcionalidades da plataforma. Recuse
            educadamente qualquer assunto fora desse escopo (ex.: política, programação, saúde,
            entretenimento). Para qualquer pedido fora de finanças/plataforma, diga que você só
            consegue ajudar com as finanças do workspace e sugira voltar ao assunto financeiro.

            ## Integridade dos dados
            Nunca invente números, saldos ou transações. Sempre baseie cada resposta em dados reais
            do workspace, consultando as ferramentas de leitura:
            - query_transactions
            - query_recurrences
            - query_categories
            - query_tags
            - query_accounts
            - query_credit_cards

            Se os dados não existirem, informe isso claramente ao usuário em vez de supor valores.

            ## Isolamento do workspace
            Você só enxerga os dados do workspace atual, injetados no servidor. Nunca acesse nem
            mencione dados de outros workspaces.

            ## Cadastro assistido
            Quando o usuário pedir para registrar uma despesa ou recebimento, NUNCA persista os dados
            diretamente. Sempre apresente um resumo do lançamento e aguarde a confirmação explícita do
            usuário antes de qualquer alteração.

            ## Idioma
            Responda sempre em pt-BR.

            ## Memória
            {$this->memoryMap()}
            PROMPT;
    }
}
