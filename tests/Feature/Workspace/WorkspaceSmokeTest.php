<?php

namespace Tests\Feature\Workspace;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class WorkspaceSmokeTest extends TestCase
{
    public function test_settings_page_returns_ok(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->get(route('workspace.settings', $workspace));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Workspace/Settings'));
    }

    public function test_members_page_returns_ok(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->get(route('workspace.members.index', $workspace));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Workspace/Members'));
    }
}
