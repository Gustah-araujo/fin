<?php

declare(strict_types=1);

namespace Tests\Feature\Transactions;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountInstallmentTest extends TestCase
{
    use RefreshDatabase;

    private function createUserAndWorkspace(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => 'admin']);

        return [$user, $workspace];
    }

    private function createAccount(Workspace $workspace, User $user): Account
    {
        return Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);
    }

    private function createExpenseCategory(Workspace $workspace, User $user): Category
    {
        return Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => TransactionType::Expense->value,
        ]);
    }

    public function test_user_can_create_installment_expense_on_account(): void
    {
        [$user, $workspace] = $this->createUserAndWorkspace();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $response = $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Notebook Dell',
                'value' => 100.01,
                'date' => '2026-01-15',
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
                'installments' => 3,
                'total_value' => 300.03,
            ]);

        $response->assertRedirect(route('transactions.index', $workspace));

        $transactions = Transaction::where('description', 'like', 'Notebook Dell%')
            ->whereNull('deleted_at')
            ->orderBy('installment_number')
            ->get();

        $this->assertCount(3, $transactions);

        $groupId = $transactions->first()->installment_group_id;
        $this->assertNotNull($groupId);

        foreach ($transactions as $index => $transaction) {
            $this->assertEquals(3, $transaction->installments_total);
            $this->assertEquals($index + 1, $transaction->installment_number);
            $this->assertEquals($groupId, $transaction->installment_group_id);
            $this->assertEquals($workspace->id, $transaction->workspace_id);
            $this->assertEquals($account->id, $transaction->account_id);
            $this->assertNull($transaction->credit_card_id);
            $this->assertNull($transaction->credit_card_bill_id);
            $this->assertNull($transaction->paid_at);
        }

        // No remainder: 300.03 / 3 = 100.01 exactly on every installment
        $this->assertEquals(100.01, (float) $transactions[0]->value);
        $this->assertEquals(100.01, (float) $transactions[1]->value);
        $this->assertEquals(100.01, (float) $transactions[2]->value);
        $this->assertEquals(300.03, round((float) $transactions->sum('value'), 2));

        // Case with remainder: 100.00 / 3 -> 33.33, 33.33, 33.34 (remainder on last)
        $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Compra parcelada',
                'value' => 33.33,
                'date' => '2026-01-15',
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
                'installments' => 3,
                'total_value' => 100.00,
            ]);

        $remainderTransactions = Transaction::where('description', 'like', 'Compra parcelada%')
            ->whereNull('deleted_at')
            ->orderBy('installment_number')
            ->get();

        $this->assertCount(3, $remainderTransactions);
        $this->assertEquals(33.33, (float) $remainderTransactions[0]->value);
        $this->assertEquals(33.33, (float) $remainderTransactions[1]->value);
        $this->assertEquals(33.34, (float) $remainderTransactions[2]->value);
    }

    public function test_installment_descriptions_have_numbered_suffix(): void
    {
        [$user, $workspace] = $this->createUserAndWorkspace();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Notebook Dell',
                'value' => 100.01,
                'date' => '2026-01-15',
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
                'installments' => 3,
                'total_value' => 300.03,
            ]);

        $descriptions = Transaction::where('description', 'like', 'Notebook Dell%')
            ->whereNull('deleted_at')
            ->orderBy('installment_number')
            ->pluck('description')
            ->toArray();

        $this->assertSame([
            'Notebook Dell (1/3)',
            'Notebook Dell (2/3)',
            'Notebook Dell (3/3)',
        ], $descriptions);
    }

    public function test_installments_respect_day_of_month_with_clamping(): void
    {
        [$user, $workspace] = $this->createUserAndWorkspace();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Aluguel',
                'value' => 1000,
                'date' => '2026-01-31',
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
                'installments' => 3,
                'total_value' => 3000,
                'installment_day' => 31,
            ]);

        $dates = Transaction::where('description', 'like', 'Aluguel%')
            ->whereNull('deleted_at')
            ->orderBy('installment_number')
            ->pluck('date')
            ->map(fn ($date) => $date->toDateString())
            ->toArray();

        $this->assertSame([
            '2026-01-31',
            '2026-02-28',
            '2026-03-31',
        ], $dates);
    }

    public function test_installments_use_day_of_month_selector(): void
    {
        [$user, $workspace] = $this->createUserAndWorkspace();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Assinatura',
                'value' => 50,
                'date' => '2026-01-05',
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
                'installments' => 3,
                'total_value' => 150,
                'installment_day' => 10,
            ]);

        $dates = Transaction::where('description', 'like', 'Assinatura%')
            ->whereNull('deleted_at')
            ->orderBy('installment_number')
            ->pluck('date')
            ->map(fn ($date) => $date->toDateString())
            ->toArray();

        $this->assertSame([
            '2026-01-10',
            '2026-02-10',
            '2026-03-10',
        ], $dates);
    }

    public function test_installments_are_scoped_to_workspace(): void
    {
        [$user, $workspace] = $this->createUserAndWorkspace();
        $category = $this->createExpenseCategory($workspace, $user);

        $otherWorkspace = Workspace::factory()->create();
        $externalAccount = $this->createAccount($otherWorkspace, $user);

        $response = $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Conta alheia',
                'value' => 100,
                'date' => '2026-01-15',
                'account_id' => $externalAccount->uuid,
                'category_id' => $category->uuid,
                'installments' => 3,
                'total_value' => 300,
            ]);

        $response->assertSessionHasErrors(['account_id']);
        $this->assertEquals(0, Transaction::count());
    }
}
