<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Ai\Agents\FinancialAssistantAgent;
use App\Ai\Agents\ScheduledFinancialAnalysisAgent;
use App\Ai\Tools\Memory\CreateMemoryTool;
use App\Enums\WorkspaceRole;
use App\Models\AgentMemory;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AgentMemoryService;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class AgentMemoryCrossAgentTest extends TestCase
{
    private Workspace $workspace;

    private Workspace $otherWorkspace;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->otherWorkspace = Workspace::factory()->create();
        $this->workspace->members()->attach($this->user, ['role' => WorkspaceRole::Admin->value]);
    }

    public function test_memory_created_by_insights_agent_is_injected_into_fin_context(): void
    {
        app(AgentMemoryService::class)->create($this->workspace, [
            'title' => 'Gasto anômalo em alimentação',
            'content' => 'Gastos com alimentação 60% acima da média.',
            'type' => 'anomaly',
            'source' => ScheduledFinancialAnalysisAgent::class,
        ]);

        $agent = new FinancialAssistantAgent($this->workspace, 'conv-test-id', $this->user);

        $instructions = (string) $agent->instructions();

        $this->assertStringContainsString('Gasto anômalo em alimentação', $instructions);
        $this->assertStringContainsString('Gastos com alimentação 60% acima da média.', $instructions);
    }

    public function test_memory_created_by_fin_tool_is_available_to_insights_agent(): void
    {
        $tool = new CreateMemoryTool($this->workspace->uuid);

        $request = new Request([
            'title' => 'Prefiro registrar delivery como Lazer',
            'content' => 'Usuário categoriza gastos de delivery como lazer.',
        ]);

        $response = json_decode($tool->handle($request), true);

        $this->assertNotEmpty($response['uuid']);
        $this->assertSame('Prefiro registrar delivery como Lazer', $response['title']);

        $this->assertDatabaseHas('agent_memories', [
            'uuid' => $response['uuid'],
            'title' => 'Prefiro registrar delivery como Lazer',
            'workspace_id' => $this->workspace->id,
        ]);

        $agent = new ScheduledFinancialAnalysisAgent($this->workspace);

        $this->assertStringContainsString(
            'Prefiro registrar delivery como Lazer',
            (string) $agent->instructions()
        );
    }

    public function test_fin_context_does_not_leak_memories_from_other_workspaces(): void
    {
        AgentMemory::factory()->for($this->otherWorkspace)->create([
            'title' => 'Memória privada do outro workspace',
            'content' => 'Conteúdo exclusivo do outro workspace.',
        ]);

        $finForWorkspace = new FinancialAssistantAgent($this->workspace, 'conv-test-id', $this->user);

        $this->assertStringNotContainsString(
            'Memória privada do outro workspace',
            (string) $finForWorkspace->instructions()
        );

        $finForOtherWorkspace = new FinancialAssistantAgent($this->otherWorkspace, 'conv-test-id', $this->user);

        $this->assertStringContainsString(
            'Memória privada do outro workspace',
            (string) $finForOtherWorkspace->instructions()
        );
    }

    public function test_fin_instructions_respect_memory_prompt_limit(): void
    {
        foreach (range(1, 25) as $index) {
            $memory = AgentMemory::factory()->for($this->workspace)->create([
                'title' => sprintf('Memory %02d', $index),
            ]);

            AgentMemory::query()->where('id', $memory->id)->update([
                'created_at' => now()->subSeconds(100 - $index),
            ]);
        }

        $agent = new FinancialAssistantAgent($this->workspace, 'conv-test-id', $this->user);

        $instructions = (string) $agent->instructions();

        $this->assertStringContainsString('Memory 25', $instructions);
        $this->assertStringNotContainsString('Memory 01', $instructions);
    }
}
