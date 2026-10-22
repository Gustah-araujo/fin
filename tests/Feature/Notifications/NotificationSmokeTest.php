<?php

namespace Tests\Feature\Notifications;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationSmokeTest extends TestCase
{
    public function test_notifications_index_returns_ok(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->get(route('notifications.index', $workspace));

        $response->assertOk();
        $response->assertJsonStructure(['unread_count', 'notifications']);
    }

    public function test_mark_as_read_returns_ok(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $notification = DatabaseNotification::create([
            'id' => Str::uuid()->toString(),
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => [
                'title' => 'Smoke Test',
                'message' => 'Smoke Message',
                'workspace_uuid' => $workspace->uuid,
            ],
            'read_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->patch(route('notifications.read', [$workspace, $notification]));

        $response->assertOk();
        $response->assertJsonStructure(['unread_count', 'notification']);
    }

    public function test_mark_all_as_read_returns_ok(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->post(route('notifications.mark-all-as-read', $workspace));

        $response->assertOk();
        $response->assertJsonStructure(['unread_count']);
    }
}
