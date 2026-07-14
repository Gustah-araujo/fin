<?php

declare(strict_types=1);

namespace Tests\Feature\Transactions;

use App\Enums\WorkspaceRole;
use App\Models\Account;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AccountService;
use Tests\TestCase;

class TransactionUpdateTest extends TestCase
{
    public function test_user_can_update_transaction(): void
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
            "description" => "Original",
            "value" => 100,
            "date" => "2026-07-10",
        ]);

        $response = $this->actingAs($user)
            ->put(route("transactions.update", ["workspace" => $workspace, "transaction" => $transaction]), [
                "description" => "Atualizada",
            ]);

        $response->assertRedirect(route("transactions.index", $workspace));

        $this->assertDatabaseHas("transactions", [
            "id" => $transaction->id,
            "description" => "Atualizada",
        ]);
    }

    public function test_updating_transaction_syncs_tags(): void
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

        $tag1 = Tag::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
            "name" => "Urgente",
        ]);

        $tag2 = Tag::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
            "name" => "Viagem",
        ]);

        $tag3 = Tag::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
            "name" => "Lazer",
        ]);

        $this->actingAs($user)
            ->post(route("transactions.store", $workspace), [
                "description" => "Com tags",
                "value" => 200,
                "date" => "2026-07-10",
                "account_id" => $account->uuid,
                "category_id" => $category->uuid,
                "tags" => [$tag1->uuid, $tag2->uuid],
            ]);

        $transaction = Transaction::where("description", "Com tags")->first();

        $this->assertEquals(2, \Illuminate\Support\Facades\DB::table("taggables")
            ->where("taggable_id", $transaction->id)
            ->where("taggable_type", Transaction::class)
            ->count());

        $this->actingAs($user)
            ->put(route("transactions.update", ["workspace" => $workspace, "transaction" => $transaction]), [
                "tags" => [$tag3->uuid],
            ]);

        $transaction->refresh();

        $this->assertEquals(1, \Illuminate\Support\Facades\DB::table("taggables")
            ->where("taggable_id", $transaction->id)
            ->where("taggable_type", Transaction::class)
            ->count());
        $this->assertDatabaseHas("taggables", [
            "tag_id" => $tag3->id,
            "taggable_id" => $transaction->id,
            "taggable_type" => Transaction::class,
        ]);
    }

    public function test_updating_paid_transaction_value_recalculates_balance(): void
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
            ->put(route("transactions.update", ["workspace" => $workspace, "transaction" => $transaction]), [
                "value" => 300,
            ]);

        $this->assertEquals(700, (float) $account->fresh()->current_balance);
    }

    public function test_moving_paid_transaction_recalculates_both_accounts(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ["role" => WorkspaceRole::Admin->value]);

        $accountA = Account::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
            "initial_balance" => 1000,
            "current_balance" => 1000,
        ]);

        $accountB = Account::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
            "initial_balance" => 2000,
            "current_balance" => 2000,
        ]);

        $category = Category::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
            "type" => "expense",
        ]);

        $transaction = Transaction::factory()->paid()->create([
            "workspace_id" => $workspace->id,
            "account_id" => $accountA->id,
            "category_id" => $category->id,
            "created_by" => $user->id,
            "type" => "expense",
            "value" => 200,
            "date" => "2026-07-10",
        ]);

        app(AccountService::class)->recalculateBalance($accountA);

        $this->assertEquals(800, (float) $accountA->fresh()->current_balance);

        $this->actingAs($user)
            ->put(route("transactions.update", ["workspace" => $workspace, "transaction" => $transaction]), [
                "account_id" => $accountB->uuid,
            ]);

        $this->assertEquals(1000, (float) $accountA->fresh()->current_balance);
        $this->assertEquals(1800, (float) $accountB->fresh()->current_balance);
    }

    public function test_cannot_update_transaction_with_invalid_data(): void
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
            "description" => "Original",
            "value" => 100,
            "date" => "2026-07-10",
        ]);

        $response = $this->actingAs($user)
            ->put(route("transactions.update", ["workspace" => $workspace, "transaction" => $transaction]), [
                "description" => "",
            ]);

        $response->assertSessionHasErrors(["description"]);
    }
}
