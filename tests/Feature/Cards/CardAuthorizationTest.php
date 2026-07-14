<?php

namespace Tests\Feature\Cards;

use App\Enums\WorkspaceRole;
use App\Models\CreditCard;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class CardAuthorizationTest extends TestCase
{
    public function test_viewer_can_see_card_list(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Viewer->value]);

        CreditCard::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('cards.index', $workspace));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Cards/Index', false)
            ->has('cards', 1)
        );
    }

    public function test_viewer_cannot_create_card(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Viewer->value]);

        $response = $this->actingAs($user)
            ->post(route('cards.store', $workspace), [
                'name' => 'Test',
                'credit_limit' => 1000,
                'closing_day' => 1,
                'due_day' => 10,
            ]);

        $response->assertForbidden();
    }

    public function test_viewer_cannot_update_card(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Viewer->value]);

        $card = CreditCard::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->put(route('cards.update', [$workspace, $card]), [
                'name' => 'Hacked',
            ]);

        $response->assertForbidden();
    }

    public function test_viewer_cannot_delete_card(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Viewer->value]);

        $card = CreditCard::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->delete(route('cards.destroy', [$workspace, $card]));

        $response->assertForbidden();
    }

    public function test_editor_can_create_card(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Editor->value]);

        $response = $this->actingAs($user)
            ->post(route('cards.store', $workspace), [
                'name' => 'Editor Card',
                'credit_limit' => 2000,
                'closing_day' => 1,
                'due_day' => 10,
            ]);

        $response->assertRedirect(route('cards.index', $workspace));
    }

    public function test_editor_can_update_card(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Editor->value]);

        $card = CreditCard::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->put(route('cards.update', [$workspace, $card]), [
                'name' => 'Editor Updated',
            ]);

        $response->assertRedirect(route('cards.index', $workspace));
    }

    public function test_only_admin_can_delete_card(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Editor->value]);

        $card = CreditCard::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->delete(route('cards.destroy', [$workspace, $card]));

        $response->assertForbidden();
    }

    public function test_cannot_access_card_from_other_workspace(): void
    {
        $user = User::factory()->create();
        $workspaceA = Workspace::factory()->create();
        $workspaceA->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $workspaceB = Workspace::factory()->create();
        $workspaceB->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $cardInB = CreditCard::factory()->create([
            'workspace_id' => $workspaceB->id,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->put(route('cards.update', [$workspaceA, $cardInB]), [
                'name' => 'Hacked',
            ]);

        $response->assertNotFound();
    }
}
