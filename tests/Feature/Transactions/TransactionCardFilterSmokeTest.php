<?php

declare(strict_types=1);

namespace Tests\Feature\Transactions;

use App\Models\CreditCard;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class TransactionCardFilterSmokeTest extends TestCase
{
    /** @test */
    public function test_transactions_index_returns_with_card_data(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => 'admin']);

        // Create a card so there's card data in the props
        CreditCard::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('transactions.index', $workspace));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Transactions/Index')
            ->has('cards')
            ->has('bills')
        );
    }
}
