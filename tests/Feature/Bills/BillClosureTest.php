<?php

declare(strict_types=1);

namespace Tests\Feature\Bills;

use App\Enums\BillStatus;
use App\Models\CreditCardBill;
use App\Models\Transaction;
use App\Services\BillService;
use Carbon\Carbon;
use Tests\Feature\CardExpenses\CardExpenseTestCase;

class BillClosureTest extends CardExpenseTestCase
{
    public function test_close_bills_before_closes_eligible_bills(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);

        $bill = CreditCardBill::factory()->open()->create([
            'credit_card_id' => $card->id,
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'closing_date' => now()->subDay()->toDateString(),
        ]);

        $count = app(BillService::class)->closeBillsBefore(now());

        $this->assertEquals(1, $count);
        $bill->refresh();
        $this->assertEquals(BillStatus::Closed, $bill->status);
        $this->assertNotNull($bill->closed_at);
    }

    public function test_close_bills_does_not_close_future_bills(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);

        $bill = CreditCardBill::factory()->open()->create([
            'credit_card_id' => $card->id,
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'closing_date' => now()->addDay()->toDateString(),
        ]);

        $count = app(BillService::class)->closeBillsBefore(now());

        $this->assertEquals(0, $count);
        $bill->refresh();
        $this->assertEquals(BillStatus::Open, $bill->status);
    }

    public function test_close_bills_does_not_close_paid_bills(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);

        $bill = CreditCardBill::factory()->paid()->create([
            'credit_card_id' => $card->id,
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'closing_date' => now()->subWeek()->toDateString(),
        ]);

        $count = app(BillService::class)->closeBillsBefore(now());

        $this->assertEquals(0, $count);
        $bill->refresh();
        $this->assertEquals(BillStatus::Paid, $bill->status);
    }

    public function test_open_bill_cannot_receive_expenses_after_closing(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user, ['closing_day' => 1]);
        $category = $this->createExpenseCategory($workspace, $user);

        $bill = CreditCardBill::factory()->open()->create([
            'credit_card_id' => $card->id,
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'period_year' => 2026,
            'period_month' => 2,
            'closing_date' => '2026-03-01',
            'due_date' => '2026-03-10',
        ]);

        app(BillService::class)->closeBill($bill);

        $this->actingAs($user)
            ->post(route('card-expenses.store', [$workspace, $card]), [
                'description' => 'After Close',
                'value' => 100,
                'date' => '2026-03-05',
                'credit_card_id' => $card->uuid,
                'category_id' => $category->uuid,
            ]);

        $newTransaction = Transaction::where('description', 'After Close')->first();
        $this->assertNotNull($newTransaction);
        $this->assertNotEquals($bill->id, $newTransaction->credit_card_bill_id);
    }

    public function test_job_continues_on_individual_failure(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);

        $bill1 = CreditCardBill::factory()->open()->create([
            'credit_card_id' => $card->id,
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'closing_date' => now()->subDay()->toDateString(),
        ]);

        $bill2 = CreditCardBill::factory()->open()->create([
            'credit_card_id' => $card->id,
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'period_year' => now()->subMonth()->year,
            'period_month' => now()->subMonth()->month,
            'closing_date' => now()->subWeek()->toDateString(),
        ]);

        $mock = $this->partialMock(BillService::class);
        $mock->shouldReceive('closeBill')
            ->withArgs(fn ($b) => $b->id === $bill1->id)
            ->once()
            ->andThrow(new \Exception('DB error'));
        $mock->shouldReceive('closeBill')
            ->withArgs(fn ($b) => $b->id === $bill2->id)
            ->once()
            ->andReturnUsing(function ($b) {
                $b->status = BillStatus::Closed;
                $b->closed_at = now();
                $b->save();
            });
        $mock->shouldReceive('computeBillPeriod', 'computeClosingDate', 'computeDueDate', 'findOrCreateBill', 'recalculateBillTotal', 'payBill', 'undoPayment')
            ->andReturn(null);

        $count = $mock->closeBillsBefore(now());

        $this->assertEquals(1, $count);
        $bill2->refresh();
        $this->assertEquals(BillStatus::Closed, $bill2->status);
    }

    public function test_close_bills_does_not_create_empty_bills(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $card = $this->createCard($workspace, $user);

        $count = app(BillService::class)->closeBillsBefore(now());

        $this->assertEquals(0, $count);
        $this->assertEquals(0, CreditCardBill::where('credit_card_id', $card->id)->count());
    }
}
