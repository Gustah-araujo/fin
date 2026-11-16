<?php

namespace Tests\Feature\ExpenseGroups;

use App\Enums\WorkspaceRole;
use App\Models\ExpenseGroup;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class ExpenseGroupAuthorizationTest extends TestCase
{
    public function test_viewer_cannot_create_expense_group(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Viewer->value]);

        $response = $this->actingAs($user)
            ->post(route('expense-groups.store', $workspace), [
                'name' => 'Teste',
                'color' => '#FF5733',
            ]);

        $response->assertForbidden();
    }

    public function test_viewer_cannot_update_expense_group(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Viewer->value]);

        $admin = User::factory()->create();
        $workspace->members()->attach($admin, ['role' => WorkspaceRole::Admin->value]);

        $group = ExpenseGroup::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        $response = $this->actingAs($user)
            ->put(route('expense-groups.update', ['workspace' => $workspace, 'expense_group' => $group]), [
                'name' => 'Hacked',
            ]);

        $response->assertForbidden();
    }

    public function test_viewer_cannot_delete_expense_group(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Viewer->value]);

        $admin = User::factory()->create();
        $workspace->members()->attach($admin, ['role' => WorkspaceRole::Admin->value]);

        $group = ExpenseGroup::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        $response = $this->actingAs($user)
            ->delete(route('expense-groups.destroy', ['workspace' => $workspace, 'expense_group' => $group]));

        $response->assertForbidden();
    }

    public function test_non_member_cannot_create_expense_group(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('expense-groups.store', $workspace), [
                'name' => 'Teste',
                'color' => '#FF5733',
            ]);

        $response->assertForbidden();
    }

    public function test_cannot_access_expense_group_from_other_workspace(): void
    {
        $user = User::factory()->create();
        $workspaceA = Workspace::factory()->create();
        $workspaceA->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $otherUser = User::factory()->create();
        $workspaceB = Workspace::factory()->create();
        $workspaceB->members()->attach($otherUser, ['role' => WorkspaceRole::Admin->value]);

        $group = ExpenseGroup::factory()->create([
            'workspace_id' => $workspaceB->id,
        ]);

        $response = $this->actingAs($user)
            ->put(route('expense-groups.update', ['workspace' => $workspaceA, 'expense_group' => $group]), [
                'name' => 'Hacked',
            ]);

        $response->assertNotFound();
    }

    public function test_validation_fails_when_name_is_missing(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->post(route('expense-groups.store', $workspace), [
                'color' => '#FF5733',
            ]);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_validation_fails_when_color_is_invalid(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->post(route('expense-groups.store', $workspace), [
                'name' => 'Teste',
                'color' => 'invalid',
            ]);

        $response->assertSessionHasErrors(['color']);
    }
}
