<?php

declare(strict_types=1);

namespace Tests\Feature\Transactions;

use App\Enums\WorkspaceRole;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AccountService;
use Tests\TestCase;

class TransactionPaymentTest extends TestCase
{
    private AccountService $accountService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountService = app(AccountService::class);
    }

    private function createTestSetup(
        float $initialBalance = 1000,
        WorkspaceRole $role = WorkspaceRole::Admin,
    ): array {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => $role->value]);
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

    private function createTransaction(
        int $workspaceId,
        int $accountId,
        int $categoryId,
        int $userId,
        float $value,
        bool $paid = false,
    ): Transaction {
        return Transaction::factory()->create([
            'workspace_id' => $workspaceId,
            'account_id' => $accountId,
            'category_id' => $categoryId,
            'created_by' => $userId,
            'value' => $value,
            'paid_at' => $paid ? now() : null,
        ]);
    }

    public function test_user_can_pay_transaction(): void
    {
        [$user, $workspace, $account, $category] = $this->createTestSetup(1000);

        $transaction = $this->createTransaction(
            $workspace->id, $account->id, $category->id, $user->id, 200
        );

        $response = $this->actingAs($user)
            ->post(route('transactions.pay', [
                'workspace' => $workspace->uuid,
                'transaction' => $transaction->uuid,
            ]));

        $response->assertRedirect();

        $this->assertNotNull($transaction->fresh()->paid_at);
        $this->assertEquals(800, (float) $account->fresh()->current_balance);
    }

    public function test_user_can_unpay_transaction(): void
    {
        [$user, $workspace, $account, $category] = $this->createTestSetup(1000);

        $transaction = $this->createTransaction(
            $workspace->id, $account->id, $category->id, $user->id, 200, true
        );

        $this->accountService->recalculateBalance($account);

        $this->assertEquals(800, (float) $account->fresh()->current_balance);

        $response = $this->actingAs($user)
            ->post(route('transactions.unpay', [
                'workspace' => $workspace->uuid,
                'transaction' => $transaction->uuid,
            ]));

        $response->assertRedirect();

        $this->assertNull($transaction->fresh()->paid_at);
        $this->assertEquals(1000, (float) $account->fresh()->current_balance);
    }

    public function test_paying_already_paid_transaction_is_idempotent(): void
    {
        [$user, $workspace, $account, $category] = $this->createTestSetup(1000);

        $transaction = $this->createTransaction(
            $workspace->id, $account->id, $category->id, $user->id, 200, true
        );

        $this->accountService->recalculateBalance($account);

        $this->assertEquals(800, (float) $account->fresh()->current_balance);

        $response = $this->actingAs($user)
            ->post(route('transactions.pay', [
                'workspace' => $workspace->uuid,
                'transaction' => $transaction->uuid,
            ]));

        $response->assertRedirect();

        $this->assertEquals(800, (float) $account->fresh()->current_balance);
    }

    public function test_unpaying_unpaid_transaction_is_idempotent(): void
    {
        [$user, $workspace, $account, $category] = $this->createTestSetup(1000);

        $transaction = $this->createTransaction(
            $workspace->id, $account->id, $category->id, $user->id, 200
        );

        $this->assertEquals(1000, (float) $account->fresh()->current_balance);

        $response = $this->actingAs($user)
            ->post(route('transactions.unpay', [
                'workspace' => $workspace->uuid,
                'transaction' => $transaction->uuid,
            ]));

        $response->assertRedirect();

        $this->assertNull($transaction->fresh()->paid_at);
        $this->assertEquals(1000, (float) $account->fresh()->current_balance);
    }

    public function test_payment_changes_only_target_account(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

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

        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
        ]);

        $transaction = $this->createTransaction(
            $workspace->id, $accountA->id, $category->id, $user->id, 300
        );

        $this->actingAs($user)
            ->post(route('transactions.pay', [
                'workspace' => $workspace->uuid,
                'transaction' => $transaction->uuid,
            ]));

        $this->assertEquals(700, (float) $accountA->fresh()->current_balance);
        $this->assertEquals(2000, (float) $accountB->fresh()->current_balance);
    }

    public function test_pay_and_unpay_toggles_correctly(): void
    {
        [$user, $workspace, $account, $category] = $this->createTestSetup(1000);

        $transaction = $this->createTransaction(
            $workspace->id, $account->id, $category->id, $user->id, 200
        );

        $this->actingAs($user)
            ->post(route('transactions.pay', [
                'workspace' => $workspace->uuid,
                'transaction' => $transaction->uuid,
            ]));

        $this->assertNotNull($transaction->fresh()->paid_at);
        $this->assertEquals(800, (float) $account->fresh()->current_balance);

        $this->actingAs($user)
            ->post(route('transactions.unpay', [
                'workspace' => $workspace->uuid,
                'transaction' => $transaction->uuid,
            ]));

        $this->assertNull($transaction->fresh()->paid_at);
        $this->assertEquals(1000, (float) $account->fresh()->current_balance);
    }

    public function test_editor_can_pay_and_unpay(): void
    {
        [$user, $workspace, $account, $category] = $this->createTestSetup(
            initialBalance: 1000,
            role: WorkspaceRole::Editor,
        );

        $transaction = $this->createTransaction(
            $workspace->id, $account->id, $category->id, $user->id, 200
        );

        $payResponse = $this->actingAs($user)
            ->post(route('transactions.pay', [
                'workspace' => $workspace->uuid,
                'transaction' => $transaction->uuid,
            ]));

        $payResponse->assertRedirect();

        $this->assertNotNull($transaction->fresh()->paid_at);
        $this->assertEquals(800, (float) $account->fresh()->current_balance);

        $unpayResponse = $this->actingAs($user)
            ->post(route('transactions.unpay', [
                'workspace' => $workspace->uuid,
                'transaction' => $transaction->uuid,
            ]));

        $unpayResponse->assertRedirect();

        $this->assertNull($transaction->fresh()->paid_at);
        $this->assertEquals(1000, (float) $account->fresh()->current_balance);
    }

    public function test_viewer_cannot_pay_transaction(): void
    {
        [$user, $workspace, $account, $category] = $this->createTestSetup(
            initialBalance: 1000,
            role: WorkspaceRole::Viewer,
        );

        $transaction = $this->createTransaction(
            $workspace->id, $account->id, $category->id, $user->id, 200
        );

        $response = $this->actingAs($user)
            ->post(route('transactions.pay', [
                'workspace' => $workspace->uuid,
                'transaction' => $transaction->uuid,
            ]));

        $response->assertForbidden();
    }

    public function test_viewer_cannot_unpay_transaction(): void
    {
        [$user, $workspace, $account, $category] = $this->createTestSetup(
            initialBalance: 1000,
            role: WorkspaceRole::Viewer,
        );

        $transaction = $this->createTransaction(
            $workspace->id, $account->id, $category->id, $user->id, 200, true
        );

        $this->accountService->recalculateBalance($account);

        $response = $this->actingAs($user)
            ->post(route('transactions.unpay', [
                'workspace' => $workspace->uuid,
                'transaction' => $transaction->uuid,
            ]));

        $response->assertForbidden();
    }

    public function test_pay_unpay_work_across_different_transactions(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

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

        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
        ]);

        $transactionA = $this->createTransaction(
            $workspace->id, $accountA->id, $category->id, $user->id, 300
        );
        $transactionB = $this->createTransaction(
            $workspace->id, $accountB->id, $category->id, $user->id, 500
        );

        $this->actingAs($user)
            ->post(route('transactions.pay', [
                'workspace' => $workspace->uuid,
                'transaction' => $transactionA->uuid,
            ]));

        $this->actingAs($user)
            ->post(route('transactions.pay', [
                'workspace' => $workspace->uuid,
                'transaction' => $transactionB->uuid,
            ]));

        $this->assertEquals(700, (float) $accountA->fresh()->current_balance);
        $this->assertEquals(1500, (float) $accountB->fresh()->current_balance);
    }
}
