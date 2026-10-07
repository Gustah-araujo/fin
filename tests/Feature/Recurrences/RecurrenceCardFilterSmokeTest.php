<?php

declare(strict_types=1);

namespace Tests\Feature\Recurrences;

use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class RecurrenceCardFilterSmokeTest extends TestCase
{
    /** @test */
    public function test_recurrences_index_returns_ok(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => 'admin']);

        $response = $this->actingAs($user)
            ->get(route('recurrences.index', $workspace));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Recurrences/Index'));
    }
}
