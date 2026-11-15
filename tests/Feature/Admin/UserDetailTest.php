<?php

namespace Tests\Feature\Admin;

use App\Enums\UserType;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDetailTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill([
            'type' => UserType::Admin,
            'email_verified_at' => now(),
        ])->save();

        return $admin;
    }

    public function test_detail_shows_user_data(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->unverified()->create([
            'name' => 'Alice Silva',
            'email' => 'alice@example.com',
            'created_at' => now()->subDays(3),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.users.show', $user));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Show')
            ->where('user.uuid', $user->uuid)
            ->where('user.name', 'Alice Silva')
            ->where('user.email', 'alice@example.com')
            ->where('user.type', 'customer')
            ->where('user.email_verified_at', null)
            ->has('user.created_at')
        );
    }

    public function test_detail_lists_workspaces_with_roles(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->create();

        $first = Workspace::factory()->create();
        $second = Workspace::factory()->create();
        $first->members()->attach($user, ['role' => 'admin']);
        $second->members()->attach($user, ['role' => 'editor']);

        $response = $this->actingAs($admin)
            ->get(route('admin.users.show', $user));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Show')
            ->has('workspaces', 2)
            ->where('workspaces.0.uuid', $first->uuid)
            ->where('workspaces.0.name', $first->name)
            ->where('workspaces.0.role', 'admin')
            ->where('workspaces.1.uuid', $second->uuid)
            ->where('workspaces.1.name', $second->name)
            ->where('workspaces.1.role', 'editor')
        );
    }

    public function test_detail_empty_state_for_zero_workspaces(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.users.show', $user));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Show')
            ->has('workspaces', 0)
        );
    }
}
