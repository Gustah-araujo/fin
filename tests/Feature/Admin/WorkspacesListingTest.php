<?php

namespace Tests\Feature\Admin;

use App\Enums\UserType;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspacesListingTest extends TestCase
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

    private function attachMembers(Workspace $workspace, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $workspace->members()->attach(User::factory()->create(), ['role' => 'admin']);
        }
    }

    public function test_index_requires_admin_403(): void
    {
        $customer = User::factory()->create();

        $response = $this->actingAs($customer)->get(route('admin.workspaces.index'));

        $response->assertForbidden();
    }

    public function test_index_lists_all_workspaces_with_members_count(): void
    {
        $admin = $this->makeAdmin();
        $oldest = Workspace::factory()->create(['created_at' => now()->subDays(2)]);
        $middle = Workspace::factory()->create(['created_at' => now()->subDays(1)]);
        $newest = Workspace::factory()->create(['created_at' => now()]);

        $this->attachMembers($oldest, 1);
        $this->attachMembers($middle, 2);
        $this->attachMembers($newest, 5);

        $response = $this->actingAs($admin)->get(route('admin.workspaces.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Workspaces/Index')
            ->has('workspaces', 3)
            ->where('workspaces.0.uuid', $newest->uuid)
            ->where('workspaces.0.members_count', 5)
            ->where('workspaces.1.uuid', $middle->uuid)
            ->where('workspaces.1.members_count', 2)
            ->where('workspaces.2.uuid', $oldest->uuid)
            ->where('workspaces.2.members_count', 1)
        );
    }
}
