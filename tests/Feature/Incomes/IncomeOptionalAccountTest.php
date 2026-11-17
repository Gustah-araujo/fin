<?php

declare(strict_types=1);

namespace Tests\Feature\Incomes;

use App\Models\Account;
use App\Models\Transaction;

class IncomeOptionalAccountTest extends IncomeTestCase
{
    public function test_user_can_create_income_without_account(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData([
                'account_id' => null,
            ]));

        $response->assertRedirect(route('incomes.index', $this->workspace));

        $this->assertDatabaseHas('transactions', [
            'workspace_id' => $this->workspace->id,
            'account_id' => null,
            'description' => 'Salário',
            'type' => 'income',
        ]);
    }

    public function test_creating_paid_income_with_account_sets_paid_at_and_updates_balance(): void
    {
        $this->account->update(['initial_balance' => 1000, 'current_balance' => 1000]);

        $response = $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData([
                'is_paid' => true,
            ]));

        $response->assertRedirect(route('incomes.index', $this->workspace));

        $transaction = Transaction::where('description', 'Salário')->first();

        $this->assertNotNull($transaction);
        $this->assertEquals($this->account->id, $transaction->account_id);
        $this->assertNotNull($transaction->paid_at);

        $this->assertEquals(6000, (float) $this->account->fresh()->current_balance);
    }

    public function test_creating_paid_income_without_account_sets_paid_at_and_leaves_balances_untouched(): void
    {
        $this->account->update(['initial_balance' => 1000, 'current_balance' => 1000]);

        $otherAccount = Account::factory()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
            'initial_balance' => 500,
            'current_balance' => 500,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData([
                'account_id' => null,
                'is_paid' => true,
            ]));

        $response->assertRedirect(route('incomes.index', $this->workspace));

        $transaction = Transaction::where('description', 'Salário')->first();

        $this->assertNotNull($transaction);
        $this->assertNull($transaction->account_id);
        $this->assertNotNull($transaction->paid_at);

        $this->assertEquals(1000, (float) $this->account->fresh()->current_balance);
        $this->assertEquals(500, (float) $otherAccount->fresh()->current_balance);
    }

    public function test_pay_on_null_account_income_toggles_paid_at_without_balance_side_effects(): void
    {
        $this->account->update(['initial_balance' => 1000, 'current_balance' => 1000]);

        $transaction = Transaction::factory()->income()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => null,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'value' => 500,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('incomes.pay', [$this->workspace, $transaction]));

        $response->assertRedirect();

        $this->assertNotNull($transaction->fresh()->paid_at);
        $this->assertEquals(1000, (float) $this->account->fresh()->current_balance);

        $response = $this->actingAs($this->user)
            ->post(route('incomes.unpay', [$this->workspace, $transaction]));

        $response->assertRedirect();

        $this->assertNull($transaction->fresh()->paid_at);
        $this->assertEquals(1000, (float) $this->account->fresh()->current_balance);
    }

    public function test_workspace_id_is_correct_for_null_account_income(): void
    {
        $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData([
                'account_id' => null,
            ]));

        $transaction = Transaction::where('description', 'Salário')->first();

        $this->assertNotNull($transaction);
        $this->assertEquals($this->workspace->id, $transaction->workspace_id);
    }
}
