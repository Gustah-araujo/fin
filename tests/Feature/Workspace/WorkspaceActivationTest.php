<?php

namespace Tests\Feature\Workspace;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class WorkspaceActivationTest extends TestCase
{
    public function test_user_can_activate_a_workspace(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)->post('/workspace/activate', [
            'workspace_uuid' => $workspace->uuid,
        ]);

        $response->assertRedirect(route('dashboard', ['workspace' => $workspace->uuid]));

        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_root_redirects_to_dashboard_when_a_workspace_was_visited(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, [
            'role' => WorkspaceRole::Admin->value,
            'last_visited_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/');

        $response->assertRedirect(route('dashboard', ['workspace' => $workspace->uuid]));
    }

    public function test_root_redirects_to_select_when_workspace_was_never_visited(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)->get('/');

        $response->assertRedirect(route('workspace.select'));
    }
}
