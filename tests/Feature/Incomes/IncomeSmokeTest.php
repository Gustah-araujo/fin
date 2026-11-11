<?php

declare(strict_types=1);

namespace Tests\Feature\Incomes;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class IncomeSmokeTest extends TestCase
{
    public function test_incomes_index_returns_ok(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)->get(route('incomes.index', $workspace));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Incomes/Index'));
    }

    public function test_incomes_index_returns_ok_with_month_param(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)->get(route('incomes.index', $workspace).'?month=2026-01');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Incomes/Index'));
    }
}
