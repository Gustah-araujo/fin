<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    public function test_user_can_request_password_reset_link(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $response = $this->post('/forgot-password', [
            'email' => 'test@example.com',
        ]);

        $response->assertSessionHas('status');
    }

    public function test_non_existent_email_gets_same_response(): void
    {
        $response = $this->post('/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertSessionHas('status');
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);
        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'email' => 'test@example.com',
            'token' => $token,
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');

        $this->assertTrue(Hash::check('newpassword', $user->fresh()->password));
    }

    public function test_expired_token_is_rejected(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $response = $this->post('/reset-password', [
            'email' => 'test@example.com',
            'token' => 'invalid-token',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ]);

        $response->assertSessionHasErrors('email');
    }
}
