<?php

declare(strict_types=1);

namespace Tests\Feature\Incomes;

use App\Enums\WorkspaceRole;
use App\Models\Transaction;
use App\Models\User;

class IncomeCreationTest extends IncomeTestCase
{
    public function test_user_can_create_income_in_their_workspace(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember(WorkspaceRole::Admin->value);
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);

        $response = $this->actingAs($user)
            ->post(route('incomes.store', $workspace), [
                'description' => 'Salário Maio',
                'value' => 5000,
                'date' => '2026-05-05',
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
            ]);

        $response->assertRedirect(route('incomes.index', $workspace));
        $this->assertDatabaseHas('transactions', [
            'description' => 'Salário Maio',
            'workspace_id' => $workspace->id,
            'type' => 'income',
            'value' => 5000,
        ]);
    }

    public function test_new_income_starts_with_null_paid_at(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('incomes.store', $workspace), [
                'description' => 'Freelance',
                'value' => 1500,
                'date' => '2026-05-10',
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
            ]);

        $income = Transaction::where('description', 'Freelance')->first();
        $this->assertNull($income->paid_at);
    }

    public function test_income_accepts_category_type_both(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createBothCategory($workspace, $user);

        $response = $this->actingAs($user)
            ->post(route('incomes.store', $workspace), [
                'description' => 'Venda',
                'value' => 200,
                'date' => '2026-05-10',
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('transactions', ['description' => 'Venda', 'type' => 'income']);
    }

    public function test_income_with_expense_category_is_rejected(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $response = $this->actingAs($user)
            ->post(route('incomes.store', $workspace), [
                'description' => 'Test',
                'value' => 100,
                'date' => '2026-05-10',
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
            ]);

        $response->assertSessionHasErrors([
            'category_id' => 'Categoria de despesa não pode ser usada em receita.',
        ]);
    }

    public function test_viewer_cannot_create_income(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $viewer = $this->attachMember($workspace, WorkspaceRole::Viewer->value);
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);

        $response = $this->actingAs($viewer)
            ->post(route('incomes.store', $workspace), [
                'description' => 'Test',
                'value' => 100,
                'date' => '2026-05-10',
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
            ]);

        $response->assertForbidden();
    }

    public function test_cross_workspace_income_uuid_returns_404_in_scoped_route(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);

        [, $otherWorkspace] = $this->createWorkspaceWithMember();
        $otherUser = User::factory()->create();
        $otherAccount = $this->createAccount($otherWorkspace, $otherUser);
        $otherCategory = $this->createIncomeCategory($otherWorkspace, $otherUser);

        $response = $this->actingAs($user)
            ->post(route('incomes.store', $workspace), [
                'description' => 'Test',
                'value' => 100,
                'date' => '2026-05-10',
                'account_id' => $otherAccount->uuid,
                'category_id' => $otherCategory->uuid,
            ]);

        $response->assertSessionHasErrors(['account_id', 'category_id']);
    }

    public function test_index_lists_incomes_ordered_by_date_desc(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);

        Transaction::factory()->income()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'description' => 'Old',
            'date' => '2026-01-10',
            'value' => 100,
        ]);

        Transaction::factory()->income()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'description' => 'New',
            'date' => '2026-05-10',
            'value' => 200,
        ]);

        $response = $this->actingAs($user)
            ->get(route('incomes.index', $workspace));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Incomes/Index', false)
            ->has('incomes.data', 2)
            ->where('incomes.data.0.description', 'New'));
    }

    public function test_index_excludes_transfer_legs(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);

        Transaction::factory()->income()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'description' => 'Real Income',
            'date' => '2026-05-10',
            'value' => 500,
        ]);

        Transaction::factory()->income()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'description' => 'Transfer Leg',
            'date' => '2026-05-11',
            'value' => 300,
            'transfer_group_id' => 'abc-123',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('incomes.index', $workspace));

        $response->assertInertia(fn ($page) => $page
            ->has('incomes.data', 1)
            ->where('incomes.data.0.description', 'Real Income'));
    }

    public function test_index_filters_by_status_paid(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);

        Transaction::factory()->income()->paid()->create([
            'workspace_id' => $workspace->id, 'account_id' => $account->id, 'category_id' => $category->id, 'created_by' => $user->id,
            'description' => 'Received', 'date' => '2026-05-01', 'value' => 100,
        ]);

        Transaction::factory()->income()->unpaid()->create([
            'workspace_id' => $workspace->id, 'account_id' => $account->id, 'category_id' => $category->id, 'created_by' => $user->id,
            'description' => 'Pending', 'date' => '2026-05-02', 'value' => 200,
        ]);

        $response = $this->actingAs($user)
            ->get(route('incomes.index', $workspace).'?status=paid');

        $response->assertInertia(fn ($page) => $page
            ->has('incomes.data', 1)
            ->where('incomes.data.0.description', 'Received'));
    }

    public function test_index_search_filters_by_description(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);

        foreach (['Salário', 'Freelance', 'Venda'] as $desc) {
            Transaction::factory()->income()->create([
                'workspace_id' => $workspace->id, 'account_id' => $account->id, 'category_id' => $category->id, 'created_by' => $user->id,
                'description' => $desc, 'date' => '2026-05-01', 'value' => 100,
            ]);
        }

        $response = $this->actingAs($user)
            ->get(route('incomes.index', $workspace).'?search=Fre');

        $response->assertInertia(fn ($page) => $page
            ->has('incomes.data', 1)
            ->where('incomes.data.0.description', 'Freelance'));
    }

    public function test_index_paginates_25_per_page(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);

        for ($i = 0; $i < 30; $i++) {
            Transaction::factory()->income()->create([
                'workspace_id' => $workspace->id, 'account_id' => $account->id, 'category_id' => $category->id, 'created_by' => $user->id,
                'description' => "Income {$i}", 'date' => '2026-05-01', 'value' => 100,
            ]);
        }

        $response = $this->actingAs($user)
            ->get(route('incomes.index', $workspace));

        $response->assertInertia(fn ($page) => $page->has('incomes.data', 25));
    }

    public function test_create_page_loads_form_with_income_and_both_categories_only(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $this->createIncomeCategory($workspace, $user);
        $this->createExpenseCategory($workspace, $user);
        $this->createBothCategory($workspace, $user);
        $this->createAccount($workspace, $user);

        $response = $this->actingAs($user)
            ->get(route('incomes.create', $workspace));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Incomes/Create', false)
            ->has('categories', 2));
    }
}
