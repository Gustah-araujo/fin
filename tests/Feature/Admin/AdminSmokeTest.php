<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserType;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSmokeTest extends TestCase
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

    public function test_dashboard_smoke(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Admin/Dashboard'));
    }

    public function test_users_index_smoke(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Admin/Users/Index'));
    }

    public function test_users_show_smoke(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->get(route('admin.users.show', $user));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Admin/Users/Show'));
    }

    public function test_workspaces_index_smoke(): void
    {
        $admin = $this->makeAdmin();
        Workspace::factory()->create();

        $response = $this->actingAs($admin)->get(route('admin.workspaces.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Admin/Workspaces/Index'));
    }

    public function test_workspaces_show_smoke(): void
    {
        $admin = $this->makeAdmin();
        $workspace = Workspace::factory()->create();

        $response = $this->actingAs($admin)->get(route('admin.workspaces.show', $workspace));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Admin/Workspaces/Show'));
    }
}
