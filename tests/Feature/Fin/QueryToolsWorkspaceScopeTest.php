<?php

declare(strict_types=1);

namespace Tests\Feature\Fin;

use App\Ai\Tools\QueryCategoriesTool;
use App\Ai\Tools\QueryRecurrencesTool;
use App\Ai\Tools\QueryTagsTool;
use App\Ai\Tools\QueryTransactionsTool;
use App\Enums\WorkspaceRole;
use App\Models\Account;
use App\Models\Category;
use App\Models\Recurrence;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class QueryToolsWorkspaceScopeTest extends TestCase
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

    private function categoryFor(Workspace $workspace): Category
    {
        return Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $this->user->id,
            'type' => 'expense',
        ]);
    }

    public function test_query_transactions_tool_finds_workspace_transactions(): void
    {
        $accountA = Account::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'created_by' => $this->user->id,
        ]);
        $categoryA = $this->categoryFor($this->workspaceA);
        $transactionA = Transaction::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'account_id' => $accountA->id,
            'category_id' => $categoryA->id,
            'created_by' => $this->user->id,
            'type' => 'expense',
            'value' => 42.50,
            'description' => 'Almoço teste',
            'date' => '2026-01-20',
        ]);

        $accountB = Account::factory()->create([
            'workspace_id' => $this->workspaceB->id,
            'created_by' => $this->user->id,
        ]);
        $categoryB = $this->categoryFor($this->workspaceB);
        Transaction::factory()->create([
            'workspace_id' => $this->workspaceB->id,
            'account_id' => $accountB->id,
            'category_id' => $categoryB->id,
            'created_by' => $this->user->id,
            'type' => 'expense',
            'value' => 10.00,
            'description' => 'Outra transação',
            'date' => '2026-01-21',
        ]);

        $tool = new QueryTransactionsTool($this->workspaceA->uuid);

        $response = json_decode($tool->handle(new Request([])), true);

        $this->assertSame(1, $response['count']);
        $this->assertCount(1, $response['transactions']);
        $this->assertSame($transactionA->uuid, $response['transactions'][0]['uuid']);
    }

    public function test_query_categories_tool_finds_workspace_categories(): void
    {
        $categoryA = $this->categoryFor($this->workspaceA);
        $this->categoryFor($this->workspaceB);

        $tool = new QueryCategoriesTool($this->workspaceA->uuid);

        $response = json_decode($tool->handle(new Request([])), true);

        $this->assertSame(1, $response['count']);
        $this->assertCount(1, $response['categories']);
        $this->assertSame($categoryA->uuid, $response['categories'][0]['uuid']);
    }

    public function test_query_recurrences_tool_finds_workspace_recurrences(): void
    {
        $categoryA = $this->categoryFor($this->workspaceA);
        $recurrenceA = Recurrence::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'category_id' => $categoryA->id,
            'created_by' => $this->user->id,
        ]);

        $categoryB = $this->categoryFor($this->workspaceB);
        Recurrence::factory()->create([
            'workspace_id' => $this->workspaceB->id,
            'category_id' => $categoryB->id,
            'created_by' => $this->user->id,
        ]);

        $tool = new QueryRecurrencesTool($this->workspaceA->uuid);

        $response = json_decode($tool->handle(new Request([])), true);

        $this->assertSame(1, $response['count']);
        $this->assertCount(1, $response['recurrences']);
        $this->assertSame($recurrenceA->uuid, $response['recurrences'][0]['uuid']);
    }

    public function test_query_tags_tool_finds_workspace_tags(): void
    {
        $tagA = Tag::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'created_by' => $this->user->id,
        ]);
        Tag::factory()->create([
            'workspace_id' => $this->workspaceB->id,
            'created_by' => $this->user->id,
        ]);

        $tool = new QueryTagsTool($this->workspaceA->uuid);

        $response = json_decode($tool->handle(new Request([])), true);

        $this->assertSame(1, $response['count']);
        $this->assertCount(1, $response['tags']);
        $this->assertSame($tagA->uuid, $response['tags'][0]['uuid']);
    }
}
