<?php

declare(strict_types=1);

namespace Tests\Feature\Fin;

use App\Enums\WorkspaceRole;
use App\Jobs\DispatchFinAssistantJob;
use App\Models\User;
use App\Models\Workspace;
use App\Services\FinAssistantService;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FinMessageControllerTest extends TestCase
{
    private User $user;

    private Workspace $workspace;

    private FinAssistantService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->members()->attach($this->user, ['role' => WorkspaceRole::Admin->value]);

        $this->service = app(FinAssistantService::class);
    }

    public function test_store_persists_message_and_dispatches_run(): void
    {
        Queue::fake();

        $conversation = $this->service->createConversation($this->workspace, $this->user, 'Nova conversa');

        $response = $this->actingAs($this->user)
            ->postJson(route('fin.conversations.messages.store', [$this->workspace, $conversation]), ['content' => 'Olá']);

        $response->assertStatus(201);
        $response->assertJsonPath('status', 'pending');

        $this->assertDatabaseHas('agent_conversation_messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Olá',
        ]);

        Queue::assertPushed(DispatchFinAssistantJob::class);
    }

    public function test_store_returns_404_for_other_workspace_conversation(): void
    {
        $otherWorkspace = Workspace::factory()->create();
        $otherWorkspace->members()->attach($this->user, ['role' => WorkspaceRole::Admin->value]);
        $foreign = $this->service->createConversation($otherWorkspace, $this->user, 'Estrangeira');

        $response = $this->actingAs($this->user)
            ->postJson(route('fin.conversations.messages.store', [$this->workspace, $foreign]), ['content' => 'Olá']);

        $response->assertStatus(404);
    }

    public function test_store_validation_requires_content(): void
    {
        $conversation = $this->service->createConversation($this->workspace, $this->user, 'Nova conversa');

        $response = $this->actingAs($this->user)
            ->postJson(route('fin.conversations.messages.store', [$this->workspace, $conversation]), ['content' => '']);

        $response->assertStatus(422);
    }
}
