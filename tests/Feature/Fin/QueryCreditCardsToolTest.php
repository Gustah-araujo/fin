<?php

declare(strict_types=1);

namespace Tests\Feature\Fin;

use App\Ai\Tools\QueryCreditCardsTool;
use App\Enums\WorkspaceRole;
use App\Models\CreditCard;
use App\Models\User;
use App\Models\Workspace;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class QueryCreditCardsToolTest extends TestCase
{
    private Workspace $workspaceA;

    private Workspace $workspaceB;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspaceA = Workspace::factory()->create();
        $this->workspaceA->members()->attach($this->user, ['role' => WorkspaceRole::Admin->value]);

        $otherUser = User::factory()->create();
        $this->workspaceB = Workspace::factory()->create();
        $this->workspaceB->members()->attach($otherUser, ['role' => WorkspaceRole::Admin->value]);
    }

    public function test_query_credit_cards_tool_returns_only_workspace_cards(): void
    {
        $cardA1 = CreditCard::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'created_by' => $this->user->id,
        ]);
        $cardA2 = CreditCard::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'created_by' => $this->user->id,
        ]);
        $cardB = CreditCard::factory()->create([
            'workspace_id' => $this->workspaceB->id,
            'created_by' => $this->user->id,
        ]);

        $tool = new QueryCreditCardsTool($this->workspaceA->uuid);

        $response = json_decode($tool->handle(new Request([])), true);

        $this->assertSame(2, $response['count']);
        $this->assertCount(2, $response['credit_cards']);

        $returnedUuids = array_map(fn (array $card): string => $card['uuid'], $response['credit_cards']);

        $this->assertContains($cardA1->uuid, $returnedUuids);
        $this->assertContains($cardA2->uuid, $returnedUuids);
        $this->assertNotContains($cardB->uuid, $returnedUuids);
    }

    public function test_query_credit_cards_tool_returns_shape(): void
    {
        CreditCard::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'created_by' => $this->user->id,
        ]);

        $tool = new QueryCreditCardsTool($this->workspaceA->uuid);

        $response = json_decode($tool->handle(new Request([])), true);

        $card = $response['credit_cards'][0];

        $this->assertArrayHasKey('uuid', $card);
        $this->assertArrayHasKey('name', $card);
        $this->assertArrayHasKey('credit_limit', $card);
        $this->assertArrayHasKey('available_limit', $card);
        $this->assertArrayHasKey('closing_day', $card);
        $this->assertArrayHasKey('due_day', $card);
    }
}
