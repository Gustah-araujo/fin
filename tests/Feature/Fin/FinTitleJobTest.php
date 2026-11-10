<?php

declare(strict_types=1);

namespace Tests\Feature\Fin;

use App\Ai\Agents\FinTitleAgent;
use App\Enums\WorkspaceRole;
use App\Jobs\TitleConversationJob;
use App\Models\User;
use App\Models\Workspace;
use App\Services\FinAssistantService;
use Tests\TestCase;

class FinTitleJobTest extends TestCase
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

    public function test_job_sets_title_from_first_user_message(): void
    {
        $conversation = $this->service->createConversation($this->workspace, $this->user, 'Título antigo');
        $this->service->persistUserMessage($conversation, $this->workspace, 'Como está o meu orçamento mensal?');

        FinTitleAgent::fake(['Orçamento mensal']);

        (new TitleConversationJob($conversation->id, $this->workspace))->handle();

        $this->assertDatabaseHas('agent_conversations', [
            'id' => $conversation->id,
            'title' => 'Orçamento mensal',
        ]);
    }

    public function test_job_keeps_title_when_conversation_has_no_messages(): void
    {
        $conversation = $this->service->createConversation($this->workspace, $this->user, 'Título original');

        FinTitleAgent::fake(['Não deve ser usado']);

        (new TitleConversationJob($conversation->id, $this->workspace))->handle();

        $this->assertDatabaseHas('agent_conversations', [
            'id' => $conversation->id,
            'title' => 'Título original',
        ]);
    }
}
