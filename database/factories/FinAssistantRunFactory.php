<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FinAssistantRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinAssistantRun>
 */
class FinAssistantRunFactory extends Factory
{
    protected $model = FinAssistantRun::class;

    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'conversation_id' => fake()->uuid(),
            'status' => 'pending',
            'error_message' => null,
            'result_message_id' => null,
        ];
    }
}
