<?php

namespace Tests\Feature\Workspace;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\Workspace\NewInvite;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InviteEmailTest extends TestCase
{
    public function test_invite_sends_email_notification(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $target = User::factory()->create(['email' => 'invited@example.com']);
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($admin, ['role' => WorkspaceRole::Admin->value]);

        $this->actingAs($admin)->post("/w/{$workspace->uuid}/invites", [
            'email' => 'invited@example.com',
            'role' => 'editor',
        ]);

        Notification::assertSentTo($target, NewInvite::class);
    }

    public function test_invite_to_nonexistent_user_does_not_send_email(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($admin, ['role' => WorkspaceRole::Admin->value]);

        $this->actingAs($admin)->post("/w/{$workspace->uuid}/invites", [
            'email' => 'nonexistent@example.com',
            'role' => 'editor',
        ]);

        Notification::assertNothingSent();
    }

    public function test_invite_email_contains_correct_workspace_name(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $target = User::factory()->create(['email' => 'invited@example.com']);
        $workspace = Workspace::factory()->create(['name' => 'Empresa XYZ']);
        $workspace->members()->attach($admin, ['role' => WorkspaceRole::Admin->value]);

        $this->actingAs($admin)->post("/w/{$workspace->uuid}/invites", [
            'email' => 'invited@example.com',
            'role' => 'editor',
        ]);

        Notification::assertSentTo($target, NewInvite::class, function ($notification) use ($target) {
            $mail = $notification->toMail($target);

            return str_contains($mail->subject, 'Empresa XYZ');
        });
    }

    public function test_duplicate_invite_does_not_send_second_email(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $target = User::factory()->create(['email' => 'invited@example.com']);
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($admin, ['role' => WorkspaceRole::Admin->value]);

        // First invite
        $this->actingAs($admin)->post("/w/{$workspace->uuid}/invites", [
            'email' => 'invited@example.com',
            'role' => 'editor',
        ]);

        // Second invite (duplicate - returns existing, doesn't dispatch event)
        $this->actingAs($admin)->post("/w/{$workspace->uuid}/invites", [
            'email' => 'invited@example.com',
            'role' => 'editor',
        ]);

        Notification::assertSentTimes(NewInvite::class, 1);
    }
}
