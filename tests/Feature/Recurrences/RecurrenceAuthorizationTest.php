<?php

declare(strict_types=1);

namespace Tests\Feature\Recurrences;

use App\Enums\WorkspaceRole;
use App\Models\Account;
use App\Models\Category;
use App\Models\Recurrence;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RecurrenceAuthorizationTest extends TestCase
{
    private User $admin;
    private Workspace $workspace;
    private Recurrence $recurrence;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->members()->attach($this->admin, ['role' => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->admin->id,
        ]);
        $category = Category::factory()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->admin->id,
        ]);

        $this->recurrence = Recurrence::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $this->admin->id,
        ]);
    }

    // ─── Admin ───────────────────────────────────────────────────────

    public function test_admin_can_view_any_recurrences(): void
    {
        $this->assertTrue(
            Gate::forUser($this->admin)->allows('viewAny', [Recurrence::class, $this->workspace])
        );
    }

    public function test_admin_can_view_recurrence(): void
    {
        $this->assertTrue(
            Gate::forUser($this->admin)->allows('view', [$this->recurrence, $this->workspace])
        );
    }

    public function test_admin_can_create_recurrence(): void
    {
        $this->assertTrue(
            Gate::forUser($this->admin)->allows('create', [Recurrence::class, $this->workspace])
        );
    }

    public function test_admin_can_update_recurrence(): void
    {
        $this->assertTrue(
            Gate::forUser($this->admin)->allows('update', [$this->recurrence, $this->workspace])
        );
    }

    public function test_admin_can_delete_recurrence(): void
    {
        $this->assertTrue(
            Gate::forUser($this->admin)->allows('delete', [$this->recurrence, $this->workspace])
        );
    }

    public function test_admin_can_pause_recurrence(): void
    {
        $this->assertTrue(
            Gate::forUser($this->admin)->allows('pause', [$this->recurrence, $this->workspace])
        );
    }

    public function test_admin_can_restore_recurrence(): void
    {
        $this->assertTrue(
            Gate::forUser($this->admin)->allows('restore', [$this->recurrence, $this->workspace])
        );
    }

    public function test_admin_can_generate_now_recurrence(): void
    {
        $this->assertTrue(
            Gate::forUser($this->admin)->allows('generateNow', [$this->recurrence, $this->workspace])
        );
    }

    // ─── Editor ──────────────────────────────────────────────────────

    public function test_editor_can_view_any_recurrences(): void
    {
        $editor = User::factory()->create();
        $this->workspace->members()->attach($editor, ['role' => WorkspaceRole::Editor->value]);

        $this->assertTrue(
            Gate::forUser($editor)->allows('viewAny', [Recurrence::class, $this->workspace])
        );
    }

    public function test_editor_can_view_recurrence(): void
    {
        $editor = User::factory()->create();
        $this->workspace->members()->attach($editor, ['role' => WorkspaceRole::Editor->value]);

        $this->assertTrue(
            Gate::forUser($editor)->allows('view', [$this->recurrence, $this->workspace])
        );
    }

    public function test_editor_can_create_recurrence(): void
    {
        $editor = User::factory()->create();
        $this->workspace->members()->attach($editor, ['role' => WorkspaceRole::Editor->value]);

        $this->assertTrue(
            Gate::forUser($editor)->allows('create', [Recurrence::class, $this->workspace])
        );
    }

    public function test_editor_can_update_recurrence(): void
    {
        $editor = User::factory()->create();
        $this->workspace->members()->attach($editor, ['role' => WorkspaceRole::Editor->value]);

        $this->assertTrue(
            Gate::forUser($editor)->allows('update', [$this->recurrence, $this->workspace])
        );
    }

    public function test_editor_can_delete_recurrence(): void
    {
        $editor = User::factory()->create();
        $this->workspace->members()->attach($editor, ['role' => WorkspaceRole::Editor->value]);

        $this->assertTrue(
            Gate::forUser($editor)->allows('delete', [$this->recurrence, $this->workspace])
        );
    }

    public function test_editor_can_pause_recurrence(): void
    {
        $editor = User::factory()->create();
        $this->workspace->members()->attach($editor, ['role' => WorkspaceRole::Editor->value]);

        $this->assertTrue(
            Gate::forUser($editor)->allows('pause', [$this->recurrence, $this->workspace])
        );
    }

    public function test_editor_can_restore_recurrence(): void
    {
        $editor = User::factory()->create();
        $this->workspace->members()->attach($editor, ['role' => WorkspaceRole::Editor->value]);

        $this->assertTrue(
            Gate::forUser($editor)->allows('restore', [$this->recurrence, $this->workspace])
        );
    }

    public function test_editor_can_generate_now_recurrence(): void
    {
        $editor = User::factory()->create();
        $this->workspace->members()->attach($editor, ['role' => WorkspaceRole::Editor->value]);

        $this->assertTrue(
            Gate::forUser($editor)->allows('generateNow', [$this->recurrence, $this->workspace])
        );
    }

    // ─── Viewer ──────────────────────────────────────────────────────

    public function test_viewer_can_view_any_recurrences(): void
    {
        $viewer = User::factory()->create();
        $this->workspace->members()->attach($viewer, ['role' => WorkspaceRole::Viewer->value]);

        $this->assertTrue(
            Gate::forUser($viewer)->allows('viewAny', [Recurrence::class, $this->workspace])
        );
    }

    public function test_viewer_can_view_recurrence(): void
    {
        $viewer = User::factory()->create();
        $this->workspace->members()->attach($viewer, ['role' => WorkspaceRole::Viewer->value]);

        $this->assertTrue(
            Gate::forUser($viewer)->allows('view', [$this->recurrence, $this->workspace])
        );
    }

    public function test_viewer_cannot_create_recurrence(): void
    {
        $viewer = User::factory()->create();
        $this->workspace->members()->attach($viewer, ['role' => WorkspaceRole::Viewer->value]);

        $this->assertFalse(
            Gate::forUser($viewer)->allows('create', [Recurrence::class, $this->workspace])
        );
    }

    public function test_viewer_cannot_update_recurrence(): void
    {
        $viewer = User::factory()->create();
        $this->workspace->members()->attach($viewer, ['role' => WorkspaceRole::Viewer->value]);

        $this->assertFalse(
            Gate::forUser($viewer)->allows('update', [$this->recurrence, $this->workspace])
        );
    }

    public function test_viewer_cannot_delete_recurrence(): void
    {
        $viewer = User::factory()->create();
        $this->workspace->members()->attach($viewer, ['role' => WorkspaceRole::Viewer->value]);

        $this->assertFalse(
            Gate::forUser($viewer)->allows('delete', [$this->recurrence, $this->workspace])
        );
    }

    public function test_viewer_cannot_pause_recurrence(): void
    {
        $viewer = User::factory()->create();
        $this->workspace->members()->attach($viewer, ['role' => WorkspaceRole::Viewer->value]);

        $this->assertFalse(
            Gate::forUser($viewer)->allows('pause', [$this->recurrence, $this->workspace])
        );
    }

    public function test_viewer_cannot_restore_recurrence(): void
    {
        $viewer = User::factory()->create();
        $this->workspace->members()->attach($viewer, ['role' => WorkspaceRole::Viewer->value]);

        $this->assertFalse(
            Gate::forUser($viewer)->allows('restore', [$this->recurrence, $this->workspace])
        );
    }

    public function test_viewer_cannot_generate_now_recurrence(): void
    {
        $viewer = User::factory()->create();
        $this->workspace->members()->attach($viewer, ['role' => WorkspaceRole::Viewer->value]);

        $this->assertFalse(
            Gate::forUser($viewer)->allows('generateNow', [$this->recurrence, $this->workspace])
        );
    }

    // ─── Cross-workspace ─────────────────────────────────────────────

    public function test_cannot_access_recurrence_from_other_workspace(): void
    {
        $otherUser = User::factory()->create();
        $otherWorkspace = Workspace::factory()->create();
        $otherWorkspace->members()->attach($otherUser, ['role' => WorkspaceRole::Admin->value]);

        $otherAccount = Account::factory()->create([
            'workspace_id' => $otherWorkspace->id,
            'created_by' => $otherUser->id,
        ]);
        $otherCategory = Category::factory()->create([
            'workspace_id' => $otherWorkspace->id,
            'created_by' => $otherUser->id,
        ]);

        $otherRecurrence = Recurrence::factory()->create([
            'workspace_id' => $otherWorkspace->id,
            'account_id' => $otherAccount->id,
            'category_id' => $otherCategory->id,
            'created_by' => $otherUser->id,
        ]);

        // User from workspace A cannot interact with a recurrence from workspace B
        // even when passing workspace A as context.
        $this->assertFalse(
            Gate::forUser($this->admin)->allows('update', [$otherRecurrence, $this->workspace])
        );

        $this->assertFalse(
            Gate::forUser($this->admin)->allows('view', [$otherRecurrence, $this->workspace])
        );
    }
}
