<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Ai\Agents\ScheduledFinancialAnalysisAgent;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Tests\TestCase;

class ScheduledFinancialAnalysisAgentTest extends TestCase
{
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);
    }

    public function test_agent_returns_structured_insights_from_fake_response(): void
    {
        ScheduledFinancialAnalysisAgent::fake([
            [
                'insights' => [
                    [
                        'type' => 'anomaly',
                        'title' => 'Gasto alto',
                        'description' => 'Gastos com alimentação 60% acima da média.',
                        'severity' => 'high',
                        'data' => null,
                    ],
                ],
            ],
        ]);

        $agent = new ScheduledFinancialAnalysisAgent($this->workspace);
        $response = $agent->prompt('Analise');

        $insights = $response['insights'];

        $this->assertCount(1, $insights);
        $this->assertSame('Gasto alto', $insights[0]['title']);
        $this->assertSame('high', $insights[0]['severity']);
    }

    public function test_agent_registers_six_tools(): void
    {
        $agent = new ScheduledFinancialAnalysisAgent($this->workspace);

        $tools = [...$agent->tools()];

        $this->assertCount(6, $tools);

        $toolNames = array_map(fn ($tool) => class_basename($tool), $tools);

        $this->assertContains('QueryTransactionsTool', $toolNames);
        $this->assertContains('QueryRecurrencesTool', $toolNames);
        $this->assertContains('CreateMemoryTool', $toolNames);
    }

    public function test_schema_contains_insights_field(): void
    {
        $schema = (new ScheduledFinancialAnalysisAgent($this->workspace))->schema(
            new JsonSchemaTypeFactory
        );

        $this->assertArrayHasKey('insights', $schema);
    }

    public function test_instructions_include_memory_map(): void
    {
        $agent = new ScheduledFinancialAnalysisAgent($this->workspace);

        $this->assertStringContainsString('Mapa de Memória', (string) $agent->instructions());
    }
}
