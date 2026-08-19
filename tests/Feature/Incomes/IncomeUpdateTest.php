<?php

declare(strict_types=1);

namespace Tests\Feature\Incomes;

use App\Models\Transaction;
use App\Services\AccountService;

class IncomeUpdateTest extends IncomeTestCase
{
    public function test_edit_page_loads_for_unpaid_income(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);
        $income = Transaction::factory()->income()->create([
            'workspace_id' => $workspace->id, 'account_id' => $account->id, 'category_id' => $category->id, 'created_by' => $user->id,
            'description' => 'Salário',
        ]);

        $response = $this->actingAs($user)
            ->get(route('incomes.edit', [$workspace, $income]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Incomes/Edit', false));
    }

    public function test_update_unpaid_income_changes_fields(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);
        $income = Transaction::factory()->income()->create([
            'workspace_id' => $workspace->id, 'account_id' => $account->id, 'category_id' => $category->id, 'created_by' => $user->id,
            'description' => 'Old',
            'value' => 100,
        ]);

        $response = $this->actingAs($user)
            ->put(route('incomes.update', [$workspace, $income]), [
                'description' => 'Updated Salario',
                'value' => 150,
                'date' => '2026-06-15',
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
            ]);

        $response->assertRedirect(route('incomes.index', $workspace));
        $income->refresh();
        $this->assertEquals('Updated Salario', $income->description);
        $this->assertEquals(150, (float) $income->value);
    }

    public function test_update_paid_value_recalcs_account_balance(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user, 1000);
        $category = $this->createIncomeCategory($workspace, $user);
        $income = Transaction::factory()->income()->paid()->create([
            'workspace_id' => $workspace->id, 'account_id' => $account->id, 'category_id' => $category->id, 'created_by' => $user->id,
            'value' => 100,
            'date' => '2026-05-01',
        ]);

        app(AccountService::class)->recalculateBalance($account);
        $account->refresh();
        $this->assertEquals(1100, (float) $account->current_balance);

        $this->actingAs($user)
            ->put(route('incomes.update', [$workspace, $income]), [
                'description' => 'Salário Updated',
                'value' => 300,
                'date' => '2026-05-01',
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
            ]);

        $account->refresh();
        $this->assertEquals(1300, (float) $account->current_balance);
    }

    public function test_update_paid_account_moves_recalcs_both(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $accountA = $this->createAccount($workspace, $user, 1000);
        $accountB = $this->createAccount($workspace, $user, 500);
        $category = $this->createIncomeCategory($workspace, $user);
        $income = Transaction::factory()->income()->paid()->create([
            'workspace_id' => $workspace->id, 'account_id' => $accountA->id, 'category_id' => $category->id, 'created_by' => $user->id,
            'value' => 200,
        ]);
        app(AccountService::class)->recalculateBalance($accountA);
        app(AccountService::class)->recalculateBalance($accountB);

        $this->actingAs($user)
            ->put(route('incomes.update', [$workspace, $income]), [
                'description' => 'Salário',
                'value' => 200,
                'date' => '2026-05-01',
                'account_id' => $accountB->uuid,
                'category_id' => $category->uuid,
            ]);

        $accountA->refresh();
        $accountB->refresh();
        $this->assertEquals(1000, (float) $accountA->current_balance);
        $this->assertEquals(700, (float) $accountB->current_balance);
    }

    public function test_update_cross_workspace_returns_404(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);
        $income = Transaction::factory()->income()->create([
            'workspace_id' => $workspace->id, 'account_id' => $account->id, 'category_id' => $category->id, 'created_by' => $user->id,
        ]);

        [$otherUser, $otherWorkspace] = $this->createWorkspaceWithMember();

        $response = $this->actingAs($otherUser)
            ->put(route('incomes.update', [$otherWorkspace, $income->uuid]), [
                'description' => 'Hacker',
            ]);

        $response->assertNotFound();
    }

    public function test_unpaid_delete_does_not_change_balance(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user, 1000);
        $category = $this->createIncomeCategory($workspace, $user);
        $income = Transaction::factory()->income()->unpaid()->create([
            'workspace_id' => $workspace->id, 'account_id' => $account->id, 'category_id' => $category->id, 'created_by' => $user->id,
            'value' => 500,
        ]);

        $this->actingAs($user)
            ->delete(route('incomes.destroy', [$workspace, $income]));

        $account->refresh();
        $this->assertEquals(1000, (float) $account->current_balance);
        $this->assertSoftDeleted('transactions', ['id' => $income->id]);
    }

    public function test_paid_delete_reverts_balance(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user, 1000);
        $category = $this->createIncomeCategory($workspace, $user);
        $income = Transaction::factory()->income()->paid()->create([
            'workspace_id' => $workspace->id, 'account_id' => $account->id, 'category_id' => $category->id, 'created_by' => $user->id,
            'value' => 300,
        ]);
        app(AccountService::class)->recalculateBalance($account);
        $account->refresh();
        $this->assertEquals(1300, (float) $account->current_balance);

        $this->actingAs($user)
            ->delete(route('incomes.destroy', [$workspace, $income]));

        $account->refresh();
        $this->assertEquals(1000, (float) $account->current_balance);
        $this->assertSoftDeleted('transactions', ['id' => $income->id]);
    }

    public function test_viewer_cannot_update_or_delete(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $viewer = $this->attachMember($workspace, 'viewer');
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);
        $income = Transaction::factory()->income()->create([
            'workspace_id' => $workspace->id, 'account_id' => $account->id, 'category_id' => $category->id, 'created_by' => $user->id,
        ]);

        $this->actingAs($viewer)
            ->put(route('incomes.update', [$workspace, $income]), [
                'description' => 'X', 'value' => 1, 'date' => '2026-05-01',
                'account_id' => $account->uuid, 'category_id' => $category->uuid,
            ])->assertForbidden();

        $this->actingAs($viewer)
            ->delete(route('incomes.destroy', [$workspace, $income]))
            ->assertForbidden();
    }
}
