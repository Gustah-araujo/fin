<?php

namespace Database\Factories;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        $initialBalance = fake()->randomFloat(2, 0, 100000);

        return [
            "uuid" => Str::orderedUuid()->toString(),
            "name" => fake()->company(),
            "type" => fake()->randomElement(["checking", "savings", "investment"]),
            "initial_balance" => $initialBalance,
            "current_balance" => $initialBalance,
        ];
    }
}
