<?php

declare(strict_types=1);

namespace Tests\Feature\Fin;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class FinSmokeTest extends TestCase
{
    public function test_fin_index_returns_ok(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)->get(route('fin.index', $workspace));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Fin/Index'));
    }
}
