<?php

namespace Tests\Feature\Workspace;

use App\Enums\InviteStatus;
use App\Enums\WorkspaceRole;
use App\Models\Invite;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class InviteTest extends TestCase
{
    public function test_admin_can_invite_existing_user(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create(['email' => 'invited@example.com']);
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($admin, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($admin)->post("/w/{$workspace->uuid}/invites", [
            'email' => 'invited@example.com',
            'role' => 'editor',
        ]);

        $response->assertSessionHas('status');

        $this->assertDatabaseHas('invites', [
            'workspace_id' => $workspace->id,
            'email' => 'invited@example.com',
            'role' => WorkspaceRole::Editor->value,
            'status' => InviteStatus::Pending->value,
        ]);
    }

    public function test_non_existent_email_does_not_create_invite(): void
    {
        $admin = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($admin, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($admin)->post("/w/{$workspace->uuid}/invites", [
            'email' => 'nonexistent@example.com',
            'role' => 'editor',
        ]);

        $response->assertSessionHas('status');

        $this->assertDatabaseMissing('invites', [
            'email' => 'nonexistent@example.com',
        ]);
    }

    public function test_user_can_accept_invite(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create(['email' => 'invited@example.com']);
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($admin, ['role' => WorkspaceRole::Admin->value]);

        $invite = Invite::factory()->create([
            'workspace_id' => $workspace->id,
            'email' => 'invited@example.com',
            'role' => WorkspaceRole::Editor,
            'inviter_id' => $admin->id,
            'status' => InviteStatus::Pending,
        ]);

        $response = $this->actingAs($target)->post("/invites/{$invite->uuid}/accept");

        $response->assertRedirect(route('dashboard', ['workspace' => $workspace->uuid]));

        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $target->id,
            'role' => WorkspaceRole::Editor->value,
        ]);

        $this->assertEquals(InviteStatus::Accepted, $invite->fresh()->status);
    }

    public function test_user_can_decline_invite(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create(['email' => 'invited@example.com']);
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($admin, ['role' => WorkspaceRole::Admin->value]);

        $invite = Invite::factory()->create([
            'workspace_id' => $workspace->id,
            'email' => 'invited@example.com',
            'role' => WorkspaceRole::Editor,
            'inviter_id' => $admin->id,
            'status' => InviteStatus::Pending,
        ]);

        $response = $this->actingAs($target)->post("/invites/{$invite->uuid}/decline");

        $response->assertSessionHas('status');
        $this->assertEquals(InviteStatus::Declined, $invite->fresh()->status);
    }
}
