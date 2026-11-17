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

class OptionalAccountTransactionTest extends TestCase
{
    private User $user;

    private Workspace $workspace;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->members()->attach($this->user, ['role' => WorkspaceRole::Admin->value]);

        $this->category = Category::factory()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
            'type' => 'expense',
        ]);
    }

    private function createAccount(float $initialBalance = 1000): Account
    {
        return Account::factory()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
            'initial_balance' => $initialBalance,
            'current_balance' => $initialBalance,
        ]);
    }

    public function test_user_can_create_expense_without_account_or_card(): void
    {
        $account = $this->createAccount();

        $response = $this->actingAs($this->user)
            ->post(route('transactions.store', $this->workspace), [
                'description' => 'Sem conta',
                'value' => 100,
                'date' => '2026-07-10',
                'category_id' => $this->category->uuid,
            ]);

        $response->assertRedirect(route('transactions.index', $this->workspace));

        $this->assertDatabaseHas('transactions', [
            'workspace_id' => $this->workspace->id,
            'account_id' => null,
            'credit_card_id' => null,
            'description' => 'Sem conta',
            'paid_at' => null,
        ]);

        $this->assertEquals(1000, (float) $account->fresh()->current_balance);
    }

    public function test_creating_paid_expense_with_account_sets_paid_at_and_updates_balance(): void
    {
        $account = $this->createAccount();

        $response = $this->actingAs($this->user)
            ->post(route('transactions.store', $this->workspace), [
                'description' => 'Já paga',
                'value' => 250,
                'date' => '2026-07-10',
                'account_id' => $account->uuid,
                'category_id' => $this->category->uuid,
                'is_paid' => true,
            ]);

        $response->assertRedirect(route('transactions.index', $this->workspace));

        $transaction = Transaction::where('description', 'Já paga')->first();

        $this->assertNotNull($transaction);
        $this->assertEquals($account->id, $transaction->account_id);
        $this->assertNotNull($transaction->paid_at);

        $this->assertEquals(750, (float) $account->fresh()->current_balance);
    }

    public function test_creating_paid_expense_without_account_sets_paid_at_and_leaves_balances_untouched(): void
    {
        $accountA = $this->createAccount(1000);
        $accountB = $this->createAccount(500);

        $response = $this->actingAs($this->user)
            ->post(route('transactions.store', $this->workspace), [
                'description' => 'Sem conta paga',
                'value' => 300,
                'date' => '2026-07-10',
                'category_id' => $this->category->uuid,
                'is_paid' => true,
            ]);

        $response->assertRedirect(route('transactions.index', $this->workspace));

        $this->assertDatabaseHas('transactions', [
            'workspace_id' => $this->workspace->id,
            'account_id' => null,
            'description' => 'Sem conta paga',
        ]);

        $transaction = Transaction::where('description', 'Sem conta paga')->first();
        $this->assertNotNull($transaction->paid_at);

        $this->assertEquals(1000, (float) $accountA->fresh()->current_balance);
        $this->assertEquals(500, (float) $accountB->fresh()->current_balance);
    }

    public function test_unpaid_expense_is_not_paid_when_is_paid_is_false(): void
    {
        $this->actingAs($this->user)
            ->post(route('transactions.store', $this->workspace), [
                'description' => 'Não paga',
                'value' => 50,
                'date' => '2026-07-10',
                'category_id' => $this->category->uuid,
                'is_paid' => false,
            ]);

        $this->assertDatabaseHas('transactions', [
            'description' => 'Não paga',
            'paid_at' => null,
        ]);
    }

    public function test_attaching_account_to_paid_null_account_expense_recalculates_target_account(): void
    {
        $account = $this->createAccount(1000);

        $transaction = Transaction::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => null,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'type' => 'expense',
            'value' => 200,
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('transactions.update', [$this->workspace, $transaction]), [
                'account_id' => $account->uuid,
            ]);

        $response->assertRedirect(route('transactions.index', $this->workspace));

        $this->assertEquals($account->id, $transaction->fresh()->account_id);
        $this->assertEquals(800, (float) $account->fresh()->current_balance);
    }

    public function test_detaching_account_from_paid_expense_recalculates_old_account_without_errors(): void
    {
        $account = $this->createAccount(1000);

        $transaction = Transaction::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $account->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'type' => 'expense',
            'value' => 200,
            'paid_at' => now(),
        ]);

        app(AccountService::class)->recalculateBalance($account);
        $this->assertEquals(800, (float) $account->fresh()->current_balance);

        $response = $this->actingAs($this->user)
            ->put(route('transactions.update', [$this->workspace, $transaction]), [
                'account_id' => null,
            ]);

        $response->assertRedirect(route('transactions.index', $this->workspace));

        $this->assertNull($transaction->fresh()->account_id);
        $this->assertEquals(1000, (float) $account->fresh()->current_balance);
    }

    public function test_pay_on_null_account_expense_toggles_paid_at_without_balance_side_effects(): void
    {
        $accountA = $this->createAccount(1000);
        $accountB = $this->createAccount(500);

        $transaction = Transaction::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => null,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'type' => 'expense',
            'value' => 100,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('transactions.pay', [$this->workspace, $transaction]));

        $response->assertRedirect();

        $this->assertNotNull($transaction->fresh()->paid_at);
        $this->assertEquals(1000, (float) $accountA->fresh()->current_balance);
        $this->assertEquals(500, (float) $accountB->fresh()->current_balance);

        $response = $this->actingAs($this->user)
            ->post(route('transactions.unpay', [$this->workspace, $transaction]));

        $response->assertRedirect();

        $this->assertNull($transaction->fresh()->paid_at);
        $this->assertEquals(1000, (float) $accountA->fresh()->current_balance);
        $this->assertEquals(500, (float) $accountB->fresh()->current_balance);
    }

    public function test_delete_null_account_transactions_succeeds(): void
    {
        $unpaid = Transaction::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => null,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'type' => 'expense',
            'value' => 100,
        ]);

        $paid = Transaction::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => null,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'type' => 'expense',
            'value' => 100,
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('transactions.destroy', [$this->workspace, $unpaid]));

        $response->assertRedirect();

        $response = $this->actingAs($this->user)
            ->delete(route('transactions.destroy', [$this->workspace, $paid]));

        $response->assertRedirect();

        $this->assertSoftDeleted('transactions', ['id' => $unpaid->id]);
        $this->assertSoftDeleted('transactions', ['id' => $paid->id]);
    }

    public function test_null_account_transaction_stores_correct_workspace_id(): void
    {
        $otherWorkspace = Workspace::factory()->create();

        $this->actingAs($this->user)
            ->post(route('transactions.store', $this->workspace), [
                'description' => 'Isolamento',
                'value' => 100,
                'date' => '2026-07-10',
                'category_id' => $this->category->uuid,
            ]);

        $transaction = Transaction::where('description', 'Isolamento')->first();

        $this->assertNotNull($transaction);
        $this->assertEquals($this->workspace->id, $transaction->workspace_id);
        $this->assertNotEquals($otherWorkspace->id, $transaction->workspace_id);
    }

    public function test_recurring_expense_still_requires_account_or_card(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('transactions.store', $this->workspace), [
                'description' => 'Recorrente sem conta',
                'value' => 100,
                'date' => now()->format('Y-m-d'),
                'category_id' => $this->category->uuid,
                'is_recurring' => true,
                'frequency' => 'monthly',
                'frequency_day' => 10,
            ]);

        $response->assertSessionHasErrors(['account_id']);
    }

    public function test_installment_expense_still_requires_account_or_card(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('transactions.store', $this->workspace), [
                'description' => 'Parcelado sem conta',
                'value' => 100,
                'date' => now()->format('Y-m-d'),
                'category_id' => $this->category->uuid,
                'installments' => 3,
                'total_value' => 300,
            ]);

        $response->assertSessionHasErrors(['account_id']);
    }
}
