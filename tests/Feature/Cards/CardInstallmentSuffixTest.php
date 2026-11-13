<?php

declare(strict_types=1);

namespace Tests\Feature\Cards;

use App\Models\Transaction;
use Carbon\Carbon;

class CardInstallmentSuffixTest extends CardTestCase
{
    public function test_card_installment_descriptions_have_suffix(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $response = $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Notebook Dell',
                'value' => 100.00,
                'date' => '2026-01-05',
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
                'installments' => 12,
                'total_value' => 1200.00,
            ]);

        $response->assertRedirect(route('transactions.index', $workspace));

        $transactions = Transaction::where('description', 'like', 'Notebook Dell%')
            ->whereNull('deleted_at')
            ->orderBy('installment_number')
            ->get();

        $this->assertCount(12, $transactions);

        foreach ($transactions as $index => $transaction) {
            $this->assertEquals(
                'Notebook Dell ('.($index + 1).'/12)',
                $transaction->description
            );
        }

        $this->assertEquals(1200.00, round((float) $transactions->sum('value'), 2));
    }

    public function test_card_installments_respect_installment_day(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Compra dia fixo',
                'value' => 100.00,
                'date' => '2026-01-05',
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
                'installments' => 3,
                'total_value' => 300.00,
                'installment_day' => 10,
            ]);

        $transactions = Transaction::where('description', 'like', 'Compra dia fixo%')
            ->whereNull('deleted_at')
            ->orderBy('installment_number')
            ->get();

        $this->assertCount(3, $transactions);

        $dates = $transactions
            ->pluck('date')
            ->map(fn ($date) => $date->toDateString())
            ->toArray();

        $this->assertSame([
            '2026-01-10',
            '2026-02-10',
            '2026-03-10',
        ], $dates);

        foreach ($transactions as $transaction) {
            $this->assertNotNull($transaction->credit_card_bill_id);
        }
    }

    public function test_card_installments_clamp_day_in_short_months(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Compra dia 31',
                'value' => 100.00,
                'date' => '2026-01-31',
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
                'installments' => 3,
                'total_value' => 300.00,
                'installment_day' => 31,
            ]);

        $dates = Transaction::where('description', 'like', 'Compra dia 31%')
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

    public function test_recurring_with_installments_is_rejected_on_account(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $response = $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Recorrência parcelada',
                'value' => 100,
                'date' => Carbon::today()->format('Y-m-d'),
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
                'is_recurring' => true,
                'frequency' => 'monthly',
                'frequency_day' => (int) Carbon::today()->format('d'),
                'installments' => 3,
            ]);

        $response->assertSessionHasErrors(['installments']);
        $this->assertEquals(0, Transaction::count());
    }

    public function test_recurring_rejects_installment_day_and_total_value(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $response = $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Recorrência com dia de parcela',
                'value' => 100,
                'date' => Carbon::today()->format('Y-m-d'),
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
                'is_recurring' => true,
                'frequency' => 'monthly',
                'frequency_day' => (int) Carbon::today()->format('d'),
                'installment_day' => 5,
            ]);

        $response->assertSessionHasErrors(['installment_day']);

        $response = $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Recorrência com valor total',
                'value' => 100,
                'date' => Carbon::today()->format('Y-m-d'),
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
                'is_recurring' => true,
                'frequency' => 'monthly',
                'frequency_day' => (int) Carbon::today()->format('d'),
                'total_value' => 100,
            ]);

        $response->assertSessionHasErrors(['total_value']);

        $this->assertEquals(0, Transaction::count());
    }

    public function test_installment_day_out_of_range_rejected(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $response = $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Dia inválido',
                'value' => 100,
                'date' => Carbon::today()->format('Y-m-d'),
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
                'installments' => 3,
                'total_value' => 300,
                'installment_day' => 32,
            ]);

        $response->assertSessionHasErrors(['installment_day']);
        $this->assertEquals(0, Transaction::count());
    }
}
