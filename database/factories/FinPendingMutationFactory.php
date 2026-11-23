<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FinPendingMutation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinPendingMutation>
 */
class FinPendingMutationFactory extends Factory
{
    protected $model = FinPendingMutation::class;

    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'conversation_id' => fake()->uuid(),
            'type' => 'expense',
            'payload' => [],
            'status' => 'pending',
            'expires_at' => null,
        ];
    }
}
