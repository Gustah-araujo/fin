<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Recurrence;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecurrenceFactory extends Factory
{
    protected $model = Recurrence::class;

    public function definition(): array
    {
        return [
            'type' => 'income',
            'description' => fake()->sentence(3),
            'value' => fake()->randomFloat(2, 100, 10000),
            'frequency' => 'monthly',
            'frequency_day' => fake()->numberBetween(1, 28),
            'start_date' => fake()->date(),
            'until_date' => null,
            'next_date' => fake()->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            'status' => 'active',
        ];
    }

    public function weekly(): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => 'weekly',
            'frequency_day' => fake()->numberBetween(0, 6),
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paused',
        ]);
    }

    public function exhausted(): static
    {
        return $this->state(fn (array $attributes) => [
            'next_date' => null,
        ]);
    }
}
