<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Ai\Agents\BaseAgent;
use App\Ai\Tools\QueryTransactionsTool;
use App\Enums\WorkspaceRole;
use App\Models\AgentMemory;
use App\Models\User;
use App\Models\Workspace;
use Stringable;
use Tests\TestCase;

class BaseAgentTest extends TestCase
{
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);
    }

    public function test_base_agent_injects_four_memory_tools(): void
    {
        $agent = new class($this->workspace) extends BaseAgent
        {
            protected function agentTools(): iterable
            {
                return [];
            }

            public function instructions(): Stringable|string
            {
                return "Test\n{$this->memoryMap()}";
            }
        };

        $tools = [...$agent->tools()];

        $this->assertCount(4, $tools);

        $toolNames = array_map(fn ($tool) => class_basename($tool), $tools);

        $this->assertContains('CreateMemoryTool', $toolNames);
        $this->assertContains('ReadMemoryTool', $toolNames);
        $this->assertContains('UpdateMemoryTool', $toolNames);
        $this->assertContains('DeleteMemoryTool', $toolNames);
    }

    public function test_base_agent_includes_subclass_tools(): void
    {
        $agent = new class($this->workspace) extends BaseAgent
        {
            protected function agentTools(): iterable
            {
                return [
                    new QueryTransactionsTool($this->workspace->uuid),
                ];
            }

            public function instructions(): Stringable|string
            {
                return "Test\n{$this->memoryMap()}";
            }
        };

        $tools = [...$agent->tools()];

        $this->assertCount(5, $tools);

        $toolNames = array_map(fn ($tool) => class_basename($tool), $tools);

        $this->assertContains('QueryTransactionsTool', $toolNames);
        $this->assertContains('CreateMemoryTool', $toolNames);
    }

    public function test_base_agent_injects_memory_map_into_instructions(): void
    {
        AgentMemory::factory()->for($this->workspace)->create([
            'title' => 'Primeiro insight memorizado',
        ]);
        AgentMemory::factory()->for($this->workspace)->create([
            'title' => 'Segundo padrão memorizado',
        ]);

        $agent = new class($this->workspace) extends BaseAgent
        {
            protected function agentTools(): iterable
            {
                return [];
            }

            public function instructions(): Stringable|string
            {
                return "Test\n{$this->memoryMap()}";
            }
        };

        $instructions = (string) $agent->instructions();

        $this->assertStringContainsString('Primeiro insight memorizado', $instructions);
        $this->assertStringContainsString('Segundo padrão memorizado', $instructions);
        $this->assertStringContainsString('[1]', $instructions);
        $this->assertStringContainsString('[2]', $instructions);
    }

    public function test_base_agent_uses_opencode_provider(): void
    {
        $agent = new class($this->workspace) extends BaseAgent
        {
            protected function agentTools(): iterable
            {
                return [];
            }

            public function instructions(): Stringable|string
            {
                return "Test\n{$this->memoryMap()}";
            }
        };

        $this->assertSame('opencode', $agent->provider());
    }
}
