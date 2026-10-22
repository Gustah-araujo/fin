<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Ai\Agents\ScheduledFinancialAnalysisAgent;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Services\FinancialAnalysisService;
use Tests\TestCase;

class FinancialAnalysisServiceTest extends TestCase
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

    public function test_analyze_creates_memories_and_notifies_members(): void
    {
        ScheduledFinancialAnalysisAgent::fake([
            [
                'insights' => [
                    [
                        'type' => 'anomaly',
                        'title' => 'Gasto anômalo em alimentação',
                        'description' => 'Gastos com alimentação 60% acima da média.',
                        'severity' => 'high',
                        'data' => null,
                    ],
                ],
            ],
        ]);

        $count = app(FinancialAnalysisService::class)->analyze($this->workspace);

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('agent_memories', [
            'workspace_id' => $this->workspace->id,
            'title' => 'Gasto anômalo em alimentação',
            'type' => 'anomaly',
        ]);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_analyze_skips_notification_when_no_insights(): void
    {
        ScheduledFinancialAnalysisAgent::fake([
            ['insights' => []],
        ]);

        $count = app(FinancialAnalysisService::class)->analyze($this->workspace);

        $this->assertSame(0, $count);
        $this->assertDatabaseCount('agent_memories', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_testing_route_triggers_analysis(): void
    {
        $response = $this->actingAs($this->user)->postJson(
            route('testing.financial-analysis', $this->workspace),
            [
                'insights' => [
                    [
                        'type' => 'insight',
                        'title' => 'Economia em lazer',
                        'description' => 'Gastos com lazer caíram 20% em relação à média.',
                        'severity' => 'medium',
                        'data' => null,
                    ],
                ],
            ],
        );

        $response->assertOk()->assertJson(['insights' => 1, 'notification_sent' => true]);

        $this->assertDatabaseHas('agent_memories', [
            'workspace_id' => $this->workspace->id,
            'title' => 'Economia em lazer',
            'type' => 'insight',
        ]);
        $this->assertDatabaseCount('notifications', 1);
    }
}
