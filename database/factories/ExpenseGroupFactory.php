<?php

namespace Database\Factories;

use App\Models\ExpenseGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ExpenseGroup>
 */
class ExpenseGroupFactory extends Factory
{
    protected $model = ExpenseGroup::class;

    public function definition(): array
    {
        return [
            'uuid' => Str::orderedUuid()->toString(),
            'name' => fake()->unique()->word(),
            'color' => '#'.str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT),
        ];
    }
}
