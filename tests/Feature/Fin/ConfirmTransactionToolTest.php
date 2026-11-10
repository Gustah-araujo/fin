<?php

declare(strict_types=1);

namespace Tests\Feature\Fin;

use App\Ai\Tools\ConfirmTransactionTool;
use App\Enums\FinPendingMutationStatus;
use App\Enums\WorkspaceRole;
use App\Models\Account;
use App\Models\Category;
use App\Models\FinPendingMutation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class ConfirmTransactionToolTest extends TestCase
{
    private Workspace $workspaceA;

    private Workspace $workspaceB;

    private User $user;

    private Account $account;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspaceA = Workspace::factory()->create();
        $this->workspaceA->members()->attach($this->user, ['role' => WorkspaceRole::Admin->value]);

        $this->account = Account::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'created_by' => $this->user->id,
        ]);

        $this->category = Category::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'created_by' => $this->user->id,
            'type' => 'expense',
        ]);

        $otherUser = User::factory()->create();
        $this->workspaceB = Workspace::factory()->create();
        $this->workspaceB->members()->attach($otherUser, ['role' => WorkspaceRole::Admin->value]);
    }

    private function tool(): ConfirmTransactionTool
    {
        return new ConfirmTransactionTool($this->workspaceA->uuid, 'conv-test-id', $this->user->id);
    }

    private function createPending(array $overrides = []): FinPendingMutation
    {
        return FinPendingMutation::create(array_merge([
            'uuid' => Str::orderedUuid()->toString(),
            'workspace_id' => $this->workspaceA->id,
            'conversation_id' => 'conv-test-id',
            'user_id' => $this->user->id,
            'type' => 'expense',
            'payload' => [
                'type' => 'expense',
                'description' => 'Almoço de trabalho',
                'value' => 42.50,
                'date' => '2026-01-20',
                'account_id' => $this->account->uuid,
                'category_id' => $this->category->uuid,
                'tags' => [],
            ],
            'status' => FinPendingMutationStatus::Pending,
            'expires_at' => now()->addMinutes(30),
        ], $overrides));
    }

    public function test_confirm_persists_transaction_and_marks_confirmed(): void
    {
        $pending = $this->createPending();

        $response = json_decode($this->tool()->handle(new Request([
            'pending_id' => $pending->uuid,
        ])), true);

        $this->assertArrayHasKey('transaction_uuid', $response);
        $this->assertArrayNotHasKey('error', $response);

        $this->assertDatabaseHas('transactions', [
            'workspace_id' => $this->workspaceA->id,
            'description' => 'Almoço de trabalho',
        ]);

        $this->assertDatabaseHas('fin_pending_mutations', [
            'uuid' => $pending->uuid,
            'status' => 'confirmed',
        ]);
    }

    public function test_confirm_rejects_pending_from_other_workspace(): void
    {
        $otherAccount = Account::factory()->create([
            'workspace_id' => $this->workspaceB->id,
            'created_by' => $this->user->id,
        ]);

        $otherCategory = Category::factory()->create([
            'workspace_id' => $this->workspaceB->id,
            'created_by' => $this->user->id,
            'type' => 'expense',
        ]);

        $pending = FinPendingMutation::create([
            'uuid' => Str::orderedUuid()->toString(),
            'workspace_id' => $this->workspaceB->id,
            'conversation_id' => 'conv-test-id',
            'user_id' => $this->user->id,
            'type' => 'expense',
            'payload' => [
                'type' => 'expense',
                'description' => 'Almoço de trabalho',
                'value' => 42.50,
                'date' => '2026-01-20',
                'account_id' => $otherAccount->uuid,
                'category_id' => $otherCategory->uuid,
                'tags' => [],
            ],
            'status' => FinPendingMutationStatus::Pending,
            'expires_at' => now()->addMinutes(30),
        ]);

        $response = json_decode($this->tool()->handle(new Request([
            'pending_id' => $pending->uuid,
        ])), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_confirm_rejects_expired_mutation(): void
    {
        $pending = $this->createPending([
            'expires_at' => now()->subMinute(),
        ]);

        $response = json_decode($this->tool()->handle(new Request([
            'pending_id' => $pending->uuid,
        ])), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseHas('fin_pending_mutations', [
            'uuid' => $pending->uuid,
            'status' => 'expired',
        ]);
    }

    public function test_confirm_rejects_non_pending_mutation(): void
    {
        $pending = $this->createPending([
            'status' => FinPendingMutationStatus::Confirmed,
        ]);

        $response = json_decode($this->tool()->handle(new Request([
            'pending_id' => $pending->uuid,
        ])), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_confirm_rejects_unknown_pending_id(): void
    {
        $response = json_decode($this->tool()->handle(new Request([
            'pending_id' => Str::orderedUuid()->toString(),
        ])), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertDatabaseCount('transactions', 0);
    }
}
