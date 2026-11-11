<?php

declare(strict_types=1);

namespace Tests\Feature\Fin;

use App\Ai\Agents\FinancialAssistantAgent;
use App\Enums\FinRunStatus;
use App\Enums\WorkspaceRole;
use App\Jobs\DispatchFinAssistantJob;
use App\Jobs\TitleConversationJob;
use App\Models\FinAssistantRun;
use App\Models\User;
use App\Models\Workspace;
use App\Services\FinAssistantService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Files\StoredDocument;
use Laravel\Ai\Files\StoredImage;
use Laravel\Ai\Messages\MessageRole;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Tests\TestCase;

class FinAssistantServiceTest extends TestCase
{
    private Workspace $workspace;

    private User $user;

    private FinAssistantService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->members()->attach($this->user, ['role' => WorkspaceRole::Admin->value]);

        $this->service = app(FinAssistantService::class);
    }

    private function createRun(Conversation $conversation): FinAssistantRun
    {
        return FinAssistantRun::create([
            'uuid' => Str::orderedUuid()->toString(),
            'workspace_id' => $this->workspace->id,
            'conversation_id' => $conversation->id,
            'user_id' => $this->user->id,
            'status' => FinRunStatus::Pending,
        ]);
    }

    public function test_create_conversation_persists_participant_title_and_creator(): void
    {
        $conversation = $this->service->createConversation($this->workspace, $this->user, 'Orçamento mensal');

        $this->assertInstanceOf(Conversation::class, $conversation);
        $this->assertDatabaseHas('agent_conversations', [
            'id' => $conversation->id,
            'participant_type' => Workspace::class,
            'participant_id' => $this->workspace->id,
            'title' => 'Orçamento mensal',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_send_message_persists_user_message_and_dispatches_run(): void
    {
        Queue::fake();

        $conversation = $this->service->createConversation($this->workspace, $this->user, 'Orçamento mensal');

        $run = $this->service->sendMessage($this->workspace, $this->user, $conversation, 'Qual o meu saldo?');

        $this->assertDatabaseHas('agent_conversation_messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Qual o meu saldo?',
        ]);

        $this->assertDatabaseHas('fin_assistant_runs', [
            'uuid' => $run->uuid,
            'workspace_id' => $this->workspace->id,
            'conversation_id' => $conversation->id,
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        $this->assertSame(FinRunStatus::Pending, $run->status);

        Queue::assertPushed(DispatchFinAssistantJob::class, function (DispatchFinAssistantJob $job) use ($run): bool {
            return $job->run->is($run);
        });
    }

    public function test_title_job_dispatched_only_on_first_message(): void
    {
        Queue::fake();

        $conversation = $this->service->createConversation($this->workspace, $this->user, 'Orçamento mensal');

        $this->service->sendMessage($this->workspace, $this->user, $conversation, 'Primeira mensagem');

        Queue::assertPushed(TitleConversationJob::class, function (TitleConversationJob $job) use ($conversation): bool {
            return $job->conversationId === $conversation->id;
        });

        $this->service->sendMessage($this->workspace, $this->user, $conversation, 'Segunda mensagem');

        Queue::assertPushed(TitleConversationJob::class, 1);
    }

    public function test_run_marks_completed_and_persists_assistant_message(): void
    {
        $conversation = $this->service->createConversation($this->workspace, $this->user, 'Orçamento mensal');
        $this->service->persistUserMessage($conversation, $this->workspace, 'Qual o meu saldo?');

        $run = $this->createRun($conversation);

        FinancialAssistantAgent::fake(['Olá! Tudo bem?']);

        $this->service->run($run);

        $run->refresh();

        $this->assertSame(FinRunStatus::Completed, $run->status);
        $this->assertNotNull($run->result_message_id);

        $this->assertDatabaseHas('agent_conversation_messages', [
            'id' => $run->result_message_id,
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Olá! Tudo bem?',
        ]);
    }

    public function test_run_marks_failed_on_exception(): void
    {
        $conversation = $this->service->createConversation($this->workspace, $this->user, 'Orçamento mensal');
        $this->service->persistUserMessage($conversation, $this->workspace, 'Qual o meu saldo?');

        $run = $this->createRun($conversation);

        FinancialAssistantAgent::fake(fn () => throw new \RuntimeException('boom'));

        try {
            $this->service->run($run);
            $this->fail('Expected a RuntimeException to be thrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $run->refresh();

        $this->assertSame(FinRunStatus::Failed, $run->status);
        $this->assertSame('boom', $run->error_message);
    }

    public function test_build_history_returns_only_last_n_messages_in_order(): void
    {
        $conversation = $this->service->createConversation($this->workspace, $this->user, 'Histórico');

        for ($i = 1; $i <= 8; $i++) {
            $this->service->persistUserMessage($conversation, $this->workspace, "Mensagem {$i}");
        }

        $history = $this->service->buildHistory($conversation, 3);

        $this->assertCount(3, $history);
        $this->assertSame('Mensagem 6', $history[0]->content);
        $this->assertSame('Mensagem 7', $history[1]->content);
        $this->assertSame('Mensagem 8', $history[2]->content);
    }

    public function test_send_message_with_file_persists_attachment_metadata_and_stores_file(): void
    {
        Queue::fake();
        Storage::fake('local');

        $conversation = $this->service->createConversation($this->workspace, $this->user, 'Orçamento mensal');
        $file = UploadedFile::fake()->create('extrato.pdf', 100);

        $this->service->sendMessage($this->workspace, $this->user, $conversation, 'Analise o extrato', [$file]);

        $message = ConversationMessage::where('conversation_id', $conversation->id)
            ->where('role', MessageRole::User->value)
            ->firstOrFail();

        $this->assertCount(1, $message->attachments);

        $metadata = $message->attachments[0];

        $this->assertSame('stored-document', $metadata['type']);
        $this->assertSame('extrato.pdf', $metadata['name']);
        $this->assertSame('local', $metadata['disk']);
        $this->assertStringContainsString("fin/{$this->workspace->uuid}/", $metadata['path']);

        Storage::disk('local')->assertExists($metadata['path']);
    }

    public function test_send_message_with_image_persists_stored_image_metadata(): void
    {
        Queue::fake();
        Storage::fake('local');

        $conversation = $this->service->createConversation($this->workspace, $this->user, 'Orçamento mensal');
        $file = UploadedFile::fake()->image('nota.png');

        $this->service->sendMessage($this->workspace, $this->user, $conversation, null, [$file]);

        $message = ConversationMessage::where('conversation_id', $conversation->id)
            ->where('role', MessageRole::User->value)
            ->firstOrFail();

        $this->assertSame('stored-image', $message->attachments[0]['type']);
        $this->assertSame('nota.png', $message->attachments[0]['name']);
        $this->assertSame('', $message->content);
    }

    public function test_send_message_without_files_persists_empty_attachments(): void
    {
        Queue::fake();

        $conversation = $this->service->createConversation($this->workspace, $this->user, 'Orçamento mensal');

        $this->service->sendMessage($this->workspace, $this->user, $conversation, 'Qual o meu saldo?');

        $message = ConversationMessage::where('conversation_id', $conversation->id)
            ->where('role', MessageRole::User->value)
            ->firstOrFail();

        $this->assertSame([], $message->attachments);
    }

    public function test_run_passes_hydrated_attachments_to_agent(): void
    {
        Queue::fake();
        Storage::fake('local');

        $conversation = $this->service->createConversation($this->workspace, $this->user, 'Orçamento mensal');

        $pdf = UploadedFile::fake()->create('extrato.pdf', 100);
        $image = UploadedFile::fake()->image('nota.png');

        $run = $this->service->sendMessage($this->workspace, $this->user, $conversation, 'Analise os anexos', [$pdf, $image]);

        $captured = null;

        FinancialAssistantAgent::fake(function (string $prompt, Collection $attachments) use (&$captured) {
            $captured = ['prompt' => $prompt, 'attachments' => $attachments->all()];

            return 'Resposta falsa.';
        });

        $this->service->run($run);

        $captured = $captured ?? ['prompt' => '', 'attachments' => []];

        $this->assertSame('Analise os anexos', $captured['prompt']);
        $this->assertCount(2, $captured['attachments']);
        $this->assertInstanceOf(StoredDocument::class, $captured['attachments'][0]);
        $this->assertSame('extrato.pdf', $captured['attachments'][0]->name());
        $this->assertInstanceOf(StoredImage::class, $captured['attachments'][1]);
        $this->assertSame('nota.png', $captured['attachments'][1]->name());
    }

    public function test_run_uses_fallback_prompt_when_content_is_empty_with_attachments(): void
    {
        Queue::fake();
        Storage::fake('local');

        $conversation = $this->service->createConversation($this->workspace, $this->user, 'Orçamento mensal');

        $pdf = UploadedFile::fake()->create('extrato.pdf', 100);

        $run = $this->service->sendMessage($this->workspace, $this->user, $conversation, '', [$pdf]);

        $captured = null;

        FinancialAssistantAgent::fake(function (string $prompt, Collection $attachments) use (&$captured) {
            $captured = ['prompt' => $prompt, 'attachments' => $attachments->all()];

            return 'Resposta falsa.';
        });

        $this->service->run($run);

        $captured = $captured ?? ['prompt' => '', 'attachments' => []];

        $this->assertSame('Analise o(s) arquivo(s) em anexo.', $captured['prompt']);
        $this->assertCount(1, $captured['attachments']);
        $this->assertSame('extrato.pdf', $captured['attachments'][0]->name());
    }

    public function test_run_without_attachments_prompts_original_content_with_no_attachments(): void
    {
        $conversation = $this->service->createConversation($this->workspace, $this->user, 'Orçamento mensal');
        $this->service->persistUserMessage($conversation, $this->workspace, 'Qual o meu saldo?');

        $run = $this->createRun($conversation);

        $captured = null;

        FinancialAssistantAgent::fake(function (string $prompt, Collection $attachments) use (&$captured) {
            $captured = ['prompt' => $prompt, 'attachments' => $attachments->all()];

            return 'Resposta falsa.';
        });

        $this->service->run($run);

        $captured = $captured ?? ['prompt' => '', 'attachments' => []];

        $this->assertSame('Qual o meu saldo?', $captured['prompt']);
        $this->assertSame([], $captured['attachments']);
    }
}
