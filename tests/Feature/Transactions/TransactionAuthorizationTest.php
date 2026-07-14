<?php

declare(strict_types=1);

namespace Tests\Feature\Transactions;

use App\Enums\WorkspaceRole;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class TransactionAuthorizationTest extends TestCase
{
    public function test_viewer_cannot_create_transaction(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ["role" => WorkspaceRole::Viewer->value]);

        $account = Account::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
        ]);
        $category = Category::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
            "type" => "expense",
        ]);

        $response = $this->actingAs($user)
            ->post(route("transactions.store", $workspace), [
                "description" => "Compra no mercado",
                "value" => 150.00,
                "date" => "2026-07-01",
                "account_id" => $account->uuid,
                "category_id" => $category->uuid,
            ]);

        $response->assertForbidden();
    }

    public function test_viewer_cannot_update_transaction(): void
    {
        $admin = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($admin, ["role" => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $admin->id,
        ]);
        $category = Category::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $admin->id,
            "type" => "expense",
        ]);

        $transaction = Transaction::factory()->create([
            "workspace_id" => $workspace->id,
            "account_id" => $account->id,
            "category_id" => $category->id,
            "created_by" => $admin->id,
        ]);

        $user = User::factory()->create();
        $workspace->members()->attach($user, ["role" => WorkspaceRole::Viewer->value]);

        $response = $this->actingAs($user)
            ->put(route("transactions.update", [$workspace, $transaction]), [
                "description" => "Hacked",
            ]);

        $response->assertForbidden();
    }

    public function test_viewer_cannot_delete_transaction(): void
    {
        $admin = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($admin, ["role" => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $admin->id,
        ]);
        $category = Category::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $admin->id,
            "type" => "expense",
        ]);

        $transaction = Transaction::factory()->create([
            "workspace_id" => $workspace->id,
            "account_id" => $account->id,
            "category_id" => $category->id,
            "created_by" => $admin->id,
        ]);

        $user = User::factory()->create();
        $workspace->members()->attach($user, ["role" => WorkspaceRole::Viewer->value]);

        $response = $this->actingAs($user)
            ->delete(route("transactions.destroy", [$workspace, $transaction]));

        $response->assertForbidden();
    }

    public function test_editor_can_create_transaction(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ["role" => WorkspaceRole::Editor->value]);

        $account = Account::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
        ]);
        $category = Category::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
            "type" => "expense",
        ]);

        $response = $this->actingAs($user)
            ->post(route("transactions.store", $workspace), [
                "description" => "Compra no mercado",
                "value" => 150.00,
                "date" => "2026-07-01",
                "account_id" => $account->uuid,
                "category_id" => $category->uuid,
            ]);

        $response->assertRedirect();
        $response->assertStatus(302);
        $this->assertNotEquals(403, $response->status());
    }

    public function test_editor_can_update_transaction(): void
    {
        $admin = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($admin, ["role" => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $admin->id,
        ]);
        $category = Category::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $admin->id,
            "type" => "expense",
        ]);

        $transaction = Transaction::factory()->create([
            "workspace_id" => $workspace->id,
            "account_id" => $account->id,
            "category_id" => $category->id,
            "created_by" => $admin->id,
        ]);

        $user = User::factory()->create();
        $workspace->members()->attach($user, ["role" => WorkspaceRole::Editor->value]);

        $response = $this->actingAs($user)
            ->put(route("transactions.update", [$workspace, $transaction]), [
                "description" => "Descrição atualizada",
            ]);

        $response->assertRedirect();
    }

    public function test_editor_cannot_delete_transaction(): void
    {
        $admin = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($admin, ["role" => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $admin->id,
        ]);
        $category = Category::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $admin->id,
            "type" => "expense",
        ]);

        $transaction = Transaction::factory()->create([
            "workspace_id" => $workspace->id,
            "account_id" => $account->id,
            "category_id" => $category->id,
            "created_by" => $admin->id,
        ]);

        $user = User::factory()->create();
        $workspace->members()->attach($user, ["role" => WorkspaceRole::Editor->value]);

        $response = $this->actingAs($user)
            ->delete(route("transactions.destroy", [$workspace, $transaction]));

        $response->assertForbidden();
    }

    public function test_cannot_access_transaction_from_other_workspace(): void
    {
        $user = User::factory()->create();

        $workspaceA = Workspace::factory()->create();
        $workspaceA->members()->attach($user, ["role" => WorkspaceRole::Admin->value]);

        $workspaceB = Workspace::factory()->create();
        $workspaceB->members()->attach($user, ["role" => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            "workspace_id" => $workspaceA->id,
            "created_by" => $user->id,
        ]);
        $category = Category::factory()->create([
            "workspace_id" => $workspaceA->id,
            "created_by" => $user->id,
            "type" => "expense",
        ]);

        $transaction = Transaction::factory()->create([
            "workspace_id" => $workspaceA->id,
            "account_id" => $account->id,
            "category_id" => $category->id,
            "created_by" => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route("transactions.edit", ["workspace" => $workspaceB->id, "transaction" => $transaction->id]));

        $response->assertNotFound();
    }
}
