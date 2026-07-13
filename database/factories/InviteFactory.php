<?php

namespace Database\Factories;

use App\Enums\InviteStatus;
use App\Enums\WorkspaceRole;
use App\Models\Invite;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class InviteFactory extends Factory
{
    protected $model = Invite::class;

    public function definition(): array
    {
        return [
            "uuid" => Str::orderedUuid()->toString(),
            "workspace_id" => Workspace::factory(),
            "email" => fake()->email(),
            "role" => fake()->randomElement([WorkspaceRole::Admin, WorkspaceRole::Editor, WorkspaceRole::Viewer]),
            "inviter_id" => User::factory(),
            "status" => InviteStatus::Pending,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            "status" => InviteStatus::Pending,
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            "status" => InviteStatus::Accepted,
        ]);
    }

    public function declined(): static
    {
        return $this->state(fn (array $attributes) => [
            "status" => InviteStatus::Declined,
        ]);
    }
}
