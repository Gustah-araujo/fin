<?php

declare(strict_types=1);

namespace Tests\Feature\Cards;

class CardShowSmokeTest extends CardTestCase
{
    /** @test */
    public function test_card_show_page_returns_ok(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);

        $response = $this->actingAs($user)
            ->get(route('cards.show', [$workspace, $card]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Cards/Show'));
    }
}
