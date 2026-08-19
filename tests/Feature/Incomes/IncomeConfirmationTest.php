<?php

declare(strict_types=1);

namespace Tests\Feature\Incomes;

use App\Models\Transaction;

class IncomeConfirmationTest extends IncomeTestCase
{
    public function test_confirming_receipt_increases_account_balance(): void
    {
        $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData([
                'value' => 2000,
            ]));

        $transaction = Transaction::where('description', 'Salário')->first();
        $this->assertEquals($this->account->initial_balance, $this->account->refresh()->current_balance);

        $this->actingAs($this->user)
            ->post(route('incomes.pay', [$this->workspace, $transaction]));

        $expected = (float) $this->account->initial_balance + 2000;
        $this->assertEquals($expected, $this->account->refresh()->current_balance);
    }

    public function test_unconfirming_receipt_restores_balance(): void
    {
        $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData([
                'value' => 1500,
            ]));

        $transaction = Transaction::where('description', 'Salário')->first();

        $this->actingAs($this->user)->post(route('incomes.pay', [$this->workspace, $transaction]));
        $this->assertEquals((float) $this->account->initial_balance + 1500, $this->account->refresh()->current_balance);

        $this->actingAs($this->user)->post(route('incomes.unpay', [$this->workspace, $transaction]));
        $this->assertEquals((float) $this->account->initial_balance, $this->account->refresh()->current_balance);
    }

    public function test_confirming_receipt_marks_transaction_paid(): void
    {
        $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData());

        $transaction = Transaction::where('description', 'Salário')->first();

        $this->actingAs($this->user)->post(route('incomes.pay', [$this->workspace, $transaction]));

        $this->assertNotNull($transaction->refresh()->paid_at);
    }

    public function test_income_index_shows_incomes(): void
    {
        $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData());

        $response = $this->actingAs($this->user)
            ->get(route('incomes.index', $this->workspace));

        $response->assertInertia(fn ($page) => $page
            ->component('Incomes/Index', false)
            ->has('incomes.data', 1)
        );
    }
}
