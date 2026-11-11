<?php

declare(strict_types=1);

namespace Tests\Feature\Fin;

use App\Enums\WorkspaceRole;
use App\Jobs\DispatchFinAssistantJob;
use App\Models\User;
use App\Models\Workspace;
use App\Services\FinAssistantService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Messages\MessageRole;
use Laravel\Ai\Models\ConversationMessage;
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

        $message = ConversationMessage::where('conversation_id', $conversation->id)
            ->where('role', MessageRole::User->value)
            ->firstOrFail();

        $this->assertSame([], $message->attachments);

        Queue::assertPushed(DispatchFinAssistantJob::class);
    }

    public function test_store_persists_attachment_metadata_and_stores_file_on_disk(): void
    {
        Queue::fake();
        Storage::fake('local');

        $conversation = $this->service->createConversation($this->workspace, $this->user, 'Orçamento mensal');
        $file = UploadedFile::fake()->image('nota.png');

        $response = $this->actingAs($this->user)
            ->post(route('fin.conversations.messages.store', [$this->workspace, $conversation]), [
                'content' => 'Analise a nota',
                'attachments' => [$file],
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('status', 'pending');

        $message = ConversationMessage::where('conversation_id', $conversation->id)
            ->where('role', MessageRole::User->value)
            ->firstOrFail();

        $this->assertCount(1, $message->attachments);

        $metadata = $message->attachments[0];

        $this->assertSame('nota.png', $metadata['name']);
        $this->assertSame('local', $metadata['disk']);
        $this->assertStringContainsString("fin/{$this->workspace->uuid}/", $metadata['path']);

        Storage::disk('local')->assertExists($metadata['path']);

        Queue::assertPushed(DispatchFinAssistantJob::class);
    }

    public function test_store_serializes_multiple_attachments_of_different_types(): void
    {
        Queue::fake();
        Storage::fake('local');

        $conversation = $this->service->createConversation($this->workspace, $this->user, 'Orçamento mensal');

        $response = $this->actingAs($this->user)
            ->post(route('fin.conversations.messages.store', [$this->workspace, $conversation]), [
                'attachments' => [
                    UploadedFile::fake()->create('extrato.pdf', 100),
                    UploadedFile::fake()->image('nota.png'),
                ],
            ]);

        $response->assertStatus(201);

        $message = ConversationMessage::where('conversation_id', $conversation->id)
            ->where('role', MessageRole::User->value)
            ->firstOrFail();

        $this->assertCount(2, $message->attachments);
        $this->assertSame('stored-document', $message->attachments[0]['type']);
        $this->assertSame('extrato.pdf', $message->attachments[0]['name']);
        $this->assertSame('stored-image', $message->attachments[1]['type']);
        $this->assertSame('nota.png', $message->attachments[1]['name']);
    }

    public function test_show_exposes_attachment_structure_without_internal_metadata(): void
    {
        Queue::fake();
        Storage::fake('local');

        $conversation = $this->service->createConversation($this->workspace, $this->user, 'Orçamento mensal');

        $this->actingAs($this->user)
            ->post(route('fin.conversations.messages.store', [$this->workspace, $conversation]), [
                'content' => 'Analise os arquivos',
                'attachments' => [
                    UploadedFile::fake()->image('nota.png'),
                    UploadedFile::fake()->create('extrato.pdf', 100),
                ],
            ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('fin.conversations.show', [$this->workspace, $conversation]));

        $response->assertOk();
        $response->assertJsonStructure([
            'messages' => [
                ['attachments' => [['name', 'kind']]],
            ],
        ]);
        $response->assertJsonPath('messages.0.attachments.0.name', 'nota.png');
        $response->assertJsonPath('messages.0.attachments.0.kind', 'image');
        $response->assertJsonPath('messages.0.attachments.1.name', 'extrato.pdf');
        $response->assertJsonPath('messages.0.attachments.1.kind', 'document');

        $this->assertStringNotContainsString('"path"', $response->getContent());
        $this->assertStringNotContainsString('"disk"', $response->getContent());
    }

    public function test_show_serializes_empty_attachments_field(): void
    {
        Queue::fake();

        $conversation = $this->service->createConversation($this->workspace, $this->user, 'Nova conversa');

        $this->actingAs($this->user)
            ->post(route('fin.conversations.messages.store', [$this->workspace, $conversation]), [
                'content' => 'Olá',
            ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('fin.conversations.show', [$this->workspace, $conversation]));

        $response->assertOk();
        $response->assertJsonPath('messages.0.attachments', []);
    }

    public function test_store_returns_422_without_content_and_attachments(): void
    {
        $conversation = $this->service->createConversation($this->workspace, $this->user, 'Nova conversa');

        $response = $this->actingAs($this->user)
            ->post(route('fin.conversations.messages.store', [$this->workspace, $conversation]), []);

        $response->assertStatus(422);
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
