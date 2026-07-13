<?php

namespace Database\Factories;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    public function definition(): array
    {
        return [
            "uuid" => Str::orderedUuid()->toString(),
            "name" => fake()->company() . " Finanças",
            "description" => fake()->optional()->sentence(),
        ];
    }
}
