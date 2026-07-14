<?php

declare(strict_types=1);

namespace Tests\Feature\Transactions;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AccountService;
use Tests\TestCase;

class AccountBalanceRecalculationTest extends TestCase
{
    private AccountService $accountService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountService = app(AccountService::class);
    }

    private function createTestSetup(float $initialBalance = 1000): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $account = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'initial_balance' => $initialBalance,
            'current_balance' => $initialBalance,
        ]);
        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
        ]);

        return [$user, $workspace, $account, $category];
    }

    private function createTransaction(int $workspaceId, int $accountId, int $categoryId, int $userId, float $value, string $type = 'expense', bool $paid = true): Transaction
    {
        return Transaction::factory()->create([
            'workspace_id' => $workspaceId,
            'account_id' => $accountId,
            'category_id' => $categoryId,
            'created_by' => $userId,
            'value' => $value,
            'type' => $type,
            'paid_at' => $paid ? now() : null,
        ]);
    }

    public function test_recalculate_balance_sums_paid_expenses(): void
    {
        [, $workspace, $account, $category] = $this->createTestSetup(1000);

        $this->createTransaction($workspace->id, $account->id, $category->id, $account->created_by, 200);

        $this->accountService->recalculateBalance($account);

        $this->assertEquals(800, (float) $account->fresh()->current_balance);
    }

    public function test_recalculate_balance_still_works_for_accounts_without_transactions(): void
    {
        [, , $account, ] = $this->createTestSetup(500);

        $this->accountService->recalculateBalance($account);

        $this->assertEquals(500, (float) $account->fresh()->current_balance);
    }

    public function test_multiple_transactions_aggregate_correctly(): void
    {
        [, $workspace, $account, $category] = $this->createTestSetup(1000);

        $this->createTransaction($workspace->id, $account->id, $category->id, $account->created_by, 100);
        $this->createTransaction($workspace->id, $account->id, $category->id, $account->created_by, 200);
        $this->createTransaction($workspace->id, $account->id, $category->id, $account->created_by, 50);

        $this->accountService->recalculateBalance($account);

        $this->assertEquals(650, (float) $account->fresh()->current_balance);
    }

    public function test_recalculate_on_different_accounts_is_independent(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
        ]);

        $accountA = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'initial_balance' => 1000,
            'current_balance' => 1000,
        ]);
        $accountB = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'initial_balance' => 2000,
            'current_balance' => 2000,
        ]);

        $this->createTransaction($workspace->id, $accountA->id, $category->id, $user->id, 300);

        $this->accountService->recalculateBalance($accountA);

        $this->assertEquals(700, (float) $accountA->fresh()->current_balance);
        $this->assertEquals(2000, (float) $accountB->fresh()->current_balance);
    }

    public function test_balance_matches_formula_with_income_and_expense(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $account = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'initial_balance' => 1000,
            'current_balance' => 1000,
        ]);
        $expenseCategory = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
        ]);
        $incomeCategory = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'income',
        ]);

        $this->createTransaction($workspace->id, $account->id, $incomeCategory->id, $user->id, 500, 'income');
        $this->createTransaction($workspace->id, $account->id, $expenseCategory->id, $user->id, 300, 'expense');

        $this->accountService->recalculateBalance($account);

        $this->assertEquals(1200, (float) $account->fresh()->current_balance);
    }

    public function test_unpaid_transactions_do_not_affect_balance(): void
    {
        [, $workspace, $account, $category] = $this->createTestSetup(1000);

        $this->createTransaction($workspace->id, $account->id, $category->id, $account->created_by, 500, 'expense', false);

        $this->accountService->recalculateBalance($account);

        $this->assertEquals(1000, (float) $account->fresh()->current_balance);
    }

    public function test_balance_with_multiple_accounts_and_transactions(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();

        $accountA = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'initial_balance' => 1000,
            'current_balance' => 1000,
        ]);
        $accountB = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'initial_balance' => 500,
            'current_balance' => 500,
        ]);

        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
        ]);

        $this->createTransaction($workspace->id, $accountA->id, $category->id, $user->id, 300, 'expense', true);
        $this->createTransaction($workspace->id, $accountA->id, $category->id, $user->id, 200, 'expense', false);
        $this->createTransaction($workspace->id, $accountB->id, $category->id, $user->id, 100, 'expense', true);

        $this->accountService->recalculateBalance($accountA);
        $this->accountService->recalculateBalance($accountB);

        $this->assertEquals(700, (float) $accountA->fresh()->current_balance);
        $this->assertEquals(400, (float) $accountB->fresh()->current_balance);
    }

    public function test_delete_account_does_not_affect_transaction_references(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $account = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'initial_balance' => 1000,
            'current_balance' => 1000,
        ]);
        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
        ]);

        $transaction = $this->createTransaction($workspace->id, $account->id, $category->id, $user->id, 200);

        $account->delete();

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'account_id' => $account->id,
        ]);
    }
}
