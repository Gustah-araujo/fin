<?php

namespace Tests\Feature\ExpenseGroups;

use App\Enums\WorkspaceRole;
use App\Models\Account;
use App\Models\Category;
use App\Models\ExpenseGroup;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class ExpenseGroupCrudTest extends TestCase
{
    public function test_user_can_create_expense_group(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->post(route('expense-groups.store', $workspace), [
                'name' => 'Essencial',
                'color' => '#FF5733',
            ]);

        $response->assertRedirect(route('expense-groups.index', $workspace));

        $this->assertDatabaseHas('expense_groups', [
            'workspace_id' => $workspace->id,
            'name' => 'Essencial',
            'color' => '#FF5733',
        ]);
    }

    public function test_color_is_normalized_to_uppercase(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->post(route('expense-groups.store', $workspace), [
                'name' => 'Lazer',
                'color' => '#ff5733',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('expense_groups', [
            'name' => 'Lazer',
            'color' => '#FF5733',
        ]);
    }

    public function test_user_can_update_expense_group(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $group = ExpenseGroup::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        $response = $this->actingAs($user)
            ->put(route('expense-groups.update', ['workspace' => $workspace, 'expense_group' => $group]), [
                'name' => 'Assinaturas',
                'color' => '#3B82F6',
            ]);

        $response->assertRedirect(route('expense-groups.index', $workspace));

        $this->assertDatabaseHas('expense_groups', [
            'id' => $group->id,
            'name' => 'Assinaturas',
            'color' => '#3B82F6',
        ]);
    }

    public function test_user_can_delete_expense_group_and_references_are_nulled(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $group = ExpenseGroup::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'expense_group_id' => $group->id,
        ]);

        $account = Account::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        $transaction = Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'expense_group_id' => $group->id,
        ]);

        $response = $this->actingAs($user)
            ->delete(route('expense-groups.destroy', ['workspace' => $workspace, 'expense_group' => $group]));

        $response->assertRedirect(route('expense-groups.index', $workspace));

        $this->assertDatabaseMissing('expense_groups', ['id' => $group->id]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'expense_group_id' => null,
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'expense_group_id' => null,
        ]);
    }

    public function test_index_returns_expense_groups_inertia_shape(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $group = ExpenseGroup::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Essencial',
            'color' => '#FF5733',
        ]);

        $response = $this->actingAs($user)
            ->get(route('expense-groups.index', $workspace));

        $response->assertInertia(fn ($page) => $page
            ->component('ExpenseGroups/Index', false)
            ->has('expenseGroups', 1)
            ->where('expenseGroups.0.uuid', $group->uuid)
            ->where('expenseGroups.0.name', 'Essencial')
            ->where('expenseGroups.0.color', '#FF5733')
        );
    }
}
