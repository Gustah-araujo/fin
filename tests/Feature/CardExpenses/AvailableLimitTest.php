<?php

declare(strict_types=1);

namespace Tests\Feature\CardExpenses;

use App\Enums\BillStatus;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\CreditCardBill;
use App\Models\Transaction;
use App\Services\CreditCardService;
use Tests\Feature\CardExpenses\CardExpenseTestCase;

class AvailableLimitTest extends CardExpenseTestCase
{
    public function test_available_limit_decreases_on_expense_create(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        Transaction::create([
            'uuid' => \Illuminate\Support\Str::orderedUuid()->toString(),
            'workspace_id' => $workspace->id,
            'account_id' => null,
            'credit_card_id' => $card->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'description' => 'Compra Teste',
            'value' => 1000,
            'date' => '2026-07-10',
            'paid_at' => null,
            'created_by' => $user->id,
        ]);

        app(CreditCardService::class)->recalculateAvailableLimit($card->fresh());

        $this->assertEquals(4000, (float) $card->fresh()->available_limit);
    }

    public function test_available_limit_increases_on_expense_delete(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $transaction = Transaction::create([
            'uuid' => \Illuminate\Support\Str::orderedUuid()->toString(),
            'workspace_id' => $workspace->id,
            'account_id' => null,
            'credit_card_id' => $card->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'description' => 'Compra Teste',
            'value' => 1000,
            'date' => '2026-07-10',
            'paid_at' => null,
            'created_by' => $user->id,
        ]);

        app(CreditCardService::class)->recalculateAvailableLimit($card->fresh());
        $this->assertEquals(4000, (float) $card->fresh()->available_limit);

        $transaction->delete();
        app(CreditCardService::class)->recalculateAvailableLimit($card->fresh());

        $this->assertEquals(5000, (float) $card->fresh()->available_limit);
    }

    public function test_available_limit_ignores_paid_bill_expenses(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $bill = CreditCardBill::factory()->paid()->create([
            'credit_card_id' => $card->id,
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        Transaction::create([
            'uuid' => \Illuminate\Support\Str::orderedUuid()->toString(),
            'workspace_id' => $workspace->id,
            'account_id' => null,
            'credit_card_id' => $card->id,
            'credit_card_bill_id' => $bill->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'description' => 'Compra Paga',
            'value' => 1000,
            'date' => '2026-07-10',
            'paid_at' => null,
            'created_by' => $user->id,
        ]);

        app(CreditCardService::class)->recalculateAvailableLimit($card->fresh());

        $this->assertEquals(5000, (float) $card->fresh()->available_limit);
    }

    public function test_available_limit_sums_multiple_expenses(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        foreach ([100, 200, 50] as $value) {
            Transaction::create([
                'uuid' => \Illuminate\Support\Str::orderedUuid()->toString(),
                'workspace_id' => $workspace->id,
                'account_id' => null,
                'credit_card_id' => $card->id,
                'category_id' => $category->id,
                'type' => 'expense',
                'description' => "Compra {$value}",
                'value' => $value,
                'date' => '2026-07-10',
                'paid_at' => null,
                'created_by' => $user->id,
            ]);
        }

        app(CreditCardService::class)->recalculateAvailableLimit($card->fresh());

        $this->assertEquals(4650, (float) $card->fresh()->available_limit);
    }

    public function test_available_limit_correct_for_installment_partial(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        $groupId = \Illuminate\Support\Str::orderedUuid()->toString();

        foreach ([1, 2, 3] as $num) {
            Transaction::create([
                'uuid' => \Illuminate\Support\Str::orderedUuid()->toString(),
                'workspace_id' => $workspace->id,
                'account_id' => null,
                'credit_card_id' => $card->id,
                'category_id' => $category->id,
                'type' => 'expense',
                'description' => 'Parcelada',
                'value' => 100,
                'date' => "2026-0{$num}-10",
                'paid_at' => null,
                'installment_number' => $num,
                'installments_total' => 3,
                'installment_group_id' => $groupId,
                'created_by' => $user->id,
            ]);
        }

        app(CreditCardService::class)->recalculateAvailableLimit($card->fresh());

        $this->assertEquals(4700, (float) $card->fresh()->available_limit);
    }

    public function test_recalculate_available_limit_after_credit_limit_change(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);
        $category = $this->createExpenseCategory($workspace, $user);

        Transaction::create([
            'uuid' => \Illuminate\Support\Str::orderedUuid()->toString(),
            'workspace_id' => $workspace->id,
            'account_id' => null,
            'credit_card_id' => $card->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'description' => 'Compra',
            'value' => 1000,
            'date' => '2026-07-10',
            'paid_at' => null,
            'created_by' => $user->id,
        ]);

        $card->credit_limit = 8000;
        $card->save();

        app(CreditCardService::class)->recalculateAvailableLimit($card->fresh());

        $this->assertEquals(7000, (float) $card->fresh()->available_limit);
    }
}
