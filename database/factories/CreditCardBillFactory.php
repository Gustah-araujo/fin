<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BillStatus;
use App\Models\CreditCardBill;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CreditCardBillFactory extends Factory
{
    protected $model = CreditCardBill::class;

    public function definition(): array
    {
        return [
            'uuid' => Str::orderedUuid()->toString(),
            'period_year' => now()->year,
            'period_month' => now()->month,
            'closing_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'status' => BillStatus::Open->value,
            'total_amount' => 0,
            'created_by' => User::factory(),
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BillStatus::Open->value,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BillStatus::Closed->value,
            'closed_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BillStatus::Paid->value,
            'paid_at' => now(),
        ]);
    }
}
