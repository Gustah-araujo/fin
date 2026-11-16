<?php

namespace Tests\Feature\Categories;

use App\Enums\WorkspaceRole;
use App\Models\Category;
use App\Models\ExpenseGroup;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class CategoryExpenseGroupTest extends TestCase
{
    public function test_user_can_create_category_with_expense_group(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $group = ExpenseGroup::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Moradia',
        ]);

        $response = $this->actingAs($user)
            ->post(route('categories.store', $workspace), [
                'name' => 'Aluguel',
                'type' => 'expense',
                'color' => '#FF5733',
                'expense_group_id' => $group->uuid,
            ]);

        $response->assertRedirect(route('categories.index', $workspace));

        $this->assertDatabaseHas('categories', [
            'workspace_id' => $workspace->id,
            'name' => 'Aluguel',
            'expense_group_id' => ExpenseGroup::where('uuid', $group->uuid)->first()->id,
        ]);
    }

    public function test_create_rejects_expense_group_from_another_workspace(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $otherWorkspace = Workspace::factory()->create();
        $foreignGroup = ExpenseGroup::factory()->create([
            'workspace_id' => $otherWorkspace->id,
        ]);

        $response = $this->actingAs($user)
            ->post(route('categories.store', $workspace), [
                'name' => 'Aluguel',
                'type' => 'expense',
                'color' => '#FF5733',
                'expense_group_id' => $foreignGroup->uuid,
            ]);

        $response->assertSessionHasErrors(['expense_group_id']);
    }

    public function test_create_category_without_expense_group_stores_null(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->post(route('categories.store', $workspace), [
                'name' => 'Mercado',
                'type' => 'expense',
                'color' => '#FF5733',
                'expense_group_id' => '',
            ]);

        $response->assertRedirect(route('categories.index', $workspace));

        $this->assertDatabaseHas('categories', [
            'workspace_id' => $workspace->id,
            'name' => 'Mercado',
            'expense_group_id' => null,
        ]);
    }

    public function test_user_can_change_and_clear_expense_group_on_update(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $group = ExpenseGroup::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Saúde',
        ]);

        $this->actingAs($user)
            ->put(route('categories.update', ['workspace' => $workspace, 'category' => $category]), [
                'expense_group_id' => $group->uuid,
            ])
            ->assertRedirect(route('categories.index', $workspace));

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'expense_group_id' => ExpenseGroup::where('uuid', $group->uuid)->first()->id,
        ]);

        $this->actingAs($user)
            ->put(route('categories.update', ['workspace' => $workspace, 'category' => $category]), [
                'expense_group_id' => '',
            ])
            ->assertRedirect(route('categories.index', $workspace));

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'expense_group_id' => null,
        ]);
    }

    public function test_index_and_create_pages_expose_expense_group_shape(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $group = ExpenseGroup::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Lazer',
            'color' => '#10B981',
        ]);

        Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'name' => 'Cinema',
            'expense_group_id' => $group->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('categories.index', $workspace));

        $response->assertInertia(fn ($page) => $page
            ->component('Categories/Index', false)
            ->has('categories', 1)
            ->where('categories.0.expense_group.uuid', $group->uuid)
            ->where('categories.0.expense_group.name', 'Lazer')
            ->where('categories.0.expense_group.color', '#10B981')
        );

        $createResponse = $this->actingAs($user)
            ->get(route('categories.create', $workspace));

        $createResponse->assertInertia(fn ($page) => $page
            ->component('Categories/Create', false)
            ->has('expenseGroups', 1)
        );
    }

    public function test_edit_page_exposes_category_expense_group(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $group = ExpenseGroup::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Transporte',
        ]);

        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'expense_group_id' => $group->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('categories.edit', ['workspace' => $workspace, 'category' => $category]));

        $response->assertInertia(fn ($page) => $page
            ->component('Categories/Edit', false)
            ->where('category.expense_group.uuid', $group->uuid)
            ->where('category.expense_group.name', 'Transporte')
            ->has('expenseGroups', 1)
        );
    }
}
