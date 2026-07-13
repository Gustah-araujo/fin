<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    public function test_user_can_change_password(): void
    {
        $user = User::factory()->create([
            'password' => 'oldpassword',
        ]);

        $response = $this->actingAs($user)->put('/settings/password', [
            'current_password' => 'oldpassword',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ]);

        $response->assertSessionHas('status');
        $this->assertTrue(Hash::check('newpassword', $user->fresh()->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create([
            'password' => 'currentpassword',
        ]);

        $response = $this->actingAs($user)->put('/settings/password', [
            'current_password' => 'wrongpassword',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ]);

        $response->assertSessionHasErrors('current_password');
    }
}
