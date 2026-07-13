<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    protected $model = User::class;

    public function definition(): array
    {
        return [
            "uuid" => Str::orderedUuid()->toString(),
            "name" => fake()->name(),
            "email" => fake()->unique()->safeEmail(),
            "email_verified_at" => now(),
            "password" => static::$password ??= Hash::make("password"),
            "remember_token" => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            "email_verified_at" => null,
        ]);
    }

    public function withGoogle(): static
    {
        return $this->state(fn (array $attributes) => [
            "google_id" => Str::random(21),
            "avatar" => "https://lh3.googleusercontent.com/a/test",
        ]);
    }

    public function withoutPassword(): static
    {
        return $this->state(fn (array $attributes) => [
            "password" => null,
        ]);
    }
}
