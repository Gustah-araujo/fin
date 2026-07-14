<?php

namespace Tests\Feature\Cards;

use App\Enums\WorkspaceRole;
use App\Models\CreditCard;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class CardListTest extends TestCase
{
    public function test_index_displays_all_card_fields(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        CreditCard::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'name' => 'Nubank',
            'credit_limit' => 10000,
            'available_limit' => 8000,
            'closing_day' => 15,
            'due_day' => 25,
        ]);

        $response = $this->actingAs($user)
            ->get(route('cards.index', $workspace));

        $response->assertInertia(fn ($page) => $page
            ->component('Cards/Index', false)
            ->has('cards', 1)
            ->where('cards.0.uuid', fn ($uuid) => is_string($uuid))
            ->where('cards.0.name', 'Nubank')
            ->where('cards.0.credit_limit', fn ($v) => $v == 10000)
            ->where('cards.0.available_limit', fn ($v) => $v == 8000)
            ->where('cards.0.closing_day', 15)
            ->where('cards.0.due_day', 25)
        );
    }

    public function test_empty_workspace_shows_empty_state(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->get(route('cards.index', $workspace));

        $response->assertInertia(fn ($page) => $page
            ->component('Cards/Index', false)
            ->has('cards', 0)
        );
    }
}
