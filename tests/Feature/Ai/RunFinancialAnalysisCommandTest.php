<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Ai\Agents\ScheduledFinancialAnalysisAgent;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Notifications\DatabaseNotification;
use RuntimeException;
use Tests\TestCase;

class RunFinancialAnalysisCommandTest extends TestCase
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

    public function test_command_creates_memories_from_insights(): void
    {
        ScheduledFinancialAnalysisAgent::fake([
            [
                'insights' => [
                    [
                        'type' => 'anomaly',
                        'title' => 'Gasto anômalo',
                        'description' => 'Descrição do gasto',
                        'severity' => 'high',
                        'data' => null,
                    ],
                ],
            ],
        ]);

        $this->artisan('agent:financial-analysis')->assertSuccessful();

        $this->assertDatabaseHas('agent_memories', [
            'workspace_id' => $this->workspace->id,
            'title' => 'Gasto anômalo',
            'type' => 'anomaly',
        ]);
    }

    public function test_command_sends_database_notification_to_members(): void
    {
        ScheduledFinancialAnalysisAgent::fake([
            [
                'insights' => [
                    [
                        'type' => 'insight',
                        'title' => 'Tendência de gastos',
                        'description' => 'Gastos subiram 10%',
                        'severity' => 'medium',
                        'data' => null,
                    ],
                ],
            ],
        ]);

        $this->artisan('agent:financial-analysis')->assertSuccessful();

        $notification = DatabaseNotification::where('notifiable_id', $this->user->id)->first();

        $this->assertNotNull($notification);
        $this->assertSame($this->workspace->uuid, $notification->data['workspace_uuid']);
        $this->assertSame('Análise financeira concluída', $notification->data['title']);
    }

    public function test_command_skips_notification_when_no_insights(): void
    {
        ScheduledFinancialAnalysisAgent::fake([
            ['insights' => []],
        ]);

        $this->artisan('agent:financial-analysis')->assertSuccessful();

        $this->assertDatabaseCount('agent_memories', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_command_continues_after_agent_failure(): void
    {
        ScheduledFinancialAnalysisAgent::fake(fn () => throw new RuntimeException('AI failure'));

        $this->artisan('agent:financial-analysis')->assertSuccessful();

        $this->assertDatabaseCount('agent_memories', 0);
    }

    public function test_command_smoke_runs_without_error(): void
    {
        ScheduledFinancialAnalysisAgent::fake([
            ['insights' => []],
        ]);

        $this->artisan('agent:financial-analysis')->assertSuccessful();
    }
}
