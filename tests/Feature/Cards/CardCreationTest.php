<?php

namespace Tests\Feature\Cards;

use App\Enums\WorkspaceRole;
use App\Models\CreditCard;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class CardCreationTest extends TestCase
{
    public function test_user_can_create_credit_card(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->post(route('cards.store', $workspace), [
                'name' => 'Nubank Mastercard',
                'credit_limit' => 10000,
                'closing_day' => 1,
                'due_day' => 10,
            ]);

        $response->assertRedirect(route('cards.index', $workspace));

        $this->assertDatabaseHas('credit_cards', [
            'workspace_id' => $workspace->id,
            'name' => 'Nubank Mastercard',
            'credit_limit' => 10000,
            'closing_day' => 1,
            'due_day' => 10,
        ]);
    }

    public function test_available_limit_equals_credit_limit_on_create(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $this->actingAs($user)
            ->post(route('cards.store', $workspace), [
                'name' => 'Inter Card',
                'credit_limit' => 5000,
                'closing_day' => 5,
                'due_day' => 15,
            ]);

        $this->assertDatabaseHas('credit_cards', [
            'name' => 'Inter Card',
            'credit_limit' => 5000,
            'available_limit' => 5000,
        ]);
    }

    public function test_validation_errors_on_create(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->post(route('cards.store', $workspace), []);

        $response->assertSessionHasErrors(['name', 'credit_limit', 'closing_day', 'due_day']);
    }

    public function test_closing_day_must_be_between_1_and_31(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->post(route('cards.store', $workspace), [
                'name' => 'Card',
                'credit_limit' => 1000,
                'closing_day' => 0,
                'due_day' => 10,
            ]);

        $response->assertSessionHasErrors(['closing_day']);

        $response = $this->actingAs($user)
            ->post(route('cards.store', $workspace), [
                'name' => 'Card 2',
                'credit_limit' => 1000,
                'closing_day' => 32,
                'due_day' => 10,
            ]);

        $response->assertSessionHasErrors(['closing_day']);
    }

    public function test_due_day_must_be_between_1_and_31(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->post(route('cards.store', $workspace), [
                'name' => 'Card',
                'credit_limit' => 1000,
                'closing_day' => 10,
                'due_day' => 0,
            ]);

        $response->assertSessionHasErrors(['due_day']);

        $response = $this->actingAs($user)
            ->post(route('cards.store', $workspace), [
                'name' => 'Card 2',
                'credit_limit' => 1000,
                'closing_day' => 10,
                'due_day' => 32,
            ]);

        $response->assertSessionHasErrors(['due_day']);
    }

    public function test_credit_limit_cannot_be_negative(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->post(route('cards.store', $workspace), [
                'name' => 'Negative Card',
                'credit_limit' => -100,
                'closing_day' => 1,
                'due_day' => 10,
            ]);

        $response->assertSessionHasErrors(['credit_limit']);
    }

    public function test_credit_card_with_zero_limit_is_accepted(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->post(route('cards.store', $workspace), [
                'name' => 'Zero Limit Card',
                'credit_limit' => 0,
                'closing_day' => 1,
                'due_day' => 10,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('credit_cards', [
            'name' => 'Zero Limit Card',
            'credit_limit' => 0,
            'available_limit' => 0,
        ]);
    }
}
