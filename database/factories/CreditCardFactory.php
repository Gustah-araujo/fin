<?php

namespace Database\Factories;

use App\Models\CreditCard;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CreditCardFactory extends Factory
{
    protected $model = CreditCard::class;

    public function definition(): array
    {
        $creditLimit = fake()->randomFloat(2, 1000, 50000);

        return [
            'uuid' => Str::orderedUuid()->toString(),
            'name' => fake()->company(),
            'credit_limit' => $creditLimit,
            'available_limit' => $creditLimit,
            'closing_day' => fake()->numberBetween(1, 28),
            'due_day' => fake()->numberBetween(1, 28),
        ];
    }
}
