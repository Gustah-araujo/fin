<?php

declare(strict_types=1);

namespace Tests\Feature\Fin;

use App\Ai\Tools\ValidateTransactionTool;
use App\Enums\WorkspaceRole;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class ValidateTransactionToolTest extends TestCase
{
    private Workspace $workspace;

    private Workspace $workspaceB;

    private User $user;

    private Account $account;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->members()->attach($this->user, ['role' => WorkspaceRole::Admin->value]);

        $this->account = Account::factory()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        $this->category = Category::factory()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
            'type' => 'expense',
        ]);

        $otherUser = User::factory()->create();
        $this->workspaceB = Workspace::factory()->create();
        $this->workspaceB->members()->attach($otherUser, ['role' => WorkspaceRole::Admin->value]);
        Account::factory()->create([
            'workspace_id' => $this->workspaceB->id,
            'created_by' => $otherUser->id,
        ]);
    }

    private function tool(): ValidateTransactionTool
    {
        return new ValidateTransactionTool($this->workspace->uuid, 'conv-test-id', $this->user->id);
    }

    private function validArgs(array $overrides = []): array
    {
        return array_merge([
            'type' => 'expense',
            'description' => 'Supermercado Extra',
            'value' => 100.0,
            'date' => '2026-01-15',
            'account_id' => $this->account->uuid,
            'category_id' => $this->category->uuid,
            'tags' => [],
        ], $overrides);
    }

    public function test_validate_creates_pending_mutation_without_persisting_transaction(): void
    {
        $response = json_decode($this->tool()->handle(new Request($this->validArgs())), true);

        $this->assertArrayHasKey('pending_id', $response);
        $this->assertFalse($response['duplicate_detected']);
        $this->assertNull($response['duplicate']);

        $this->assertDatabaseHas('fin_pending_mutations', [
            'workspace_id' => $this->workspace->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_validate_flags_duplicate(): void
    {
        $transaction = Transaction::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'type' => 'expense',
            'value' => 100.00,
            'date' => '2026-01-15',
            'description' => 'Supermercado Extra',
        ]);

        $response = json_decode($this->tool()->handle(new Request($this->validArgs())), true);

        $this->assertTrue($response['duplicate_detected']);
        $this->assertSame($transaction->uuid, $response['duplicate']['uuid']);

        $this->assertDatabaseHas('fin_pending_mutations', [
            'workspace_id' => $this->workspace->id,
            'status' => 'pending',
        ]);
    }

    public function test_validate_rejects_account_from_other_workspace(): void
    {
        $otherAccount = Account::factory()->create([
            'workspace_id' => $this->workspaceB->id,
            'created_by' => $this->user->id,
        ]);

        $response = json_decode($this->tool()->handle(new Request($this->validArgs([
            'account_id' => $otherAccount->uuid,
        ]))), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertDatabaseCount('fin_pending_mutations', 0);
    }

    public function test_validate_rejects_invalid_type(): void
    {
        $response = json_decode($this->tool()->handle(new Request($this->validArgs([
            'type' => 'both',
        ]))), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertDatabaseCount('fin_pending_mutations', 0);
    }

    public function test_validate_rejects_non_positive_value(): void
    {
        $response = json_decode($this->tool()->handle(new Request($this->validArgs([
            'value' => 0,
        ]))), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertDatabaseCount('fin_pending_mutations', 0);
    }
}
