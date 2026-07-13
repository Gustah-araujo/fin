<?php

namespace Tests\Feature\Categories;

use App\Enums\WorkspaceRole;
use App\Models\Category;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class CategoryUpdateTest extends TestCase
{
    public function test_user_can_update_category(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'name' => 'Original',
            'type' => 'expense',
        ]);

        $response = $this->actingAs($user)
            ->put(route('categories.update', ['workspace' => $workspace, 'category' => $category]), [
                'name' => 'Atualizada',
            ]);

        $response->assertRedirect(route('categories.index', $workspace));

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Atualizada',
            'type' => 'expense',
        ]);
    }

    public function test_cannot_update_category_with_invalid_type(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->put(route('categories.update', ['workspace' => $workspace, 'category' => $category]), [
                'type' => 'invalid',
            ]);

        $response->assertSessionHasErrors(['type']);
    }
}
