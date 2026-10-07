<?php

declare(strict_types=1);

namespace Tests\Feature\Cards;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Str;

class CardExpensePayGuardTest extends CardTestCase
{
    public function test_paying_card_expense_returns_422(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $transaction = Transaction::create([
            'uuid' => Str::orderedUuid()->toString(),
            'workspace_id' => $workspace->id,
            'credit_card_id' => $card->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'description' => 'Despesa cartão',
            'value' => 200,
            'date' => Carbon::today()->format('Y-m-d'),
            'paid_at' => null,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->post(route('transactions.pay', [$workspace, $transaction]));

        $response->assertSessionHasErrors(['transaction']);
    }

    public function test_unpaying_card_expense_returns_422(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $transaction = Transaction::create([
            'uuid' => Str::orderedUuid()->toString(),
            'workspace_id' => $workspace->id,
            'credit_card_id' => $card->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'description' => 'Despesa cartão paga',
            'value' => 200,
            'date' => Carbon::today()->format('Y-m-d'),
            'paid_at' => now(),
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->post(route('transactions.unpay', [$workspace, $transaction]));

        $response->assertSessionHasErrors(['transaction']);
    }

    public function test_paying_account_expense_still_works(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user, 1000);
        $category = $this->createExpenseCategory($workspace, $user);

        $transaction = Transaction::create([
            'uuid' => Str::orderedUuid()->toString(),
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'description' => 'Despesa conta',
            'value' => 200,
            'date' => Carbon::today()->format('Y-m-d'),
            'paid_at' => null,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->post(route('transactions.pay', [$workspace, $transaction]));

        $response->assertRedirect();

        $transaction->refresh();
        $this->assertNotNull($transaction->paid_at);
    }
}
