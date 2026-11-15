<?php

namespace Tests\Feature\Admin;

use App\Enums\UserType;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UsersListingTest extends TestCase
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

    private function attachWorkspaces(User $user, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $workspace = Workspace::factory()->create();
            $workspace->members()->attach($user, ['role' => 'admin']);
        }
    }

    public function test_datatable_requires_admin(): void
    {
        $customer = User::factory()->create();

        $response = $this->actingAs($customer)->get(route('admin.users.datatable'));

        $response->assertForbidden();
    }

    public function test_datatable_returns_resource_shape(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->unverified()->create(['created_at' => now()->addDay()]);
        $this->attachWorkspaces($user, 2);

        $response = $this->actingAs($admin)->get(route('admin.users.datatable'));

        $response->assertOk();
        $response->assertJsonStructure(['data', 'meta']);
        $response->assertJson(fn ($json) => $json
            ->has('data', 2)
            ->has('meta.current_page')
            ->has('meta.total')
            ->has('data.0.uuid')
            ->where('data.0.name', $user->name)
            ->where('data.0.email', $user->email)
            ->where('data.0.type', 'customer')
            ->where('data.0.email_verified_at', null)
            ->has('data.0.created_at')
            ->where('data.0.workspaces_count', 2)
        );
    }

    public function test_search_filters_by_name_or_email_case_insensitive(): void
    {
        $admin = $this->makeAdmin();
        $alice = User::factory()->create(['name' => 'Alice Silva', 'email' => 'alice@example.com']);
        User::factory()->create(['name' => 'Bob Jones', 'email' => 'bob@example.com']);

        $byName = $this->actingAs($admin)
            ->get(route('admin.users.datatable', ['search' => 'alice sil']));

        $byName->assertOk();
        $byName->assertJsonCount(1, 'data');
        $byName->assertJsonPath('data.0.uuid', $alice->uuid);

        $byEmail = $this->actingAs($admin)
            ->get(route('admin.users.datatable', ['search' => 'ALICE@EXAMPLE']));

        $byEmail->assertOk();
        $byEmail->assertJsonCount(1, 'data');
        $byEmail->assertJsonPath('data.0.uuid', $alice->uuid);
    }

    public function test_filter_verified_shows_only_verified(): void
    {
        $admin = $this->makeAdmin();
        User::factory()->unverified()->create();
        $verified = User::factory()->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.users.datatable', ['email_verified_at' => 'verified']));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $uuids = collect($response->json('data'))->pluck('uuid');
        $this->assertContains($verified->uuid, $uuids);
        $this->assertContains($admin->uuid, $uuids);
    }

    public function test_filter_unverified_shows_only_unverified(): void
    {
        $admin = $this->makeAdmin();
        $unverified = User::factory()->unverified()->create();
        User::factory()->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.users.datatable', ['email_verified_at' => 'unverified']));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.uuid', $unverified->uuid);
    }

    public function test_sort_by_created_at_desc_default(): void
    {
        $admin = $this->makeAdmin();
        $first = User::factory()->create(['created_at' => now()->addDay()]);
        $second = User::factory()->create(['created_at' => now()->addDays(2)]);
        $third = User::factory()->create(['created_at' => now()->addDays(3)]);

        $default = $this->actingAs($admin)->get(route('admin.users.datatable'));

        $default->assertOk();
        $default->assertJsonPath('data.0.uuid', $third->uuid);
        $default->assertJsonPath('data.1.uuid', $second->uuid);
        $default->assertJsonPath('data.2.uuid', $first->uuid);
        $default->assertJsonPath('data.3.uuid', $admin->uuid);
    }

    public function test_workspaces_count_no_n_plus_one(): void
    {
        $admin = $this->makeAdmin();
        $this->attachWorkspaces($admin, 2);
        for ($i = 0; $i < 3; $i++) {
            $user = User::factory()->create();
            $this->attachWorkspaces($user, 2);
        }

        DB::enableQueryLog();
        $this->actingAs($admin)->get(route('admin.users.datatable'));
        $firstRun = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        for ($i = 0; $i < 10; $i++) {
            $user = User::factory()->create();
            $this->attachWorkspaces($user, 2);
        }

        DB::enableQueryLog();
        $this->actingAs($admin)->get(route('admin.users.datatable'));
        $secondRun = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($firstRun, $secondRun, 'Expected constant query count regardless of user count (no N+1 on workspaces_count)');
    }
}
