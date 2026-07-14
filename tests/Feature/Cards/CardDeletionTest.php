<?php

namespace Tests\Feature\Cards;

use App\Enums\WorkspaceRole;
use App\Models\CreditCard;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class CardDeletionTest extends TestCase
{
    public function test_admin_can_soft_delete_card(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $card = CreditCard::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'name' => 'To Delete',
        ]);

        $response = $this->actingAs($user)
            ->delete(route('cards.destroy', [$workspace, $card]));

        $response->assertRedirect(route('cards.index', $workspace));

        $this->assertSoftDeleted($card);
    }

    public function test_soft_deleted_card_not_in_list(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $card = CreditCard::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'name' => 'Archived',
        ]);

        $this->actingAs($user)
            ->delete(route('cards.destroy', [$workspace, $card]));

        $response = $this->actingAs($user)
            ->get(route('cards.index', $workspace));

        $response->assertInertia(fn ($page) => $page
            ->component('Cards/Index', false)
            ->has('cards', 0)
        );
    }
}
