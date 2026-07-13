<?php

namespace Tests\Feature\Categories;

use App\Enums\WorkspaceRole;
use App\Models\Category;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class CategoryCreationTest extends TestCase
{
    public function test_user_can_create_category(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->post(route('categories.store', $workspace), [
                'name' => 'Alimentação',
                'type' => 'expense',
                'color' => '#FF5733',
                'icon' => 'utensils',
            ]);

        $response->assertRedirect(route('categories.index', $workspace));

        $this->assertDatabaseHas('categories', [
            'workspace_id' => $workspace->id,
            'name' => 'Alimentação',
            'type' => 'expense',
            'color' => '#FF5733',
            'icon' => 'utensils',
        ]);
    }

    public function test_category_list_shows_categories(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'name' => 'Alimentação',
            'type' => 'expense',
        ]);
        Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'name' => 'Salário',
            'type' => 'income',
        ]);

        $response = $this->actingAs($user)
            ->get(route('categories.index', $workspace));

        $response->assertInertia(fn ($page) => $page
            ->component('Categories/Index', false)
            ->has('categories', 2)
        );
    }

    public function test_validation_errors_on_create(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->post(route('categories.store', $workspace), []);

        $response->assertSessionHasErrors(['name', 'type', 'color']);
    }

    public function test_rejects_invalid_color_hex(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->post(route('categories.store', $workspace), [
                'name' => 'Teste',
                'type' => 'expense',
                'color' => 'invalid',
            ]);

        $response->assertSessionHasErrors(['color']);
    }

    public function test_accepts_empty_icon(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->post(route('categories.store', $workspace), [
                'name' => 'Transporte',
                'type' => 'expense',
                'color' => '#3B82F6',
                'icon' => '',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('categories', [
            'name' => 'Transporte',
            'icon' => null,
        ]);
    }

    public function test_color_is_normalized_to_uppercase(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->post(route('categories.store', $workspace), [
                'name' => 'Lazer',
                'type' => 'expense',
                'color' => '#ff5733',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('categories', [
            'name' => 'Lazer',
            'color' => '#FF5733',
        ]);
    }
}
