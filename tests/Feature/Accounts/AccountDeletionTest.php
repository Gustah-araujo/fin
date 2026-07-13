<?php

namespace Tests\Feature\Accounts;

use App\Enums\WorkspaceRole;
use App\Models\Account;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    public function test_user_can_soft_delete_account(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ["role" => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
            "name" => "To Delete",
        ]);

        $response = $this->actingAs($user)
            ->delete(route("accounts.destroy", [$workspace, $account]));

        $response->assertRedirect(route("accounts.index", $workspace));

        $this->assertSoftDeleted($account);
        $this->assertDatabaseHas("accounts", [
            "id" => $account->id,
        ]);
    }

    public function test_soft_deleted_account_not_in_list(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ["role" => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
            "name" => "Archived",
        ]);

        $this->actingAs($user)
            ->delete(route("accounts.destroy", [$workspace, $account]));

        $response = $this->actingAs($user)
            ->get(route("accounts.index", $workspace));

        $response->assertInertia(fn ($page) => $page
            ->component("Accounts/Index", false)
            ->has("accounts", 0)
        );
    }
}
