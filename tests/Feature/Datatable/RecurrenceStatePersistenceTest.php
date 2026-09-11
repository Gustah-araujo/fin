<?php

declare(strict_types=1);

namespace Tests\Feature\Datatable;

use App\Models\Account;
use App\Models\Recurrence;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class RecurrenceStatePersistenceTest extends TestCase
{
    private User $user;

    private Workspace $workspace;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->members()->attach($this->user, ['role' => 'admin']);

        $this->account = Account::factory()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);
    }

    private function createRecurrence(array $overrides = []): Recurrence
    {
        return Recurrence::factory()->create(array_merge([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'created_by' => $this->user->id,
        ], $overrides));
    }

    public function test_index_returns_initial_state_from_session(): void
    {
        // First: hit datatable to save state
        $this->actingAs($this->user)
            ->getJson(route('recurrences.datatable', [
                'workspace' => $this->workspace->uuid,
                'status' => 'active',
                'sort' => 'value',
                'direction' => 'asc',
            ]));

        // Then: hit index and check initialState
        $response = $this->actingAs($this->user)
            ->get(route('recurrences.index', ['workspace' => $this->workspace->uuid]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('initialState')
            ->where('initialState.filters.status', 'active')
            ->where('initialState.sort', 'value')
            ->where('initialState.direction', 'asc')
        );
    }

    public function test_index_returns_default_state_when_no_session(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('recurrences.index', ['workspace' => $this->workspace->uuid]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('initialState')
            ->where('initialState.filters', [])
            ->where('initialState.sort', null)
            ->where('initialState.direction', 'asc')
        );
    }

    public function test_datatable_saves_state_to_session(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('recurrences.datatable', [
                'workspace' => $this->workspace->uuid,
                'status' => 'active',
                'sort' => 'value',
                'direction' => 'asc',
            ]));

        $response->assertOk();

        $sessionData = session('datatable.recurrences');
        $this->assertSame(['status' => 'active'], $sessionData['filters']);
        $this->assertSame('value', $sessionData['sort']);
        $this->assertSame('asc', $sessionData['direction']);
    }

    public function test_datatable_restores_state_when_no_params(): void
    {
        // First request: save state
        $this->actingAs($this->user)
            ->getJson(route('recurrences.datatable', [
                'workspace' => $this->workspace->uuid,
                'status' => 'active',
                'sort' => 'value',
                'direction' => 'asc',
            ]));

        // Second request: no params, should use session state
        $response = $this->actingAs($this->user)
            ->getJson(route('recurrences.datatable', [
                'workspace' => $this->workspace->uuid,
            ]));

        $response->assertOk();

        // Verify session still has the state
        $sessionData = session('datatable.recurrences');
        $this->assertSame(['status' => 'active'], $sessionData['filters']);
    }

    public function test_datatable_request_params_override_session(): void
    {
        // First request: save state
        $this->actingAs($this->user)
            ->getJson(route('recurrences.datatable', [
                'workspace' => $this->workspace->uuid,
                'status' => 'active',
                'sort' => 'value',
                'direction' => 'asc',
            ]));

        // Second request: override
        $response = $this->actingAs($this->user)
            ->getJson(route('recurrences.datatable', [
                'workspace' => $this->workspace->uuid,
                'status' => 'paused',
                'sort' => 'next_date',
                'direction' => 'desc',
            ]));

        $response->assertOk();

        $sessionData = session('datatable.recurrences');
        $this->assertSame(['status' => 'paused'], $sessionData['filters']);
        $this->assertSame('next_date', $sessionData['sort']);
        $this->assertSame('desc', $sessionData['direction']);
    }
}
