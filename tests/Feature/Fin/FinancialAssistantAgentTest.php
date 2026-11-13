<?php

declare(strict_types=1);

namespace Tests\Feature\Fin;

use App\Ai\Agents\FinancialAssistantAgent;
use App\Enums\WorkspaceRole;
use App\Models\AgentMemory;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Tests\TestCase;

class FinancialAssistantAgentTest extends TestCase
{
    private Workspace $workspace;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->members()->attach($this->user, ['role' => WorkspaceRole::Admin->value]);
    }

    public function test_agent_uses_configured_fin_model(): void
    {
        $agent = new FinancialAssistantAgent($this->workspace, 'conv-test-id', $this->user);

        $this->assertSame(config('ai.fin.model'), $agent->model());
    }

    public function test_agent_exposes_all_read_tools_plus_memory_tools(): void
    {
        $agent = new FinancialAssistantAgent($this->workspace, 'conv-test-id', $this->user);

        $tools = [...$agent->tools()];

        $this->assertCount(12, $tools);

        $toolNames = array_map(fn ($tool) => class_basename($tool), $tools);

        $this->assertContains('QueryTransactionsTool', $toolNames);
        $this->assertContains('QueryRecurrencesTool', $toolNames);
        $this->assertContains('QueryCategoriesTool', $toolNames);
        $this->assertContains('QueryTagsTool', $toolNames);
        $this->assertContains('QueryAccountsTool', $toolNames);
        $this->assertContains('QueryCreditCardsTool', $toolNames);
        $this->assertContains('CreateMemoryTool', $toolNames);
        $this->assertContains('ReadMemoryTool', $toolNames);
        $this->assertContains('UpdateMemoryTool', $toolNames);
        $this->assertContains('DeleteMemoryTool', $toolNames);
        $this->assertContains('ValidateTransactionTool', $toolNames);
        $this->assertContains('ConfirmTransactionTool', $toolNames);
    }

    public function test_agent_instructions_contain_scope_guardrails(): void
    {
        $agent = new FinancialAssistantAgent($this->workspace, 'conv-test-id', $this->user);

        $instructions = (string) $agent->instructions();

        $this->assertStringContainsString('query_transactions', $instructions);
        $this->assertStringContainsString('query_accounts', $instructions);
        $this->assertStringContainsString('query_credit_cards', $instructions);
        $this->assertStringContainsString('política', $instructions);
        $this->assertStringContainsString('escopo', $instructions);

        $this->assertStringContainsString('## Tom', $instructions);
        $this->assertStringContainsString('amigável', $instructions);
        $this->assertStringContainsString('## Formato de resposta', $instructions);
        $this->assertStringContainsString('Markdown', $instructions);
        $this->assertStringContainsString('## Contexto atual', $instructions);
    }

    public function test_agent_instructions_inject_workspace_memories(): void
    {
        AgentMemory::factory()->for($this->workspace)->create([
            'title' => 'Meta: quitar dívidas',
            'content' => 'Usuário quer quitar dívidas até 2027',
        ]);

        $agent = new FinancialAssistantAgent($this->workspace, 'conv-test-id', $this->user);

        $instructions = (string) $agent->instructions();

        $this->assertStringContainsString('## Memória', $instructions);
        $this->assertStringContainsString('Meta: quitar dívidas', $instructions);
        $this->assertStringContainsString('Usuário quer quitar dívidas até 2027', $instructions);
    }

    public function test_agent_instructions_contain_memory_writing_guidance(): void
    {
        $agent = new FinancialAssistantAgent($this->workspace, 'conv-test-id', $this->user);

        $instructions = (string) $agent->instructions();

        $this->assertStringContainsString('create_memory', $instructions);
        $this->assertStringContainsString('update_memory', $instructions);
        $this->assertStringContainsString('duplicat', $instructions);
    }

    public function test_agent_instructions_contain_memory_scope_guardrail(): void
    {
        $agent = new FinancialAssistantAgent($this->workspace, 'conv-test-id', $this->user);

        $instructions = (string) $agent->instructions();

        $this->assertStringContainsString('NUNCA registre assuntos fora do escopo', $instructions);
        $this->assertStringContainsString('sensíveis', $instructions);
    }

    public function test_agent_instructions_contain_context_info(): void
    {
        Carbon::setTestNow('2026-11-11 10:00:00');

        try {
            $agent = new FinancialAssistantAgent($this->workspace, 'conv-test-id', $this->user);

            $instructions = (string) $agent->instructions();

            $this->assertStringContainsString($this->user->name, $instructions);
            $this->assertStringContainsString($this->workspace->name, $instructions);
            $this->assertStringContainsString('11/11/2026', $instructions);
        } finally {
            Carbon::setTestNow();
        }
    }
}
