<?php

declare(strict_types=1);

namespace Tests\Feature\Fin;

use App\Enums\FinRunStatus;
use App\Enums\WorkspaceRole;
use App\Models\FinAssistantRun;
use App\Models\User;
use App\Models\Workspace;
use App\Services\FinAssistantService;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinRunControllerTest extends TestCase
{
    private User $user;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->members()->attach($this->user, ['role' => WorkspaceRole::Admin->value]);
    }

    private function createRun(Workspace $workspace, User $user): FinAssistantRun
    {
        $conversation = app(FinAssistantService::class)->createConversation($workspace, $user, 'X');

        return FinAssistantRun::create([
            'uuid' => Str::orderedUuid()->toString(),
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'status' => FinRunStatus::Pending,
        ]);
    }

    public function test_show_returns_run_status(): void
    {
        $run = $this->createRun($this->workspace, $this->user);

        $response = $this->actingAs($this->user)
            ->getJson(route('fin.runs.show', [$this->workspace, $run]));

        $response->assertOk();
        $response->assertJsonPath('status', 'pending');
    }

    public function test_show_returns_403_for_other_workspace_run(): void
    {
        $otherWorkspace = Workspace::factory()->create();
        $otherWorkspace->members()->attach($this->user, ['role' => WorkspaceRole::Admin->value]);
        $run = $this->createRun($otherWorkspace, $this->user);

        $response = $this->actingAs($this->user)
            ->getJson(route('fin.runs.show', [$this->workspace, $run]));

        $response->assertStatus(403);
    }
}
