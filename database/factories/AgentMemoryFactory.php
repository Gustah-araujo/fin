<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AgentMemory;
use Illuminate\Database\Eloquent\Factories\Factory;

class AgentMemoryFactory extends Factory
{
    protected $model = AgentMemory::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'content' => fake()->paragraph(),
            'type' => 'insight',
            'source' => null,
            'expires_at' => null,
        ];
    }

    public function pattern(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'pattern']);
    }

    public function anomaly(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'anomaly']);
    }
}
