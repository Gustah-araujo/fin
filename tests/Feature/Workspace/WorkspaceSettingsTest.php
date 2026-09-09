<?php

namespace Tests\Feature\Workspace;

use App\Enums\InviteStatus;
use App\Enums\WorkspaceRole;
use App\Models\Invite;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class WorkspaceSettingsTest extends TestCase
{
    public function test_admin_can_access_workspace_settings(): void
    {
        $admin = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($admin, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($admin)
            ->get("/w/{$workspace->uuid}/settings");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Workspace/Settings'));
    }

    public function test_editor_can_access_settings_but_is_not_admin(): void
    {
        $editor = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($editor, ['role' => WorkspaceRole::Editor->value]);

        $response = $this->actingAs($editor)
            ->get("/w/{$workspace->uuid}/settings");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('isAdmin', false)
        );
    }

    public function test_non_member_cannot_access_settings(): void
    {
        $nonMember = User::factory()->create();
        $workspace = Workspace::factory()->create();

        $response = $this->actingAs($nonMember)
            ->get("/w/{$workspace->uuid}/settings");

        $response->assertForbidden();
    }

    public function test_settings_page_returns_members_and_invites(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($admin, ['role' => WorkspaceRole::Admin->value]);
        $workspace->members()->attach($member, ['role' => WorkspaceRole::Editor->value]);

        Invite::factory()->create([
            'workspace_id' => $workspace->id,
            'email' => 'pending@example.com',
            'inviter_id' => $admin->id,
            'status' => InviteStatus::Pending,
        ]);

        $response = $this->actingAs($admin)
            ->get("/w/{$workspace->uuid}/settings");

        $response->assertInertia(fn ($page) => $page
            ->has('members', 2)
            ->has('invites', 1)
            ->where('isAdmin', true)
        );
    }
}
