<?php

namespace Tests\Feature\ExpenseGroups;

use App\Enums\WorkspaceRole;
use App\Models\ExpenseGroup;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class ExpenseGroupSmokeTest extends TestCase
{
    public function test_index_page_returns_ok(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->get(route('expense-groups.index', $workspace));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('ExpenseGroups/Index'));
    }

    public function test_create_page_returns_ok(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->get(route('expense-groups.create', $workspace));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('ExpenseGroups/Create'));
    }

    public function test_edit_page_returns_ok(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $group = ExpenseGroup::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('expense-groups.edit', ['workspace' => $workspace, 'expense_group' => $group]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('ExpenseGroups/Edit'));
    }
}
