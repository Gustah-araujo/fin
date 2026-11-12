<?php

declare(strict_types=1);

namespace Tests\Feature\Fin;

use App\Enums\WorkspaceRole;
use App\Jobs\TitleConversationJob;
use App\Models\User;
use App\Models\Workspace;
use App\Services\FinAssistantService;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Models\Conversation;
use Tests\TestCase;

class FinConversationControllerTest extends TestCase
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

    private function conversation(): Conversation
    {
        return $this->service->createConversation($this->workspace, $this->user, 'Orçamento');
    }

    public function test_store_creates_conversation_from_first_message(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->user)
            ->postJson(route('fin.conversations.store', $this->workspace), ['content' => 'Qual o meu saldo?']);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'conversation' => ['id', 'title', 'created_at', 'updated_at'],
            'run' => ['id', 'status', 'created_at'],
        ]);
        $response->assertJsonPath('conversation.title', 'Nova conversa');
        $response->assertJsonPath('run.status', 'pending');

        $conversationId = $response->json('conversation.id');

        $this->assertDatabaseHas('agent_conversations', [
            'id' => $conversationId,
            'participant_type' => Workspace::class,
            'participant_id' => $this->workspace->id,
            'title' => 'Nova conversa',
        ]);

        $this->assertDatabaseHas('agent_conversation_messages', [
            'conversation_id' => $conversationId,
            'role' => 'user',
            'content' => 'Qual o meu saldo?',
        ]);

        Queue::assertPushed(TitleConversationJob::class, function (TitleConversationJob $job) use ($conversationId): bool {
            return $job->conversationId === $conversationId;
        });
    }

    public function test_store_returns_422_without_content_or_attachments(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('fin.conversations.store', $this->workspace), []);

        $response->assertStatus(422);
        $this->assertDatabaseCount('agent_conversations', 0);
    }

    public function test_store_denied_for_non_member(): void
    {
        $outsider = User::factory()->create();

        $response = $this->actingAs($outsider)
            ->postJson(route('fin.conversations.store', $this->workspace), ['content' => 'Qual o meu saldo?']);

        $response->assertStatus(403);
    }

    public function test_index_returns_only_workspace_conversations(): void
    {
        $this->service->createConversation($this->workspace, $this->user, 'A1');
        $this->service->createConversation($this->workspace, $this->user, 'A2');

        $otherWorkspace = Workspace::factory()->create();
        $otherWorkspace->members()->attach($this->user, ['role' => WorkspaceRole::Admin->value]);
        $this->service->createConversation($otherWorkspace, $this->user, 'B1');

        $response = $this->actingAs($this->user)
            ->getJson(route('fin.conversations.index', $this->workspace));

        $response->assertOk();
        $response->assertJsonCount(2);
    }

    public function test_show_returns_conversation_with_messages(): void
    {
        $conversation = $this->conversation();
        $this->service->persistUserMessage($conversation, $this->workspace, 'Olá!');

        $response = $this->actingAs($this->user)
            ->getJson(route('fin.conversations.show', [$this->workspace, $conversation]));

        $response->assertOk();
        $response->assertJsonPath('messages.0.role', 'user');
    }

    public function test_show_returns_404_for_other_workspace_conversation(): void
    {
        $otherWorkspace = Workspace::factory()->create();
        $otherWorkspace->members()->attach($this->user, ['role' => WorkspaceRole::Admin->value]);
        $foreign = $this->service->createConversation($otherWorkspace, $this->user, 'Estrangeira');

        $response = $this->actingAs($this->user)
            ->getJson(route('fin.conversations.show', [$this->workspace, $foreign]));

        $response->assertStatus(404);
    }

    public function test_update_renames_conversation(): void
    {
        $conversation = $this->conversation();

        $response = $this->actingAs($this->user)
            ->putJson(route('fin.conversations.update', [$this->workspace, $conversation]), ['title' => 'Novo título']);

        $response->assertOk();
        $response->assertJsonPath('title', 'Novo título');
    }

    public function test_destroy_deletes_conversation(): void
    {
        $conversation = $this->conversation();

        $response = $this->actingAs($this->user)
            ->deleteJson(route('fin.conversations.destroy', [$this->workspace, $conversation]));

        $response->assertStatus(204);
        $this->assertDatabaseMissing('agent_conversations', ['id' => $conversation->id]);
    }
}
