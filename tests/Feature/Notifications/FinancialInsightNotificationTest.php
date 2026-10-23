<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\Workspace\FinancialInsightNotification;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class FinancialInsightNotificationTest extends TestCase
{
    private User $user;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->members()->attach($this->user, ['role' => WorkspaceRole::Admin->value]);
    }

    public function test_notification_uses_database_channel(): void
    {
        $notification = new FinancialInsightNotification($this->workspace, 2);

        $this->assertSame(['database'], $notification->via($this->user));
    }

    public function test_to_database_conforms_to_contract(): void
    {
        $payload = (new FinancialInsightNotification($this->workspace, 3))->toDatabase($this->user);

        $this->assertArrayHasKey('workspace_uuid', $payload);
        $this->assertArrayHasKey('title', $payload);
        $this->assertArrayHasKey('message', $payload);
        $this->assertArrayHasKey('action_url', $payload);
        $this->assertArrayHasKey('action_text', $payload);
        $this->assertSame($this->workspace->uuid, $payload['workspace_uuid']);
    }

    public function test_notifying_user_persists_database_notification(): void
    {
        $this->user->notify(new FinancialInsightNotification($this->workspace, 3));

        $notification = DatabaseNotification::where('notifiable_id', $this->user->id)->first();

        $this->assertNotNull($notification);
        $this->assertSame($this->workspace->uuid, $notification->data['workspace_uuid']);
        $this->assertSame('Análise financeira concluída', $notification->data['title']);
    }
}
