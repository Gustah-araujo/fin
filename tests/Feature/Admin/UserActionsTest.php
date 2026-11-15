<?php

namespace Tests\Feature\Admin;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserActionsTest extends TestCase
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

    public function test_resend_sends_verification_email_synchronously(): void
    {
        Notification::fake();
        $admin = $this->makeAdmin();
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.users.resend-verification', $user));

        $response->assertRedirect();
        Notification::assertSentTo($user, VerifyEmail::class);
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
    }

    public function test_resend_flashes_success_toast_message(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.users.resend-verification', $user));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'E-mail de verificação reenviado.');
    }

    public function test_resend_is_throttled(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->unverified()->create();

        foreach (range(1, 6) as $attempt) {
            $response = $this->actingAs($admin)
                ->post(route('admin.users.resend-verification', $user));
            $response->assertRedirect();
        }

        $response = $this->actingAs($admin)
            ->post(route('admin.users.resend-verification', $user));

        $response->assertStatus(429);
    }

    public function test_manual_verify_fills_email_verified_at(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.users.verify', $user));

        $response->assertRedirect();
        $this->assertNotNull($user->fresh()->email_verified_at);
        $response->assertSessionHas('success', 'Usuário marcado como verificado.');
    }

    public function test_resend_on_verified_user_returns_422(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.users.resend-verification', $user), [], ['Accept' => 'application/json']);

        $response->assertStatus(422);
    }

    public function test_manual_verify_on_verified_user_returns_422(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.users.verify', $user), [], ['Accept' => 'application/json']);

        $response->assertStatus(422);
    }

    public function test_actions_rejected_for_customers_403(): void
    {
        $customer = User::factory()->create();
        $target = User::factory()->unverified()->create();

        $resend = $this->actingAs($customer)
            ->post(route('admin.users.resend-verification', $target));
        $resend->assertForbidden();

        $verify = $this->actingAs($customer)
            ->post(route('admin.users.verify', $target));
        $verify->assertForbidden();
    }
}
