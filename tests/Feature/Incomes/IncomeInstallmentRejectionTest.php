<?php

declare(strict_types=1);

namespace Tests\Feature\Incomes;

use App\Models\Recurrence;
use App\Models\Transaction;

class IncomeInstallmentRejectionTest extends IncomeTestCase
{
    public function test_income_installments_payload_rejected(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData([
                'installments' => 3,
            ]));

        $response->assertSessionHasErrors(['installments']);
        $this->assertEquals(0, Transaction::count());
    }

    public function test_income_total_value_rejected(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData([
                'total_value' => 100,
            ]));

        $response->assertSessionHasErrors(['total_value']);
        $this->assertEquals(0, Transaction::count());
    }

    public function test_income_installment_day_rejected(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData([
                'installment_day' => 5,
            ]));

        $response->assertSessionHasErrors(['installment_day']);
        $this->assertEquals(0, Transaction::count());
    }

    public function test_income_without_installment_payload_still_works(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData([
                'is_recurring' => true,
                'frequency' => 'monthly',
                'frequency_day' => now()->day,
                'buffer_ahead' => 3,
            ]));

        $response->assertRedirect(route('incomes.index', $this->workspace));
        $this->assertEquals(1, Recurrence::count());
    }
}
