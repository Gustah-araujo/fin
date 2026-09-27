<?php

declare(strict_types=1);

namespace Tests\Feature\Cards;

use App\Enums\BillStatus;
use App\Models\CreditCard;
use App\Models\CreditCardBill;
use Carbon\Carbon;

class CardPreCreateBillsTest extends CardTestCase
{
    public function test_creating_card_creates_13_bills(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();

        $response = $this->actingAs($user)
            ->post(route('cards.store', $workspace), [
                'name' => 'Cartão 13 Faturas',
                'credit_limit' => 5000,
                'closing_day' => 1,
                'due_day' => 10,
            ]);

        $response->assertRedirect();

        $card = CreditCard::where('workspace_id', $workspace->id)->first();
        $this->assertNotNull($card);

        $billCount = CreditCardBill::where('credit_card_id', $card->id)->count();
        $this->assertEquals(13, $billCount);

        // All should be Open
        $openCount = CreditCardBill::where('credit_card_id', $card->id)
            ->where('status', BillStatus::Open->value)
            ->count();
        $this->assertEquals(13, $openCount);
    }

    public function test_pre_created_bills_have_correct_periods(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();

        $this->actingAs($user)
            ->post(route('cards.store', $workspace), [
                'name' => 'Cartão Períodos',
                'credit_limit' => 5000,
                'closing_day' => 15,
                'due_day' => 20,
            ]);

        $card = CreditCard::where('workspace_id', $workspace->id)->first();
        $bills = CreditCardBill::where('credit_card_id', $card->id)
            ->orderBy('period_year')
            ->orderBy('period_month')
            ->get();

        $this->assertEquals(13, $bills->count());

        // Verify all 13 bills are consecutive months with correct closing/due dates
        $firstBill = $bills->first();
        $closingDay = min(15, Carbon::createFromDate($firstBill->period_year, $firstBill->period_month, 1)->daysInMonth);

        // Build expected periods from the first bill's period
        $firstPeriod = Carbon::createFromDate($firstBill->period_year, $firstBill->period_month, 1);

        for ($i = 0; $i < 13; $i++) {
            $expectedDate = $firstPeriod->copy()->addMonthsNoOverflow($i);
            $bill = $bills[$i];

            $this->assertEquals($expectedDate->year, $bill->period_year, "Bill {$i} year mismatch");
            $this->assertEquals($expectedDate->month, $bill->period_month, "Bill {$i} month mismatch");

            // Closing date should be day 15 of the period month (clamped)
            $expectedClosing = Carbon::createFromDate($bill->period_year, $bill->period_month, $closingDay)->startOfDay();
            $this->assertTrue(
                $expectedClosing->eq(Carbon::parse($bill->closing_date)->startOfDay()),
                "Bill {$i} closing_date mismatch: expected {$expectedClosing->toDateString()} got ".Carbon::parse($bill->closing_date)->toDateString()
            );

            // Due date should be day 20 of the period month
            $expectedDue = Carbon::createFromDate($bill->period_year, $bill->period_month, 20)->startOfDay();
            $this->assertTrue(
                $expectedDue->eq(Carbon::parse($bill->due_date)->startOfDay()),
                "Bill {$i} due_date mismatch"
            );
        }
    }

    public function test_closing_day_clamps_to_month_end(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();

        $this->actingAs($user)
            ->post(route('cards.store', $workspace), [
                'name' => 'Cartão Dia 31',
                'credit_limit' => 5000,
                'closing_day' => 31,
                'due_day' => 5,
            ]);

        $card = CreditCard::where('workspace_id', $workspace->id)->first();

        // Find the February bill
        $febBill = CreditCardBill::where('credit_card_id', $card->id)
            ->where('period_month', 2)
            ->first();

        $this->assertNotNull($febBill);

        // Closing date should be Feb 28 (or 29 in leap year)
        $febClosing = Carbon::parse($febBill->closing_date);
        $this->assertEquals(2, $febClosing->month);
        $this->assertLessThanOrEqual(29, (int) $febClosing->format('d'));
        $this->assertGreaterThanOrEqual(28, (int) $febClosing->format('d'));
    }
}
