<?php

declare(strict_types=1);

namespace Tests\Feature\Fin;

use App\Ai\Tools\QueryAccountsTool;
use App\Enums\WorkspaceRole;
use App\Models\Account;
use App\Models\User;
use App\Models\Workspace;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class QueryAccountsToolTest extends TestCase
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

    public function test_query_accounts_tool_returns_only_workspace_accounts(): void
    {
        $accountA1 = Account::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'created_by' => $this->user->id,
        ]);
        $accountA2 = Account::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'created_by' => $this->user->id,
        ]);
        $accountB = Account::factory()->create([
            'workspace_id' => $this->workspaceB->id,
            'created_by' => $this->user->id,
        ]);

        $tool = new QueryAccountsTool($this->workspaceA->uuid);

        $response = json_decode($tool->handle(new Request([])), true);

        $this->assertSame(2, $response['count']);
        $this->assertCount(2, $response['accounts']);

        $returnedUuids = array_map(fn (array $account): string => $account['uuid'], $response['accounts']);

        $this->assertContains($accountA1->uuid, $returnedUuids);
        $this->assertContains($accountA2->uuid, $returnedUuids);
        $this->assertNotContains($accountB->uuid, $returnedUuids);
    }

    public function test_query_accounts_tool_returns_shape(): void
    {
        Account::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'created_by' => $this->user->id,
            'name' => 'Nubank',
            'type' => 'checking',
            'current_balance' => 1500.50,
        ]);

        $tool = new QueryAccountsTool($this->workspaceA->uuid);

        $response = json_decode($tool->handle(new Request([])), true);

        $account = $response['accounts'][0];

        $this->assertArrayHasKey('uuid', $account);
        $this->assertArrayHasKey('name', $account);
        $this->assertArrayHasKey('type', $account);
        $this->assertArrayHasKey('current_balance', $account);
    }
}
