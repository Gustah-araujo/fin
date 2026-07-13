<?php

namespace Tests\Feature\Workspace;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class WorkspaceCreationTest extends TestCase
{
    public function test_user_with_zero_workspaces_is_redirected_to_create(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertRedirect(route('workspace.create'));
    }

    public function test_user_can_create_workspace_and_becomes_admin(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/workspace', [
            'name' => 'Meu Workspace',
            'description' => 'Descrição do workspace',
        ]);

        $workspace = Workspace::first();
        $response->assertRedirect(route('dashboard', ['workspace' => $workspace->uuid]));

        $this->assertDatabaseHas('workspaces', [
            'name' => 'Meu Workspace',
            'description' => 'Descrição do workspace',
        ]);

        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceRole::Admin->value,
        ]);
    }

    public function test_user_with_workspace_is_not_redirected(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)->get('/workspace/select');

        $response->assertStatus(200);
    }
}
