<?php

declare(strict_types=1);

namespace Tests\Feature\Fin;

use App\Ai\Agents\FinTitleAgent;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class FinTitleAgentTest extends TestCase
{
    private Workspace $workspace;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->members()->attach($this->user, ['role' => WorkspaceRole::Admin->value]);
    }

    public function test_agent_uses_configured_title_model(): void
    {
        $agent = new FinTitleAgent($this->workspace);

        $this->assertSame(config('ai.fin.title_model'), $agent->model());
    }

    public function test_instructions_ask_for_a_short_title(): void
    {
        $agent = new FinTitleAgent($this->workspace);

        $instructions = (string) $agent->instructions();

        $this->assertStringContainsString('título', $instructions);
        $this->assertStringContainsString('60', $instructions);
        $this->assertStringContainsString('aspas', $instructions);
    }
}
