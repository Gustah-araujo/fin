<?php

declare(strict_types=1);

namespace Tests\Feature\Transactions;

use App\Enums\WorkspaceRole;
use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class TransactionRecurrenceValidationTest extends TestCase
{
    private function basePayload(Workspace $workspace, User $user): array
    {
        $account = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
        ]);

        return [
            'description' => 'Netflix',
            'value' => 39.90,
            'date' => now()->format('Y-m-d'),
            'account_id' => $account->uuid,
            'category_id' => $category->uuid,
        ];
    }

    public function test_rejects_is_recurring_without_frequency(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->post(route('transactions.store', $workspace), array_merge(
                $this->basePayload($workspace, $user),
                ['is_recurring' => true],
            ));

        $response->assertSessionHasErrors(['frequency']);
    }

    public function test_rejects_invalid_frequency_day(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        // Weekly frequency_day=7 is invalid (valid range: 0-6)
        $response = $this->actingAs($user)
            ->post(route('transactions.store', $workspace), array_merge(
                $this->basePayload($workspace, $user),
                ['is_recurring' => true, 'frequency' => 'weekly', 'frequency_day' => 7],
            ));

        $response->assertSessionHasErrors(['frequency_day']);

        // Monthly frequency_day=32 is invalid (valid range: 1-31)
        $response = $this->actingAs($user)
            ->post(route('transactions.store', $workspace), array_merge(
                $this->basePayload($workspace, $user),
                ['is_recurring' => true, 'frequency' => 'monthly', 'frequency_day' => 32],
            ));

        $response->assertSessionHasErrors(['frequency_day']);
    }

    public function test_accepts_valid_recurrence_payload(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $payload = array_merge(
            $this->basePayload($workspace, $user),
            ['is_recurring' => true, 'frequency' => 'monthly', 'frequency_day' => 15],
        );

        $response = $this->actingAs($user)
            ->post(route('transactions.store', $workspace), $payload);

        $response->assertSessionHasNoErrors();

        // Verify recurrence was created (will fail until controller handles recurrence)
        $this->assertDatabaseHas('recurrences', [
            'workspace_id' => $workspace->id,
            'frequency' => 'monthly',
            'frequency_day' => 15,
        ]);
    }
}
