<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'uuid' => Str::orderedUuid()->toString(),
            'description' => fake()->sentence(3),
            'type' => 'expense',
            'value' => fake()->randomFloat(2, 10, 5000),
            'date' => fake()->date(),
            'paid_at' => null,
            'credit_card_id' => null,
            'credit_card_bill_id' => null,
            'installment_number' => null,
            'installments_total' => null,
            'installment_group_id' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'paid_at' => now(),
        ]);
    }

    public function unpaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'paid_at' => null,
        ]);
    }

    public function income(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'income',
        ]);
    }
}
