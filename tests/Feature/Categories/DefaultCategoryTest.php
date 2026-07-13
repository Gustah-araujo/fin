<?php

namespace Tests\Feature\Categories;

use App\Enums\WorkspaceRole;
use App\Models\Category;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class DefaultCategoryTest extends TestCase
{
    public function test_default_category_is_created_with_workspace(): void
    {
        $user = User::factory()->create();
        $user->markEmailAsVerified();

        $response = $this->actingAs($user)
            ->post(route('workspace.store'), [
                'name' => 'Meu Workspace',
            ]);

        $response->assertRedirect();
        $workspace = Workspace::first();

        $categories = $this->actingAs($user)
            ->get(route('categories.index', $workspace));

        $categories->assertInertia(fn ($page) => $page
            ->component('Categories/Index', false)
            ->has('categories', 1)
        );

        $this->assertDatabaseHas('categories', [
            'workspace_id' => $workspace->id,
            'name' => 'Sem Categoria',
            'type' => 'both',
            'color' => '#9CA3AF',
            'icon' => 'folder',
        ]);
    }

    public function test_cannot_delete_default_category(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $defaultCategory = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'name' => 'Sem Categoria',
            'type' => 'both',
            'color' => '#9CA3AF',
            'icon' => 'folder',
        ]);

        $response = $this->actingAs($user)
            ->delete(route('categories.destroy', ['workspace' => $workspace, 'category' => $defaultCategory]));

        $response->assertForbidden();
    }
}
