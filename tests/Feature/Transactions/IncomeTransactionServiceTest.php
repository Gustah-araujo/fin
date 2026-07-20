<?php

declare(strict_types=1);

namespace Tests\Feature\Transactions;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AccountService;
use App\Services\TransactionService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IncomeTransactionServiceTest extends TestCase
{
    private TransactionService $transactionService;
    private AccountService $accountService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transactionService = app(TransactionService::class);
        $this->accountService = app(AccountService::class);
    }

    public function test_service_can_create_income_transaction(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => 'admin']);

        $account = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $category = Category::factory()->income()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $transaction = $this->transactionService->create($workspace, $user, [
            'description' => 'Salário',
            'value' => 5000,
            'date' => '2026-07-15',
            'account_id' => $account->uuid,
            'category_id' => $category->uuid,
            'type' => 'income',
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'type' => TransactionType::Income->value,
            'description' => 'Salário',
            'value' => 5000,
            'paid_at' => null,
            'credit_card_id' => null,
            'installment_number' => null,
            'installments_total' => null,
        ]);
    }

    public function test_service_defaults_to_expense_when_no_type_provided(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => 'admin']);

        $account = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $category = Category::factory()->expense()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $transaction = $this->transactionService->create($workspace, $user, [
            'description' => 'Default expense',
            'value' => 100,
            'date' => '2026-07-15',
            'account_id' => $account->uuid,
            'category_id' => $category->uuid,
        ]);

        $this->assertEquals(TransactionType::Expense->value, $transaction->type->value);
    }

    public function test_service_rejects_invalid_transaction_type(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => 'admin']);

        $account = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $category = Category::factory()->expense()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $this->expectException(ValidationException::class);

        $this->transactionService->create($workspace, $user, [
            'description' => 'Invalid type',
            'value' => 100,
            'date' => '2026-07-15',
            'account_id' => $account->uuid,
            'category_id' => $category->uuid,
            'type' => 'invalid_type',
        ]);
    }

    public function test_pay_rejects_archived_account_for_income(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => 'admin']);

        $account = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'initial_balance' => 1000,
            'current_balance' => 1000,
        ]);

        $category = Category::factory()->income()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $transaction = $this->transactionService->create($workspace, $user, [
            'description' => 'Salário',
            'value' => 5000,
            'date' => '2026-07-15',
            'account_id' => $account->uuid,
            'category_id' => $category->uuid,
            'type' => 'income',
        ]);

        $account->delete();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('arquivada');

        $this->transactionService->pay($transaction);
    }

    public function test_pay_rejects_archived_account_for_expense(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => 'admin']);

        $account = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'initial_balance' => 1000,
            'current_balance' => 1000,
        ]);

        $category = Category::factory()->expense()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $transaction = Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'type' => 'expense',
            'value' => 200,
        ]);

        $account->delete();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('arquivada');

        $this->transactionService->pay($transaction);
    }

    public function test_update_paid_income_value_recalculates_balance(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => 'admin']);

        $account = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'initial_balance' => 1000,
            'current_balance' => 1000,
        ]);

        $category = Category::factory()->income()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $transaction = Transaction::factory()->income()->paid()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'value' => 500,
        ]);

        $this->accountService->recalculateBalance($account);

        $this->assertEquals(1500, (float) $account->fresh()->current_balance);

        $this->transactionService->update($transaction, [
            'value' => 800,
        ]);

        $this->assertEquals(1800, (float) $account->fresh()->current_balance);
    }

    public function test_update_paid_income_account_recalculates_both_accounts(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => 'admin']);

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

        $category = Category::factory()->income()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $transaction = Transaction::factory()->income()->paid()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $accountA->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'value' => 500,
        ]);

        $this->accountService->recalculateBalance($accountA);

        $this->assertEquals(1500, (float) $accountA->fresh()->current_balance);

        $this->transactionService->update($transaction, [
            'account_id' => $accountB->uuid,
        ]);

        $this->assertEquals(1000, (float) $accountA->fresh()->current_balance);
        $this->assertEquals(2500, (float) $accountB->fresh()->current_balance);
    }
}
