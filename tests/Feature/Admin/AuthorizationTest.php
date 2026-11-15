<?php

namespace Tests\Feature\Admin;

use App\Enums\UserType;
use App\Models\User;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    public function test_customer_gets_403_on_admin_dashboard(): void
    {
        $customer = User::factory()->create();

        $response = $this->actingAs($customer)->get(route('admin.dashboard'));

        $response->assertForbidden();
    }

    public function test_customer_gets_403_on_admin_users_index(): void
    {
        $customer = User::factory()->create();

        $response = $this->actingAs($customer)->get(route('admin.users.index'));

        $response->assertForbidden();
    }

    public function test_customer_gets_403_on_admin_action_post(): void
    {
        $customer = User::factory()->create();
        $target = User::factory()->unverified()->create();

        $response = $this->actingAs($customer)
            ->post(route('admin.users.verify', $target));

        $response->assertForbidden();
    }

    public function test_unauthenticated_redirects_to_login(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_unverified_admin_redirects_to_verification_notice(): void
    {
        $admin = User::factory()->unverified()->create();
        $admin->forceFill(['type' => UserType::Admin])->save();

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertRedirect(route('verification.notice'));
    }

    public function test_admin_with_zero_workspaces_gets_200_on_admin_dashboard(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill([
            'type' => UserType::Admin,
            'email_verified_at' => now(),
        ])->save();

        $this->assertSame(0, $admin->workspaces()->count());

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
    }
}
