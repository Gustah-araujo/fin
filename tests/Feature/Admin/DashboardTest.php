<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserType;
use App\Models\User;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_widget_counts_only_verified_users(): void
    {
        // The visiting admin is verified and counts as one of the 3 verified users.
        $admin = User::factory()->create();
        $admin->forceFill([
            'type' => UserType::Admin,
            'email_verified_at' => now(),
        ])->save();

        User::factory()->times(2)->create();
        User::factory()->times(2)->unverified()->create();

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertInertia(fn ($page) => $page->where('activeUsers', 3));
    }
}
