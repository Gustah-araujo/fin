<?php

namespace Tests\Feature\Accounts;

use App\Enums\WorkspaceRole;
use App\Models\Account;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class AccountUpdateTest extends TestCase
{
    public function test_user_can_update_account(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ["role" => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
            "name" => "Original",
            "type" => "checking",
            "initial_balance" => 1000,
            "current_balance" => 1000,
        ]);

        $response = $this->actingAs($user)
            ->put(route("accounts.update", [$workspace, $account]), [
                "name" => "Updated Name",
                "type" => "savings",
            ]);

        $response->assertRedirect(route("accounts.index", $workspace));

        $this->assertDatabaseHas("accounts", [
            "id" => $account->id,
            "name" => "Updated Name",
            "type" => "savings",
        ]);
    }

    public function test_cannot_update_account_with_invalid_type(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ["role" => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
            "name" => "Original",
            "type" => "checking",
        ]);

        $response = $this->actingAs($user)
            ->put(route("accounts.update", [$workspace, $account]), [
                "type" => "invalid_type",
            ]);

        $response->assertSessionHasErrors(["type"]);
    }
}
