<?php

declare(strict_types=1);

namespace Tests\Feature\Transactions;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class TransactionSmokeTest extends TestCase
{
    public function test_transactions_index_returns_ok(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)->get(route('transactions.index', $workspace));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Transactions/Index'));
    }

    public function test_transactions_index_returns_ok_with_month_param(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)->get(route('transactions.index', $workspace).'?month=2026-09');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Transactions/Index'));
    }
}
