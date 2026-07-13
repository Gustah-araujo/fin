<?php

namespace Tests\Feature\Categories;

use App\Enums\WorkspaceRole;
use App\Models\Category;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class CategoryDeletionTest extends TestCase
{
    public function test_soft_deletes_category(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->delete(route('categories.destroy', ['workspace' => $workspace, 'category' => $category]));

        $response->assertRedirect(route('categories.index', $workspace));

        $this->assertSoftDeleted('categories', ['id' => $category->id]);
    }
}
