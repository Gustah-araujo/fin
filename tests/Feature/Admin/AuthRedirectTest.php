<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserType;
use App\Models\User;
use Tests\TestCase;

class AuthRedirectTest extends TestCase
{
    public function test_verified_admin_redirects_to_admin_dashboard_after_login(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $admin->forceFill(['type' => UserType::Admin])->save();

        $response = $this->post(route('login'), [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_verified_customer_keeps_workspace_select_redirect(): void
    {
        User::factory()->create([
            'email' => 'customer@example.com',
            'password' => 'password',
        ]);

        $response = $this->post(route('login'), [
            'email' => 'customer@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('workspace.select'));
    }

    public function test_unverified_user_redirects_to_verification_notice_regardless_of_type(): void
    {
        User::factory()->unverified()->create([
            'email' => 'unverified-customer@example.com',
            'password' => 'password',
        ]);

        $customerResponse = $this->post(route('login'), [
            'email' => 'unverified-customer@example.com',
            'password' => 'password',
        ]);

        $customerResponse->assertRedirect(route('verification.notice'));

        $this->post(route('logout'));

        User::factory()->unverified()->create([
            'email' => 'unverified-admin@example.com',
            'password' => 'password',
        ]);
        $unverifiedAdmin = User::where('email', 'unverified-admin@example.com')->firstOrFail();
        $unverifiedAdmin->forceFill(['type' => UserType::Admin])->save();

        $adminResponse = $this->post(route('login'), [
            'email' => 'unverified-admin@example.com',
            'password' => 'password',
        ]);

        $adminResponse->assertRedirect(route('verification.notice'));
    }

    public function test_inertia_shares_user_type(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['type' => UserType::Admin])->save();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn ($page) => $page->where('auth.user.type', 'admin'));

        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->get(route('workspace.select'))
            ->assertInertia(fn ($page) => $page->where('auth.user.type', 'customer'));
    }
}
