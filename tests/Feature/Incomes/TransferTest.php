<?php

declare(strict_types=1);

namespace Tests\Feature\Incomes;

use App\Models\Category;
use App\Models\Transaction;

class TransferTest extends IncomeTestCase
{
    public function test_create_transfer_decrements_origin_and_increments_destination(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $accountA = $this->createAccount($workspace, $user, 1000);
        $accountB = $this->createAccount($workspace, $user, 500);

        $response = $this->actingAs($user)
            ->post(route('transfers.store', $workspace), [
                'description' => 'Transferência poupança',
                'value' => 300,
                'date' => '2026-05-15',
                'from_account_id' => $accountA->uuid,
                'to_account_id' => $accountB->uuid,
            ]);

        $response->assertRedirect(route('incomes.index', $workspace));

        $accountA->refresh();
        $accountB->refresh();
        $this->assertEquals(700, (float) $accountA->current_balance);
        $this->assertEquals(800, (float) $accountB->current_balance);
    }

    public function test_total_workspace_balance_is_invariant(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $accountA = $this->createAccount($workspace, $user, 1000);
        $accountB = $this->createAccount($workspace, $user, 500);

        $this->actingAs($user)
            ->post(route('transfers.store', $workspace), [
                'description' => 'T', 'value' => 400, 'date' => '2026-05-15',
                'from_account_id' => $accountA->uuid, 'to_account_id' => $accountB->uuid,
            ]);

        $accountA->refresh();
        $accountB->refresh();
        $this->assertEquals(1500, (float) $accountA->current_balance + (float) $accountB->current_balance);
    }

    public function test_transfer_creates_two_legs_with_same_transfer_group_id(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $accountA = $this->createAccount($workspace, $user);
        $accountB = $this->createAccount($workspace, $user);

        $this->actingAs($user)
            ->post(route('transfers.store', $workspace), [
                'description' => 'T', 'value' => 100, 'date' => '2026-05-15',
                'from_account_id' => $accountA->uuid, 'to_account_id' => $accountB->uuid,
            ]);

        $legs = Transaction::where('description', 'T')->whereNotNull('transfer_group_id')->get();
        $this->assertCount(2, $legs);
        $this->assertEquals($legs[0]->transfer_group_id, $legs[1]->transfer_group_id);
        $this->assertCount(1, $legs->where('type', 'expense'));
        $this->assertCount(1, $legs->where('type', 'income'));
        $this->assertTrue($legs->every(fn ($r) => $r->paid_at !== null));
    }

    public function test_from_equals_to_is_rejected(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $accountA = $this->createAccount($workspace, $user);

        $response = $this->actingAs($user)
            ->post(route('transfers.store', $workspace), [
                'description' => 'T', 'value' => 100, 'date' => '2026-05-15',
                'from_account_id' => $accountA->uuid, 'to_account_id' => $accountA->uuid,
            ]);

        $response->assertSessionHasErrors(['from_account_id']);
    }

    public function test_cross_workspace_account_is_rejected(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        [$otherUser, $otherWorkspace] = $this->createWorkspaceWithMember();
        $accountA = $this->createAccount($workspace, $user);
        $accountB = $this->createAccount($otherWorkspace, $otherUser);

        $response = $this->actingAs($user)
            ->post(route('transfers.store', $workspace), [
                'description' => 'Hacker', 'value' => 100, 'date' => '2026-05-15',
                'from_account_id' => $accountA->uuid, 'to_account_id' => $accountB->uuid,
            ]);

        $response->assertSessionHasErrors(['to_account_id']);
    }

    public function test_categoria_transferencia_auto_created_once(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $accountA = $this->createAccount($workspace, $user);
        $accountB = $this->createAccount($workspace, $user);

        $this->actingAs($user)
            ->post(route('transfers.store', $workspace), [
                'description' => 'T1', 'value' => 100, 'date' => '2026-05-15',
                'from_account_id' => $accountA->uuid, 'to_account_id' => $accountB->uuid,
            ]);

        $categories = Category::where('workspace_id', $workspace->id)->where('is_system', true)->where('name', 'Transferência')->get();
        $this->assertCount(1, $categories);
        $this->assertEquals('both', $categories[0]->type->value);

        $this->actingAs($user)
            ->post(route('transfers.store', $workspace), [
                'description' => 'T2', 'value' => 50, 'date' => '2026-05-16',
                'from_account_id' => $accountA->uuid, 'to_account_id' => $accountB->uuid,
            ]);

        $this->assertEquals(1, Category::where('workspace_id', $workspace->id)->where('name', 'Transferência')->where('is_system', true)->count());
    }

    public function test_update_value_recalcs_both_accounts(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $accountA = $this->createAccount($workspace, $user, 1000);
        $accountB = $this->createAccount($workspace, $user, 500);

        $this->actingAs($user)
            ->post(route('transfers.store', $workspace), [
                'description' => 'T', 'value' => 300, 'date' => '2026-05-15',
                'from_account_id' => $accountA->uuid, 'to_account_id' => $accountB->uuid,
            ]);
        $transferGroupId = Transaction::where('description', 'T')->value('transfer_group_id');

        $this->actingAs($user)
            ->put(route('transfers.update', [$workspace, $transferGroupId]), [
                'description' => 'T Updated', 'value' => 500, 'date' => '2026-05-15',
                'from_account_id' => $accountA->uuid, 'to_account_id' => $accountB->uuid,
            ]);

        $accountA->refresh();
        $accountB->refresh();
        $this->assertEquals(500, (float) $accountA->current_balance);
        $this->assertEquals(1000, (float) $accountB->current_balance);
    }

    public function test_delete_reverts_both_accounts(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $accountA = $this->createAccount($workspace, $user, 1000);
        $accountB = $this->createAccount($workspace, $user, 500);

        $this->actingAs($user)
            ->post(route('transfers.store', $workspace), [
                'description' => 'T', 'value' => 300, 'date' => '2026-05-15',
                'from_account_id' => $accountA->uuid, 'to_account_id' => $accountB->uuid,
            ]);
        $transferGroupId = Transaction::where('description', 'T')->value('transfer_group_id');

        $this->actingAs($user)
            ->delete(route('transfers.destroy', [$workspace, $transferGroupId]));

        $accountA->refresh();
        $accountB->refresh();
        $this->assertEquals(1000, (float) $accountA->current_balance);
        $this->assertEquals(500, (float) $accountB->current_balance);
        $this->assertEquals(0, Transaction::where('transfer_group_id', $transferGroupId)->count());
    }

    public function test_income_index_excludes_transfer_legs(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $accountA = $this->createAccount($workspace, $user);
        $accountB = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);

        Transaction::factory()->income()->create([
            'workspace_id' => $workspace->id, 'account_id' => $accountA->id, 'category_id' => $category->id, 'created_by' => $user->id,
            'description' => 'Real', 'date' => '2026-05-01', 'value' => 100,
        ]);

        $this->actingAs($user)
            ->post(route('transfers.store', $workspace), [
                'description' => 'Leggy', 'value' => 200, 'date' => '2026-05-15',
                'from_account_id' => $accountA->uuid, 'to_account_id' => $accountB->uuid,
            ]);

        $response = $this->actingAs($user)
            ->get(route('incomes.index', $workspace));

        $response->assertInertia(fn ($page) => $page
            ->has('incomes.data', 1)
            ->where('incomes.data.0.description', 'Real'));
    }

    public function test_viewer_cannot_create_transfer(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $viewer = $this->attachMember($workspace, 'viewer');
        $accountA = $this->createAccount($workspace, $user);
        $accountB = $this->createAccount($workspace, $user);

        $response = $this->actingAs($viewer)
            ->post(route('transfers.store', $workspace), [
                'description' => 'T', 'value' => 100, 'date' => '2026-05-15',
                'from_account_id' => $accountA->uuid, 'to_account_id' => $accountB->uuid,
            ]);

        $response->assertForbidden();
    }
}
