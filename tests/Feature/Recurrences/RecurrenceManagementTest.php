<?php

declare(strict_types=1);

namespace Tests\Feature\Recurrences;

use App\Enums\WorkspaceRole;
use App\Models\Account;
use App\Models\Category;
use App\Models\Recurrence;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class RecurrenceManagementTest extends TestCase
{
    private User $user;

    private Workspace $workspace;

    private Account $account;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->members()->attach($this->user, ['role' => WorkspaceRole::Admin->value]);

        $this->account = Account::factory()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        $this->category = Category::factory()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
            'type' => 'income',
        ]);
    }

    private function makeRecurrence(array $overrides = []): Recurrence
    {
        return Recurrence::factory()->create(array_merge([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
        ], $overrides));
    }

    public function test_index_lists_recurrences(): void
    {
        $this->makeRecurrence(['description' => 'Salário']);
        $this->makeRecurrence(['description' => 'Freela']);

        $response = $this->actingAs($this->user)
            ->getJson(route('recurrences.datatable', $this->workspace));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_user_can_update_recurrence(): void
    {
        $recurrence = $this->makeRecurrence();

        $this->actingAs($this->user)
            ->put(route('recurrences.update', [$this->workspace, $recurrence]), [
                'value' => 8000,
                'description' => 'Salário Novo',
            ]);

        $recurrence->refresh();

        $this->assertEquals(8000, (float) $recurrence->value);
        $this->assertEquals('Salário Novo', $recurrence->description);
    }

    public function test_user_can_pause_recurrence(): void
    {
        $recurrence = $this->makeRecurrence();

        $this->actingAs($this->user)
            ->post(route('recurrences.pause', [$this->workspace, $recurrence]));

        $this->assertEquals('paused', $recurrence->refresh()->status->value);
    }

    public function test_user_can_restore_recurrence(): void
    {
        $recurrence = $this->makeRecurrence(['status' => 'paused']);

        $this->actingAs($this->user)
            ->post(route('recurrences.restore', [$this->workspace, $recurrence]));

        $this->assertEquals('active', $recurrence->refresh()->status->value);
    }

    public function test_user_can_generate_now(): void
    {
        $recurrence = $this->makeRecurrence([
            'next_date' => now()->format('Y-m-d'),
        ]);

        $this->actingAs($this->user)
            ->post(route('recurrences.generate', [$this->workspace, $recurrence]));

        $this->assertDatabaseHas('transactions', [
            'recurrence_id' => $recurrence->id,
            'type' => 'income',
        ]);
    }

    public function test_user_can_delete_recurrence(): void
    {
        $recurrence = $this->makeRecurrence();

        $this->actingAs($this->user)
            ->delete(route('recurrences.destroy', [$this->workspace, $recurrence]));

        $this->assertNotNull($recurrence->refresh()->deleted_at);
    }

    public function test_viewer_cannot_manage_recurrences(): void
    {
        $viewer = User::factory()->create();
        $this->workspace->members()->attach($viewer, ['role' => WorkspaceRole::Viewer->value]);

        $recurrence = $this->makeRecurrence();

        $this->actingAs($viewer)
            ->post(route('recurrences.pause', [$this->workspace, $recurrence]))
            ->assertForbidden();
    }
}
