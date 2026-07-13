<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'uuid' => Str::orderedUuid()->toString(),
            'name' => fake()->unique()->word(),
            'type' => fake()->randomElement(['income', 'expense', 'both']),
            'color' => '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT),
            'icon' => fake()->optional()->randomElement(['shopping-cart', 'home', 'car', 'utensils', 'heart', 'briefcase', 'music', 'gamepad']),
        ];
    }

    public function income(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'income']);
    }

    public function expense(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'expense']);
    }

    public function both(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'both']);
    }
}
