<?php

namespace Tests\Feature\Admin;

use App\Enums\UserType;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkspaceDetailTest extends TestCase
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

    private function insertMember(Workspace $workspace, User $user, string $role, string $createdAt): void
    {
        DB::table('workspace_user')->insert([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => $role,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    public function test_detail_shows_metadata_only(): void
    {
        $admin = $this->makeAdmin();
        $workspace = Workspace::factory()->create([
            'name' => 'Finanças Casa',
            'created_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.workspaces.show', $workspace));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Workspaces/Show')
            ->where('workspace.uuid', $workspace->uuid)
            ->where('workspace.name', 'Finanças Casa')
            ->has('workspace.created_at')
            ->missing('workspace.owner')
        );
    }

    public function test_detail_lists_members_with_roles_and_links(): void
    {
        $admin = $this->makeAdmin();
        $workspace = Workspace::factory()->create();

        $oldest = User::factory()->create([
            'name' => 'Bob Jones',
            'email' => 'bob@example.com',
        ]);
        $newest = User::factory()->create([
            'name' => 'Alice Silva',
            'email' => 'alice@example.com',
        ]);

        $this->insertMember($workspace, $oldest, 'editor', now()->subDay()->format('Y-m-d H:i:s'));
        $this->insertMember($workspace, $newest, 'viewer', now()->format('Y-m-d H:i:s'));

        $response = $this->actingAs($admin)->get(route('admin.workspaces.show', $workspace));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Workspaces/Show')
            ->has('members', 2)
            ->where('members.0.user.uuid', $oldest->uuid)
            ->where('members.0.user.name', 'Bob Jones')
            ->where('members.0.user.email', 'bob@example.com')
            ->where('members.0.role', 'editor')
            ->has('members.0.joined_at')
            ->where('members.1.user.uuid', $newest->uuid)
            ->where('members.1.user.name', 'Alice Silva')
            ->where('members.1.user.email', 'alice@example.com')
            ->where('members.1.role', 'viewer')
            ->has('members.1.joined_at')
        );
    }

    public function test_detail_props_contain_no_financial_keys(): void
    {
        $admin = $this->makeAdmin();
        $workspace = Workspace::factory()->create();

        $response = $this->actingAs($admin)->get(route('admin.workspaces.show', $workspace));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Workspaces/Show')
            ->missing('accounts')
            ->missing('transactions')
            ->missing('balances')
        );
    }
}
