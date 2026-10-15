<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Ai\Agents\TransactionParser;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Tests\TestCase;

class TransactionParserAgentTest extends TestCase
{
    public function test_agent_instructions_contain_extraction_guidance(): void
    {
        $agent = new TransactionParser;
        $instructions = (string) $agent->instructions();

        $this->assertStringContainsString('transações financeiras', $instructions);
        $this->assertStringContainsString('debit', $instructions);
    }

    public function test_agent_schema_returns_expected_structure(): void
    {
        $agent = new TransactionParser;
        $schema = $agent->schema(new JsonSchemaTypeFactory);

        $this->assertArrayHasKey('transactions', $schema);
    }

    public function test_agent_uses_opencode_provider(): void
    {
        $agent = new TransactionParser;
        $this->assertSame('opencode', $agent->provider());
    }
}
