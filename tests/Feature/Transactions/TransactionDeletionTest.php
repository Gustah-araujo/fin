<?php

declare(strict_types=1);

namespace Tests\Feature\Transactions;

use App\Enums\WorkspaceRole;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AccountService;
use Tests\TestCase;

class TransactionDeletionTest extends TestCase
{
    public function test_user_can_soft_delete_transaction(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ["role" => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
        ]);

        $category = Category::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
            "type" => "expense",
        ]);

        $transaction = Transaction::factory()->create([
            "workspace_id" => $workspace->id,
            "account_id" => $account->id,
            "category_id" => $category->id,
            "created_by" => $user->id,
            "type" => "expense",
            "description" => "Para deletar",
            "value" => 100,
            "date" => "2026-07-10",
        ]);

        $response = $this->actingAs($user)
            ->delete(route("transactions.destroy", ["workspace" => $workspace, "transaction" => $transaction]));

        $response->assertRedirect(route("transactions.index", $workspace));

        $this->assertSoftDeleted($transaction);
    }

    public function test_deleting_paid_transaction_restores_balance(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ["role" => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
            "initial_balance" => 1000,
            "current_balance" => 1000,
        ]);

        $category = Category::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
            "type" => "expense",
        ]);

        $transaction = Transaction::factory()->paid()->create([
            "workspace_id" => $workspace->id,
            "account_id" => $account->id,
            "category_id" => $category->id,
            "created_by" => $user->id,
            "type" => "expense",
            "value" => 200,
            "date" => "2026-07-10",
        ]);

        app(AccountService::class)->recalculateBalance($account);

        $this->assertEquals(800, (float) $account->fresh()->current_balance);

        $this->actingAs($user)
            ->delete(route("transactions.destroy", ["workspace" => $workspace, "transaction" => $transaction]));

        $this->assertEquals(1000, (float) $account->fresh()->current_balance);
    }

    public function test_soft_deleted_transaction_not_in_list(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ["role" => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
        ]);

        $category = Category::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
            "type" => "expense",
        ]);

        $transaction = Transaction::factory()->create([
            "workspace_id" => $workspace->id,
            "account_id" => $account->id,
            "category_id" => $category->id,
            "created_by" => $user->id,
            "type" => "expense",
            "description" => "Visível",
            "value" => 100,
            "date" => "2026-07-10",
        ]);

        $this->actingAs($user)
            ->delete(route("transactions.destroy", ["workspace" => $workspace, "transaction" => $transaction]));

        $response = $this->actingAs($user)
            ->get(route("transactions.index", $workspace));

        $response->assertInertia(fn ($page) => $page
            ->component("Transactions/Index", false)
            ->has("transactions.data", 0)
        );
    }
}
