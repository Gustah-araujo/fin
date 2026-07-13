<?php

namespace Tests\Feature\Accounts;

use App\Enums\WorkspaceRole;
use App\Models\Account;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class AccountCreationTest extends TestCase
{
    public function test_user_can_create_account(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ["role" => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->post(route("accounts.store", $workspace), [
                "name" => "Nubank",
                "type" => "checking",
                "initial_balance" => 1000,
            ]);

        $response->assertRedirect(route("accounts.index", $workspace));

        $this->assertDatabaseHas("accounts", [
            "workspace_id" => $workspace->id,
            "name" => "Nubank",
            "type" => "checking",
            "initial_balance" => 1000,
            "current_balance" => 1000,
        ]);
    }

    public function test_validation_errors_on_create(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ["role" => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->post(route("accounts.store", $workspace), []);

        $response->assertSessionHasErrors(["name", "type", "initial_balance"]);
    }

    public function test_account_list_shows_balances(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ["role" => WorkspaceRole::Admin->value]);

        Account::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
            "name" => "Conta A",
            "type" => "checking",
            "initial_balance" => 1000,
            "current_balance" => 1000,
        ]);
        Account::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
            "name" => "Conta B",
            "type" => "savings",
            "initial_balance" => 500,
            "current_balance" => 500,
        ]);

        $response = $this->actingAs($user)
            ->get(route("accounts.index", $workspace));

        $response->assertInertia(fn ($page) => $page
            ->component("Accounts/Index", false)
            ->has("accounts.data", 2)
        );
    }

    public function test_account_with_zero_balance_is_accepted(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ["role" => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->post(route("accounts.store", $workspace), [
                "name" => "Conta Zerada",
                "type" => "checking",
                "initial_balance" => 0,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas("accounts", [
            "name" => "Conta Zerada",
            "initial_balance" => 0,
            "current_balance" => 0,
        ]);
    }

    public function test_account_with_negative_balance_is_accepted(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ["role" => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->post(route("accounts.store", $workspace), [
                "name" => "Conta Negativa",
                "type" => "savings",
                "initial_balance" => -500,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas("accounts", [
            "name" => "Conta Negativa",
            "initial_balance" => -500,
            "current_balance" => -500,
        ]);
    }
}
