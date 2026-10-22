<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Ai\Tools\Memory\CreateMemoryTool;
use App\Ai\Tools\Memory\DeleteMemoryTool;
use App\Ai\Tools\Memory\ReadMemoryTool;
use App\Ai\Tools\Memory\UpdateMemoryTool;
use App\Models\AgentMemory;
use App\Models\Workspace;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class MemoryToolTest extends TestCase
{
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
    }

    public function test_create_memory_tool_creates_memory(): void
    {
        $tool = new CreateMemoryTool($this->workspace->uuid);

        $request = new Request([
            'title' => 'Gasto inesperado em streaming',
            'content' => 'O gasto com streaming aumentou 40% este mês.',
            'type' => 'anomaly',
            'source' => 'scheduled-analysis',
        ]);

        $response = json_decode($tool->handle($request), true);

        $this->assertNotEmpty($response['uuid']);
        $this->assertSame('Gasto inesperado em streaming', $response['title']);
        $this->assertSame('anomaly', $response['type']);

        $this->assertDatabaseHas('agent_memories', [
            'uuid' => $response['uuid'],
            'title' => 'Gasto inesperado em streaming',
            'workspace_id' => $this->workspace->id,
        ]);
    }

    public function test_create_memory_tool_defaults_type_to_insight(): void
    {
        $tool = new CreateMemoryTool($this->workspace->uuid);

        $request = new Request([
            'title' => 'Memória sem tipo',
            'content' => 'Conteúdo da memória.',
        ]);

        $response = json_decode($tool->handle($request), true);

        $this->assertSame('insight', $response['type']);
    }

    public function test_read_memory_tool_returns_all(): void
    {
        AgentMemory::factory()->count(2)->for($this->workspace)->create();

        $tool = new ReadMemoryTool($this->workspace->uuid);

        $response = json_decode($tool->handle(new Request([])), true);

        $this->assertSame(2, $response['count']);
        $this->assertCount(2, $response['memories']);
        $this->assertArrayHasKey('uuid', $response['memories'][0]);
        $this->assertArrayHasKey('title', $response['memories'][0]);
        $this->assertArrayHasKey('content', $response['memories'][0]);
        $this->assertArrayHasKey('type', $response['memories'][0]);
        $this->assertArrayHasKey('source', $response['memories'][0]);
    }

    public function test_read_memory_tool_returns_single_by_uuid(): void
    {
        $memory = AgentMemory::factory()->for($this->workspace)->create([
            'title' => 'Memória específica',
        ]);

        $tool = new ReadMemoryTool($this->workspace->uuid);

        $response = json_decode($tool->handle(new Request(['uuid' => $memory->uuid])), true);

        $this->assertSame(1, $response['count']);
        $this->assertCount(1, $response['memories']);
        $this->assertSame('Memória específica', $response['memories'][0]['title']);
        $this->assertSame($memory->uuid, $response['memories'][0]['uuid']);
    }

    public function test_update_memory_tool_updates_fields(): void
    {
        $memory = AgentMemory::factory()->for($this->workspace)->create([
            'title' => 'Título antigo',
            'content' => 'Conteúdo antigo.',
            'type' => 'insight',
        ]);

        $tool = new UpdateMemoryTool($this->workspace->uuid);

        $request = new Request([
            'uuid' => $memory->uuid,
            'title' => 'Título atualizado',
            'content' => 'Conteúdo atualizado.',
            'type' => 'pattern',
        ]);

        $response = json_decode($tool->handle($request), true);

        $this->assertSame($memory->uuid, $response['uuid']);
        $this->assertSame('Título atualizado', $response['title']);
        $this->assertSame('pattern', $response['type']);

        $memory->refresh();

        $this->assertSame('Título atualizado', $memory->title);
        $this->assertSame('Conteúdo atualizado.', $memory->content);
        $this->assertSame('pattern', $memory->type->value);
    }

    public function test_delete_memory_tool_deletes_memory(): void
    {
        $memory = AgentMemory::factory()->for($this->workspace)->create();

        $tool = new DeleteMemoryTool($this->workspace->uuid);

        $response = json_decode($tool->handle(new Request(['uuid' => $memory->uuid])), true);

        $this->assertSame(['deleted' => $memory->uuid], $response);
        $this->assertDatabaseCount('agent_memories', 0);
    }
}
