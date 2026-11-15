<?php

namespace Tests\Feature\Admin;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Router;
use Tests\TestCase;

class TestingCreateAdminTest extends TestCase
{
    public function test_creates_verified_admin_and_logs_in(): void
    {
        $response = $this->post(route('testing.create-admin'), [
            'email' => 'admin@example.com',
            'name' => 'Admin E2E',
            'password' => 'password123',
        ]);

        $response->assertOk();

        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->assertSame(UserType::Admin, $user->type);
        $this->assertNotNull($user->email_verified_at);

        $this->assertAuthenticatedAs($user);

        $this->get('/admin')->assertOk();
    }

    public function test_promotes_existing_user(): void
    {
        $customer = User::factory()->create([
            'email' => 'promote@example.com',
        ]);

        $response = $this->post(route('testing.create-admin'), [
            'email' => 'promote@example.com',
            'name' => 'Renamed Admin',
            'password' => 'password123',
        ]);

        $response->assertOk();

        $this->assertSame(1, User::query()->where('email', 'promote@example.com')->count());

        $customer->refresh();

        $this->assertSame(UserType::Admin, $customer->type);
        $this->assertNotNull($customer->email_verified_at);

        $this->assertAuthenticatedAs($customer);
    }

    public function test_unavailable_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->app->make(Router::class)->setRoutes(new RouteCollection);
        require base_path('routes/web.php');

        $this->withoutMiddleware()
            ->post('/testing/create-admin', [
                'email' => 'admin@example.com',
                'name' => 'Admin E2E',
                'password' => 'password123',
            ])
            ->assertNotFound();

        $this->assertNull(User::query()->where('email', 'admin@example.com')->first());
    }
}
