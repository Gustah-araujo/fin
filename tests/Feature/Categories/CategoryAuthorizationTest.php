<?php

namespace Tests\Feature\Categories;

use App\Enums\WorkspaceRole;
use App\Models\Category;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class CategoryAuthorizationTest extends TestCase
{
    public function test_viewer_cannot_create_category(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Viewer->value]);

        $response = $this->actingAs($user)
            ->post(route('categories.store', $workspace), [
                'name' => 'Teste',
                'type' => 'expense',
                'color' => '#FF5733',
            ]);

        $response->assertForbidden();
    }

    public function test_viewer_cannot_update_category(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Viewer->value]);

        $admin = User::factory()->create();
        $workspace->members()->attach($admin, ['role' => WorkspaceRole::Admin->value]);

        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($user)
            ->put(route('categories.update', ['workspace' => $workspace, 'category' => $category]), [
                'name' => 'Hacked',
            ]);

        $response->assertForbidden();
    }

    public function test_viewer_cannot_delete_category(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Viewer->value]);

        $admin = User::factory()->create();
        $workspace->members()->attach($admin, ['role' => WorkspaceRole::Admin->value]);

        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($user)
            ->delete(route('categories.destroy', ['workspace' => $workspace, 'category' => $category]));

        $response->assertForbidden();
    }

    public function test_cannot_access_category_from_other_workspace(): void
    {
        $user = User::factory()->create();
        $workspaceA = Workspace::factory()->create();
        $workspaceA->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $otherUser = User::factory()->create();
        $workspaceB = Workspace::factory()->create();
        $workspaceB->members()->attach($otherUser, ['role' => WorkspaceRole::Admin->value]);

        $category = Category::factory()->create([
            'workspace_id' => $workspaceB->id,
            'created_by' => $otherUser->id,
        ]);

        $response = $this->actingAs($user)
            ->put(route('categories.update', ['workspace' => $workspaceA, 'category' => $category]), [
                'name' => 'Hacked',
            ]);

        $response->assertNotFound();
    }
}
