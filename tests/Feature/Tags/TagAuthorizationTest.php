<?php

namespace Tests\Feature\Tags;

use App\Enums\WorkspaceRole;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class TagAuthorizationTest extends TestCase
{
    public function test_viewer_cannot_create_tag(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Viewer->value]);

        $response = $this->actingAs($user)
            ->post(route('tags.store', $workspace), [
                'name' => 'Teste',
                'color' => '#FF5733',
            ]);

        $response->assertForbidden();
    }

    public function test_viewer_cannot_update_tag(): void
    {
        $admin = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($admin, ['role' => WorkspaceRole::Admin->value]);

        $tag = Tag::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $admin->id,
        ]);

        $user = User::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Viewer->value]);

        $response = $this->actingAs($user)
            ->put(route('tags.update', ['workspace' => $workspace, 'tag' => $tag]), [
                'name' => 'Hacked',
            ]);

        $response->assertForbidden();
    }

    public function test_viewer_cannot_delete_tag(): void
    {
        $admin = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($admin, ['role' => WorkspaceRole::Admin->value]);

        $tag = Tag::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $admin->id,
        ]);

        $user = User::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Viewer->value]);

        $response = $this->actingAs($user)
            ->delete(route('tags.destroy', ['workspace' => $workspace, 'tag' => $tag]));

        $response->assertForbidden();
    }

    public function test_cannot_access_tag_from_other_workspace(): void
    {
        $user = User::factory()->create();
        $workspaceA = Workspace::factory()->create();
        $workspaceA->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $otherUser = User::factory()->create();
        $workspaceB = Workspace::factory()->create();
        $workspaceB->members()->attach($otherUser, ['role' => WorkspaceRole::Admin->value]);

        $tag = Tag::factory()->create([
            'workspace_id' => $workspaceB->id,
            'created_by' => $otherUser->id,
        ]);

        $response = $this->actingAs($user)
            ->put(route('tags.update', ['workspace' => $workspaceA, 'tag' => $tag]), [
                'name' => 'Hacked',
            ]);

        $response->assertNotFound();
    }
}
