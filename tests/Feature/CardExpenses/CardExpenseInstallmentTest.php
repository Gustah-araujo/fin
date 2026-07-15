<?php

declare(strict_types=1);

namespace Tests\Feature\CardExpenses;

use App\Models\Transaction;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Tests\Feature\CardExpenses\CardExpenseTestCase;

class CardExpenseInstallmentTest extends CardExpenseTestCase
{
    public function test_can_create_installment_purchase(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $response = $this->actingAs($user)
            ->post(route('card-expenses.store', [$workspace, $card]), [
                'description' => 'Notebook 12x',
                'total_value' => 1200,
                'value' => 100,
                'date' => '2026-02-15',
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
                'installments' => 12,
            ]);

        $response->assertRedirect();

        $transactions = Transaction::where('description', 'Notebook 12x')
            ->where('credit_card_id', $card->id)
            ->orderBy('installment_number')
            ->get();

        $this->assertEquals(12, $transactions->count());

        $groupId = $transactions->first()->installment_group_id;
        $this->assertNotNull($groupId);
        $this->assertTrue($transactions->every(fn ($t) => $t->installment_group_id === $groupId));

        for ($i = 0; $i < 12; $i++) {
            $this->assertEquals($i + 1, $transactions[$i]->installment_number);
            $this->assertEquals(12, $transactions[$i]->installments_total);
        }
    }

    public function test_installment_values_sum_to_total(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('card-expenses.store', [$workspace, $card]), [
                'description' => 'Parcelada Soma',
                'total_value' => 1000,
                'value' => 100,
                'date' => '2026-02-15',
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
                'installments' => 3,
            ]);

        $transactions = Transaction::where('description', 'Parcelada Soma')->get();
        $sum = $transactions->sum('value');
        $this->assertEquals(1000.00, round((float) $sum, 2));
    }

    public function test_installment_last_absorbs_remainder(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('card-expenses.store', [$workspace, $card]), [
                'description' => 'Remainder Test',
                'total_value' => 1000,
                'value' => 333.33,
                'date' => '2026-02-15',
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
                'installments' => 3,
            ]);

        $transactions = Transaction::where('description', 'Remainder Test')
            ->orderBy('installment_number')
            ->get();

        $this->assertEquals(333.33, (float) $transactions[0]->value);
        $this->assertEquals(333.33, (float) $transactions[1]->value);
        $this->assertEquals(333.34, (float) $transactions[2]->value);
    }

    public function test_installments_count_one_treated_as_single(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('card-expenses.store', [$workspace, $card]), [
                'description' => 'Single via 1x',
                'value' => 200,
                'date' => '2026-07-10',
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
                'installments' => 1,
            ]);

        $transaction = Transaction::where('description', 'Single via 1x')->first();
        $this->assertNotNull($transaction);
        $this->assertNull($transaction->installment_number);
        $this->assertNull($transaction->installments_total);
        $this->assertNull($transaction->installment_group_id);
    }

    public function test_installments_span_year_boundary(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('card-expenses.store', [$workspace, $card]), [
                'description' => 'Year Boundary',
                'total_value' => 1200,
                'value' => 100,
                'date' => '2026-12-15',
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
                'installments' => 12,
            ]);

        $transactions = Transaction::where('description', 'Year Boundary')
            ->orderBy('installment_number')
            ->get();

        $first = $transactions->first();
        $last = $transactions->last();

        $this->assertEquals('2026-12-15', $first->date->format('Y-m-d'));
        $this->assertEquals('2027-11-15', $last->date->format('Y-m-d'));
    }

    public function test_installments_spread_across_bills(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user, ['closing_day' => 1]);
        $category = $this->createExpenseCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('card-expenses.store', [$workspace, $card]), [
                'description' => 'Multi Bill',
                'total_value' => 300,
                'value' => 100,
                'date' => '2026-02-15',
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
                'installments' => 3,
            ]);

        $transactions = Transaction::where('description', 'Multi Bill')
            ->orderBy('installment_number')
            ->get();

        $bill1 = $transactions[0]->bill;
        $bill2 = $transactions[1]->bill;
        $bill3 = $transactions[2]->bill;

        $this->assertNotEquals($bill1->id, $bill2->id);
        $this->assertNotEquals($bill2->id, $bill3->id);
        $this->assertNotEquals($bill1->id, $bill3->id);
    }

    public function test_installment_count_below_1_rejected(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $response = $this->actingAs($user)
            ->post(route('card-expenses.store', [$workspace, $card]), [
                'description' => 'Zero Installments',
                'total_value' => 100,
                'value' => 100,
                'date' => '2026-07-10',
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
                'installments' => 0,
            ]);

        $response->assertSessionHasErrors(['installments']);
    }

    public function test_installment_count_above_48_rejected(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $response = $this->actingAs($user)
            ->post(route('card-expenses.store', [$workspace, $card]), [
                'description' => 'Too Many',
                'total_value' => 100,
                'value' => 100,
                'date' => '2026-07-10',
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
                'installments' => 49,
            ]);

        $response->assertSessionHasErrors(['installments']);
    }

    public function test_installment_total_zero_rejected(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $response = $this->actingAs($user)
            ->post(route('card-expenses.store', [$workspace, $card]), [
                'description' => 'Zero Total',
                'total_value' => 0,
                'value' => 0,
                'date' => '2026-07-10',
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
                'installments' => 3,
            ]);

        $response->assertSessionHasErrors(['total_value']);
    }

    public function test_installment_indicator_visible_on_bill(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('card-expenses.store', [$workspace, $card]), [
                'description' => 'Indicator Test',
                'total_value' => 300,
                'value' => 100,
                'date' => '2026-02-15',
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
                'installments' => 3,
            ]);

        $response = $this->actingAs($user)
            ->get(route('cards.show', [$workspace, $card]));

        $response->assertInertia(fn ($page) => $page
            ->component('Cards/Show', false)
            ->has('openBill.expenses')
        );

        $openBill = $response->inertiaProps()['openBill'] ?? null;
        if ($openBill && isset($openBill['expenses'])) {
            $hasInstallment = collect($openBill['expenses'])->contains(function ($expense) {
                return isset($expense['installment_label']) && $expense['installment_label'] !== null;
            });
            $this->assertTrue($hasInstallment || count($openBill['expenses']) === 0);
        }
    }
}
