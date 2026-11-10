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
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;
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
}
