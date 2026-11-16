<?php

declare(strict_types=1);

namespace Tests\Feature\Transactions;

use App\Enums\WorkspaceRole;
use App\Models\Account;
use App\Models\Category;
use App\Models\ExpenseGroup;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class TransactionExpenseGroupTest extends TestCase
{
    public function test_effective_expense_group_falls_back_to_category_group(): void
    {
        [$user, $workspace, $account] = $this->setupWorkspace();

        $group = ExpenseGroup::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Essenciais',
        ]);
        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
            'expense_group_id' => $group->id,
        ]);

        $transaction = Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'date' => '2026-01-15',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('transactions.datatable', $workspace).'?month=2026-01');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $transaction->uuid)
            ->assertJsonPath('data.0.effective_expense_group.uuid', $group->uuid)
            ->assertJsonPath('data.0.effective_expense_group.name', 'Essenciais')
            ->assertJsonPath('data.0.expense_group', null);
    }

    public function test_effective_expense_group_uses_transaction_override(): void
    {
        [$user, $workspace, $account] = $this->setupWorkspace();

        $categoryGroup = ExpenseGroup::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Da Categoria',
        ]);
        $overrideGroup = ExpenseGroup::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Override',
        ]);
        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
            'expense_group_id' => $categoryGroup->id,
        ]);

        $transaction = Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'expense_group_id' => $overrideGroup->id,
            'created_by' => $user->id,
            'date' => '2026-01-15',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('transactions.datatable', $workspace).'?month=2026-01');

        $response->assertOk()
            ->assertJsonPath('data.0.uuid', $transaction->uuid)
            ->assertJsonPath('data.0.effective_expense_group.uuid', $overrideGroup->uuid)
            ->assertJsonPath('data.0.effective_expense_group.name', 'Override')
            ->assertJsonPath('data.0.expense_group.uuid', $overrideGroup->uuid);
    }

    public function test_effective_expense_group_is_null_when_no_group_anywhere(): void
    {
        [$user, $workspace, $account, $category] = $this->setupWorkspace();

        Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'date' => '2026-01-15',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('transactions.datatable', $workspace).'?month=2026-01');

        $response->assertOk()
            ->assertJsonPath('data.0.effective_expense_group', null)
            ->assertJsonPath('data.0.expense_group', null);
    }

    public function test_filter_by_group_matches_inherited_and_overridden_rows(): void
    {
        [$user, $workspace, $account] = $this->setupWorkspace();

        $groupA = ExpenseGroup::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Grupo A',
        ]);
        $groupB = ExpenseGroup::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Grupo B',
        ]);

        $categoryA = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
            'expense_group_id' => $groupA->id,
        ]);
        $categoryB = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
            'expense_group_id' => $groupB->id,
        ]);
        $categoryNone = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
        ]);

        $inherited = Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $categoryA->id,
            'created_by' => $user->id,
            'date' => '2026-01-10',
        ]);
        $overridden = Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $categoryB->id,
            'expense_group_id' => $groupA->id,
            'created_by' => $user->id,
            'date' => '2026-01-11',
        ]);
        Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $categoryB->id,
            'created_by' => $user->id,
            'date' => '2026-01-12',
        ]);
        Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $categoryNone->id,
            'created_by' => $user->id,
            'date' => '2026-01-13',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('transactions.datatable', $workspace).'?month=2026-01&expense_group='.$groupA->uuid);

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);

        $uuids = collect($response->json('data'))->pluck('uuid')->all();
        $this->assertEqualsCanonicalizing([$inherited->uuid, $overridden->uuid], $uuids);
    }

    public function test_filter_none_returns_only_group_less_transactions(): void
    {
        [$user, $workspace, $account] = $this->setupWorkspace();

        $group = ExpenseGroup::factory()->create(['workspace_id' => $workspace->id]);

        $categoryWithGroup = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
            'expense_group_id' => $group->id,
        ]);
        $categoryWithoutGroup = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
        ]);

        Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $categoryWithGroup->id,
            'created_by' => $user->id,
            'date' => '2026-01-10',
        ]);
        Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $categoryWithGroup->id,
            'expense_group_id' => $group->id,
            'created_by' => $user->id,
            'date' => '2026-01-11',
        ]);
        $groupLess = Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $categoryWithoutGroup->id,
            'created_by' => $user->id,
            'date' => '2026-01-12',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('transactions.datatable', $workspace).'?month=2026-01&expense_group=none');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $groupLess->uuid);
    }

    public function test_meta_totals_reflect_only_the_filtered_set(): void
    {
        [$user, $workspace, $account] = $this->setupWorkspace();

        $group = ExpenseGroup::factory()->create(['workspace_id' => $workspace->id]);

        $categoryWithGroup = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
            'expense_group_id' => $group->id,
        ]);
        $categoryWithoutGroup = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
        ]);

        Transaction::factory()->paid()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $categoryWithGroup->id,
            'created_by' => $user->id,
            'value' => 100,
            'date' => '2026-01-10',
        ]);
        Transaction::factory()->unpaid()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $categoryWithoutGroup->id,
            'created_by' => $user->id,
            'value' => 300,
            'date' => '2026-01-11',
        ]);

        $unfiltered = $this->actingAs($user)
            ->getJson(route('transactions.datatable', $workspace).'?month=2026-01');

        $unfiltered->assertOk()
            ->assertJsonPath('meta.totals.total', fn ($value) => (float) $value === 400.0)
            ->assertJsonPath('meta.totals.paid', fn ($value) => (float) $value === 100.0)
            ->assertJsonPath('meta.totals.pending', fn ($value) => (float) $value === 300.0);

        $filtered = $this->actingAs($user)
            ->getJson(route('transactions.datatable', $workspace).'?month=2026-01&expense_group='.$group->uuid);

        $filtered->assertOk()
            ->assertJsonPath('meta.totals.total', fn ($value) => (float) $value === 100.0)
            ->assertJsonPath('meta.totals.paid', fn ($value) => (float) $value === 100.0)
            ->assertJsonPath('meta.totals.pending', fn ($value) => (float) $value === 0.0);
    }

    public function test_store_persists_expense_group_id(): void
    {
        [$user, $workspace, $account, $category] = $this->setupWorkspace();

        $group = ExpenseGroup::factory()->create(['workspace_id' => $workspace->id]);

        $response = $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Mercado',
                'value' => 100,
                'date' => '2026-01-15',
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
                'expense_group_id' => $group->uuid,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'description' => 'Mercado',
            'workspace_id' => $workspace->id,
            'expense_group_id' => $group->id,
        ]);
    }

    public function test_store_rejects_group_from_another_workspace(): void
    {
        [$user, $workspace, $account, $category] = $this->setupWorkspace();

        $otherWorkspace = Workspace::factory()->create();
        $otherGroup = ExpenseGroup::factory()->create([
            'workspace_id' => $otherWorkspace->id,
        ]);

        $response = $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Mercado',
                'value' => 100,
                'date' => '2026-01-15',
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
                'expense_group_id' => $otherGroup->uuid,
            ]);

        $response->assertSessionHasErrors('expense_group_id');

        $this->assertDatabaseMissing('transactions', [
            'description' => 'Mercado',
            'workspace_id' => $workspace->id,
        ]);
    }

    public function test_update_changes_expense_group_override(): void
    {
        [$user, $workspace, $account, $category] = $this->setupWorkspace();

        $groupA = ExpenseGroup::factory()->create(['workspace_id' => $workspace->id]);
        $groupB = ExpenseGroup::factory()->create(['workspace_id' => $workspace->id]);

        $transaction = Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'expense_group_id' => $groupA->id,
            'created_by' => $user->id,
            'date' => '2026-01-15',
        ]);

        $response = $this->actingAs($user)
            ->put(route('transactions.update', [$workspace, $transaction]), [
                'expense_group_id' => $groupB->uuid,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'uuid' => $transaction->uuid,
            'expense_group_id' => $groupB->id,
        ]);
    }

    public function test_update_without_expense_group_field_preserves_existing_value(): void
    {
        [$user, $workspace, $account, $category] = $this->setupWorkspace();

        $groupA = ExpenseGroup::factory()->create(['workspace_id' => $workspace->id]);
        $groupB = ExpenseGroup::factory()->create(['workspace_id' => $workspace->id]);

        $transaction = Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'expense_group_id' => $groupA->id,
            'created_by' => $user->id,
            'date' => '2026-01-15',
        ]);

        $response = $this->actingAs($user)
            ->put(route('transactions.update', [$workspace, $transaction]), [
                'description' => 'Descrição alterada',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'uuid' => $transaction->uuid,
            'description' => 'Descrição alterada',
            'expense_group_id' => $groupA->id,
        ]);

        $this->assertDatabaseMissing('transactions', [
            'uuid' => $transaction->uuid,
            'expense_group_id' => $groupB->id,
        ]);
    }

    /**
     * @return array{0: User, 1: Workspace, 2: Account, 3: Category}
     */
    private function setupWorkspace(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);
        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
        ]);

        return [$user, $workspace, $account, $category];
    }
}
