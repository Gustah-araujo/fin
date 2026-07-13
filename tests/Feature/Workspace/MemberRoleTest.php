<?php

namespace Tests\Feature\Workspace;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class MemberRoleTest extends TestCase
{
    public function test_admin_can_change_member_role(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($admin, ['role' => WorkspaceRole::Admin->value]);
        $workspace->members()->attach($member, ['role' => WorkspaceRole::Editor->value]);

        $response = $this->actingAs($admin)
            ->put("/w/{$workspace->uuid}/members/{$member->uuid}/role", [
                'role' => 'viewer',
            ]);

        $response->assertSessionHas('status');

        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
            'role' => WorkspaceRole::Viewer->value,
        ]);
    }

    public function test_editor_cannot_manage_members(): void
    {
        $editor = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($editor, ['role' => WorkspaceRole::Editor->value]);
        $workspace->members()->attach($member, ['role' => WorkspaceRole::Viewer->value]);

        $response = $this->actingAs($editor)
            ->put("/w/{$workspace->uuid}/members/{$member->uuid}/role", [
                'role' => 'admin',
            ]);

        $response->assertForbidden();
    }

    public function test_viewer_cannot_mutate_workspace_data(): void
    {
        $viewer = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($viewer, ['role' => WorkspaceRole::Viewer->value]);

        $response = $this->actingAs($viewer)->post("/w/{$workspace->uuid}/invites", [
            'email' => 'test@example.com',
            'role' => 'editor',
        ]);

        $response->assertForbidden();
    }

    public function test_last_admin_cannot_leave_workspace(): void
    {
        $admin = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($admin, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($admin)
            ->delete("/w/{$workspace->uuid}/members/{$admin->uuid}");

        $response->assertStatus(422);
    }
}
