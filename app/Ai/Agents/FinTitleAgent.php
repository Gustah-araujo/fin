<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Tool;
use Stringable;

class FinTitleAgent extends BaseAgent
{
    /**
     * The model used to generate conversation titles.
     */
    public function model(): string
    {
        return (string) config('ai.fin.title_model');
    }

    /**
     * The title agent does not need any workspace tools.
     *
     * @return iterable<int, Tool>
     */
    protected function agentTools(): iterable
    {
        return [];
    }

    /**
     * Instructions for the AI model.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
            Você gera títulos curtos para conversas com o assistente financeiro "Fin".

            Com base na mensagem do usuário, crie um título de conversa em pt-BR que resuma o
            assunto principal. Regras:
            - Responda APENAS com o título, sem nenhum texto adicional.
            - Máximo de 60 caracteres.
            - Sem aspas, sem pontuação final e sem quebras de linha.
            PROMPT;
    }
}
