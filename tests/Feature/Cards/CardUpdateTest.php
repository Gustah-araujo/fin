<?php

namespace Tests\Feature\Cards;

use App\Enums\WorkspaceRole;
use App\Models\CreditCard;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class CardUpdateTest extends TestCase
{
    public function test_user_can_update_card_name(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $card = CreditCard::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'name' => 'Original Name',
        ]);

        $response = $this->actingAs($user)
            ->put(route('cards.update', [$workspace, $card]), [
                'name' => 'Updated Name',
            ]);

        $response->assertRedirect(route('cards.index', $workspace));

        $this->assertDatabaseHas('credit_cards', [
            'id' => $card->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_user_can_update_closing_and_due_day(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $card = CreditCard::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'closing_day' => 1,
            'due_day' => 10,
        ]);

        $response = $this->actingAs($user)
            ->put(route('cards.update', [$workspace, $card]), [
                'closing_day' => 15,
                'due_day' => 25,
            ]);

        $response->assertRedirect(route('cards.index', $workspace));

        $this->assertDatabaseHas('credit_cards', [
            'id' => $card->id,
            'closing_day' => 15,
            'due_day' => 25,
        ]);
    }

    public function test_updating_credit_limit_recalculates_available_limit(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $card = CreditCard::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'credit_limit' => 5000,
            'available_limit' => 5000,
        ]);

        $this->actingAs($user)
            ->put(route('cards.update', [$workspace, $card]), [
                'credit_limit' => 8000,
            ]);

        $this->assertDatabaseHas('credit_cards', [
            'id' => $card->id,
            'credit_limit' => 8000,
            'available_limit' => 8000,
        ]);
    }

    public function test_validation_errors_on_update(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $card = CreditCard::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->put(route('cards.update', [$workspace, $card]), [
                'closing_day' => 99,
            ]);

        $response->assertSessionHasErrors(['closing_day']);
    }
}
