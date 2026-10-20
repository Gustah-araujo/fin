<?php

namespace Tests\Feature\Notifications;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationFeatureTest extends TestCase
{
    public function test_user_can_list_notifications_in_their_workspace(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $this->createNotification($user, $workspace, 'Primeira notificação', unread: true);
        $this->createNotification($user, $workspace, 'Segunda notificação', unread: false);

        $otherWorkspace = Workspace::factory()->create();
        $this->createNotification($user, $otherWorkspace, 'Notificação de outro workspace', unread: true);

        $response = $this->actingAs($user)
            ->get(route('notifications.index', $workspace));

        $response->assertOk();
        $response->assertJsonStructure([
            'unread_count',
            'notifications' => [
                ['id', 'type', 'title', 'message', 'is_read', 'created_at', 'read_at'],
            ],
        ]);
        $response->assertJsonPath('unread_count', 1);
        $this->assertCount(2, $response->json('notifications'));
        $response->assertJsonFragment(['title' => 'Primeira notificação']);
        $response->assertJsonFragment(['title' => 'Segunda notificação']);
        $response->assertJsonMissing(['title' => 'Notificação de outro workspace']);
    }

    public function test_notification_listing_is_scoped_to_workspace(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $otherWorkspace = Workspace::factory()->create();

        $notification = $this->createNotification($user, $workspace, 'Notificação do workspace A', unread: true);
        $otherNotification = $this->createNotification($user, $otherWorkspace, 'Notificação do workspace B', unread: true);

        $response = $this->actingAs($user)
            ->get(route('notifications.index', $workspace));

        $response->assertOk();
        $response->assertJsonPath('unread_count', 1);
        $response->assertJsonPath('notifications.0.id', $notification->id);
        $response->assertJsonMissing(['id' => $otherNotification->id]);
    }

    public function test_user_can_mark_single_notification_as_read(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $notification = $this->createNotification($user, $workspace, 'Marcar como lida', unread: true);

        $response = $this->actingAs($user)
            ->patch(route('notifications.read', [$workspace, $notification]));

        $response->assertOk();
        $response->assertJsonStructure([
            'unread_count',
            'notification' => ['id', 'type', 'title', 'message', 'is_read', 'created_at', 'read_at'],
        ]);
        $response->assertJsonPath('unread_count', 0);
        $response->assertJsonPath('notification.id', $notification->id);
        $response->assertJsonPath('notification.is_read', true);

        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $first = $this->createNotification($user, $workspace, 'Notificação 1', unread: true);
        $second = $this->createNotification($user, $workspace, 'Notificação 2', unread: true);

        $otherWorkspace = Workspace::factory()->create();
        $otherNotification = $this->createNotification($user, $otherWorkspace, 'Outro workspace', unread: true);

        $response = $this->actingAs($user)
            ->post(route('notifications.mark-all-as-read', $workspace));

        $response->assertOk();
        $response->assertJsonStructure(['unread_count']);
        $response->assertJsonPath('unread_count', 0);

        $first->refresh();
        $second->refresh();
        $otherNotification->refresh();

        $this->assertNotNull($first->read_at);
        $this->assertNotNull($second->read_at);
        $this->assertNull($otherNotification->read_at);
    }

    public function test_user_cannot_mark_another_users_notification(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($owner, ['role' => WorkspaceRole::Admin->value]);
        $workspace->members()->attach($otherUser, ['role' => WorkspaceRole::Viewer->value]);

        $notification = $this->createNotification($owner, $workspace, 'Notificação do dono', unread: true);

        $response = $this->actingAs($otherUser)
            ->patch(route('notifications.read', [$workspace, $notification]));

        $response->assertForbidden();

        $notification->refresh();
        $this->assertNull($notification->read_at);
    }

    private function createNotification(User $user, Workspace $workspace, string $title, bool $unread = true): DatabaseNotification
    {
        return DatabaseNotification::create([
            'id' => Str::uuid()->toString(),
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => [
                'title' => $title,
                'message' => 'Test Message',
                'workspace_uuid' => $workspace->uuid,
            ],
            'read_at' => $unread ? null : now(),
        ]);
    }
}
