<?php

declare(strict_types=1);

namespace Tests\Feature\Bills;

use App\Enums\BillStatus;
use App\Models\Account;
use App\Models\CreditCardBill;
use App\Models\Transaction;
use Tests\Feature\CardExpenses\CardExpenseTestCase;

class BillViewTest extends CardExpenseTestCase
{
    public function test_card_show_displays_open_bill(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $bill = CreditCardBill::factory()->open()->create([
            'credit_card_id' => $card->id,
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        Transaction::create([
            'uuid' => \Illuminate\Support\Str::orderedUuid()->toString(),
            'workspace_id' => $workspace->id,
            'credit_card_id' => $card->id,
            'credit_card_bill_id' => $bill->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'description' => 'Bill Expense',
            'value' => 100,
            'date' => '2026-07-10',
            'paid_at' => null,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('cards.show', [$workspace, $card]));

        $response->assertInertia(fn ($page) => $page
            ->component('Cards/Show', false)
            ->has('openBill.expenses')
        );
    }

    public function test_card_show_displays_card_info(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user, ['credit_limit' => 8000, 'available_limit' => 7500]);

        $response = $this->actingAs($user)
            ->get(route('cards.show', [$workspace, $card]));

        $response->assertInertia(fn ($page) => $page
            ->component('Cards/Show', false)
            ->where('card.uuid', $card->uuid)
            ->where('card.credit_limit', 8000)
            ->where('card.available_limit', 7500)
            ->where('card.closing_day', 1)
            ->where('card.due_day', 10)
        );
    }

    public function test_empty_open_bill_shows_empty_state(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);

        $response = $this->actingAs($user)
            ->get(route('cards.show', [$workspace, $card]));

        $response->assertInertia(fn ($page) => $page
            ->component('Cards/Show', false)
            ->where('openBill', null)
        );
    }

    public function test_card_show_lists_previous_bills(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);

        CreditCardBill::factory()->closed()->create([
            'credit_card_id' => $card->id,
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'period_year' => 2026,
            'period_month' => 6,
        ]);

        $response = $this->actingAs($user)
            ->get(route('cards.show', [$workspace, $card]));

        $response->assertInertia(fn ($page) => $page
            ->component('Cards/Show', false)
            ->has('bills', 1)
        );
    }

    public function test_card_show_available_limit_from_persisted_column(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user, ['credit_limit' => 10000, 'available_limit' => 3333]);

        $response = $this->actingAs($user)
            ->get(route('cards.show', [$workspace, $card]));

        $response->assertInertia(fn ($page) => $page
            ->component('Cards/Show', false)
            ->where('card.available_limit', 3333)
        );
    }

    public function test_bill_show_displays_paid_bill_info(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $account = $this->createAccount($workspace, $user);

        $bill = CreditCardBill::factory()->paid()->create([
            'credit_card_id' => $card->id,
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'paid_to_account_id' => $account->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('bills.show', [$workspace, $bill]));

        $response->assertInertia(fn ($page) => $page
            ->component('Bills/Show', false)
            ->where('bill.status', 'paid')
            ->has('bill.paid_at')
        );
    }

    public function test_bill_show_displays_closed_bill_info(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);

        $bill = CreditCardBill::factory()->closed()->create([
            'credit_card_id' => $card->id,
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('bills.show', [$workspace, $bill]));

        $response->assertInertia(fn ($page) => $page
            ->component('Bills/Show', false)
            ->where('bill.status', 'closed')
            ->has('bill.closing_date')
            ->has('bill.due_date')
        );
    }
}
