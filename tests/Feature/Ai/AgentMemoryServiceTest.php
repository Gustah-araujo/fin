<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Enums\MemoryType;
use App\Enums\WorkspaceRole;
use App\Models\AgentMemory;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AgentMemoryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AgentMemoryServiceTest extends TestCase
{
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);
    }

    public function test_agent_memories_table_exists_with_correct_columns(): void
    {
        $this->assertTrue(Schema::hasTable('agent_memories'));

        foreach (['uuid', 'workspace_id', 'title', 'content', 'type', 'source', 'expires_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('agent_memories', $column), "Missing column: {$column}");
        }
    }

    public function test_memory_can_be_created_and_casts_type_to_enum(): void
    {
        $memory = AgentMemory::factory()->for($this->workspace)->create(['type' => 'anomaly']);

        $this->assertSame(MemoryType::Anomaly, $memory->type);
        $this->assertNotEmpty($memory->uuid);
    }

    public function test_workspace_has_many_agent_memories_relationship(): void
    {
        AgentMemory::factory()->count(2)->for($this->workspace)->create();

        $this->assertSame(2, $this->workspace->agentMemories()->count());
    }

    public function test_service_create_is_scoped_to_workspace(): void
    {
        $memory = (new AgentMemoryService)->create($this->workspace, [
            'title' => 'X',
            'content' => 'Y',
            'type' => 'insight',
        ]);

        $this->assertDatabaseHas('agent_memories', [
            'id' => $memory->id,
            'workspace_id' => $this->workspace->id,
            'title' => 'X',
        ]);
    }

    public function test_service_list_returns_only_active_memories(): void
    {
        AgentMemory::factory()->for($this->workspace)->create();
        AgentMemory::factory()->for($this->workspace)->create(['expires_at' => now()->subDay()]);

        $memories = (new AgentMemoryService)->list($this->workspace);

        $this->assertCount(1, $memories);
    }

    public function test_service_find_returns_memory_by_uuid_scoped(): void
    {
        $memory = AgentMemory::factory()->for($this->workspace)->create(['title' => 'Found memory']);

        $found = (new AgentMemoryService)->find($this->workspace, $memory->uuid);

        $this->assertSame('Found memory', $found->title);

        $otherWorkspace = Workspace::factory()->create();

        $this->expectException(ModelNotFoundException::class);

        (new AgentMemoryService)->find($otherWorkspace, $memory->uuid);
    }

    public function test_service_update_modifies_memory(): void
    {
        $memory = AgentMemory::factory()->for($this->workspace)->create();

        $updated = (new AgentMemoryService)->update($memory, [
            'title' => 'Updated title',
            'type' => MemoryType::Pattern->value,
        ]);

        $this->assertSame('Updated title', $updated->title);
        $this->assertSame(MemoryType::Pattern, $updated->type);
    }

    public function test_service_delete_removes_memory(): void
    {
        $memory = AgentMemory::factory()->for($this->workspace)->create();

        (new AgentMemoryService)->delete($memory);

        $this->assertSame(0, $this->workspace->agentMemories()->count());
    }

    public function test_format_for_prompt_returns_indexed_map(): void
    {
        AgentMemory::factory()->for($this->workspace)->create(['title' => 'First title']);
        AgentMemory::factory()->for($this->workspace)->create(['title' => 'Second title']);

        $prompt = (new AgentMemoryService)->formatForPrompt($this->workspace);

        $this->assertStringContainsString('[1]', $prompt);
        $this->assertStringContainsString('[2]', $prompt);
        $this->assertStringContainsString('First title', $prompt);
        $this->assertStringContainsString('Second title', $prompt);
    }

    public function test_format_for_prompt_returns_placeholder_when_empty(): void
    {
        $prompt = (new AgentMemoryService)->formatForPrompt($this->workspace);

        $this->assertSame('Nenhuma memória registrada.', $prompt);
    }
}
