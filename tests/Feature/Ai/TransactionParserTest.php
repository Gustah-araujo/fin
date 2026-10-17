<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Ai\Agents\TransactionParser;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Tests\TestCase;

class TransactionParserTest extends TestCase
{
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);
    }

    public function test_agent_returns_structured_transactions_from_fake_response(): void
    {
        TransactionParser::fake([
            [
                'transactions' => [
                    [
                        'description' => 'Supermercado XYZ',
                        'value' => 150.00,
                        'date' => '2026-09-01',
                        'type' => 'debit',
                        'category_name' => 'Alimentação',
                        'category_uuid' => null,
                        'tags' => ['alimentação'],
                        'is_duplicate' => false,
                    ],
                ],
            ],
        ]);

        $agent = new TransactionParser($this->workspace);
        $response = $agent->prompt('Extraia as transações');

        $transactions = $response['transactions'];

        $this->assertCount(1, $transactions);
        $this->assertSame('Supermercado XYZ', $transactions[0]['description']);
        $this->assertSame(150.00, $transactions[0]['value']);
        $this->assertSame('2026-09-01', $transactions[0]['date']);
        $this->assertSame('debit', $transactions[0]['type']);
        $this->assertSame('Alimentação', $transactions[0]['category_name']);
        $this->assertNull($transactions[0]['category_uuid']);
        $this->assertSame(['alimentação'], $transactions[0]['tags']);
        $this->assertFalse($transactions[0]['is_duplicate']);
    }

    public function test_agent_propagates_is_duplicate_true_from_fake_response(): void
    {
        TransactionParser::fake([
            [
                'transactions' => [
                    [
                        'description' => 'Netflix',
                        'value' => 39.90,
                        'date' => '2026-09-05',
                        'type' => 'debit',
                        'category_name' => 'Lazer',
                        'category_uuid' => 'uuid-here',
                        'tags' => ['streaming'],
                        'is_duplicate' => true,
                    ],
                ],
            ],
        ]);

        $agent = new TransactionParser($this->workspace);
        $response = $agent->prompt('Extraia');

        $this->assertTrue($response['transactions'][0]['is_duplicate']);
        $this->assertSame('uuid-here', $response['transactions'][0]['category_uuid']);
    }

    public function test_agent_registers_four_tools_when_workspace_is_provided(): void
    {
        $agent = new TransactionParser($this->workspace);

        $tools = [...$agent->tools()];

        $this->assertCount(4, $tools);

        $toolNames = array_map(fn ($tool) => class_basename($tool), $tools);

        $this->assertContains('QueryTransactionsTool', $toolNames);
        $this->assertContains('QueryCategoriesTool', $toolNames);
        $this->assertContains('QueryRecurrencesTool', $toolNames);
        $this->assertContains('QueryTagsTool', $toolNames);
    }

    public function test_agent_has_no_tools_when_workspace_is_null(): void
    {
        $agent = new TransactionParser(null);

        $this->assertCount(0, [...$agent->tools()]);
    }

    public function test_fake_generates_data_from_schema_when_no_response_provided(): void
    {
        TransactionParser::fake();

        $agent = new TransactionParser($this->workspace);
        $response = $agent->prompt('Extraia as transações');

        $data = $response instanceof StructuredAgentResponse
            ? $response->structured
            : $response;

        $this->assertIsArray($data);
        $this->assertArrayHasKey('transactions', $data);
        $this->assertNotEmpty($data['transactions']);
    }

    public function test_schema_contains_transactions_field(): void
    {
        $schema = (new TransactionParser($this->workspace))->schema(
            new JsonSchemaTypeFactory
        );

        $this->assertArrayHasKey('transactions', $schema);
    }
}
