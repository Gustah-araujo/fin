<?php

namespace Tests\Feature\Accounts;

use App\Enums\WorkspaceRole;
use App\Models\Account;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class AccountAuthorizationTest extends TestCase
{
    public function test_viewer_cannot_create_account(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ["role" => WorkspaceRole::Viewer->value]);

        $response = $this->actingAs($user)
            ->post(route("accounts.store", $workspace), [
                "name" => "Test",
                "type" => "checking",
                "initial_balance" => 100,
            ]);

        $response->assertForbidden();
    }

    public function test_viewer_cannot_update_account(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ["role" => WorkspaceRole::Viewer->value]);

        $account = Account::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->put(route("accounts.update", [$workspace, $account]), [
                "name" => "Hacked",
            ]);

        $response->assertForbidden();
    }

    public function test_viewer_cannot_delete_account(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ["role" => WorkspaceRole::Viewer->value]);

        $account = Account::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->delete(route("accounts.destroy", [$workspace, $account]));

        $response->assertForbidden();
    }

    public function test_cannot_access_account_from_other_workspace(): void
    {
        $user = User::factory()->create();
        $workspaceA = Workspace::factory()->create();
        $workspaceA->members()->attach($user, ["role" => WorkspaceRole::Admin->value]);

        $workspaceB = Workspace::factory()->create();
        $workspaceB->members()->attach($user, ["role" => WorkspaceRole::Admin->value]);

        $accountInB = Account::factory()->create([
            "workspace_id" => $workspaceB->id,
            "created_by" => $user->id,
        ]);

        // Try to access workspace B's account via workspace A's URL
        $response = $this->actingAs($user)
            ->put(route("accounts.update", [$workspaceA, $accountInB]), [
                "name" => "Hacked",
            ]);

        $response->assertNotFound();
    }
}
