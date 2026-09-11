<?php

declare(strict_types=1);

namespace Tests\Feature\Datatable;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class IncomeStatePersistenceTest extends TestCase
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
        $this->workspace->members()->attach($this->user, ['role' => 'admin']);

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

    private function createIncome(array $overrides = []): Transaction
    {
        return Transaction::factory()->create(array_merge([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'type' => TransactionType::Income,
        ], $overrides));
    }

    public function test_index_returns_initial_state_from_session(): void
    {
        // First: hit datatable to save state
        $this->actingAs($this->user)
            ->getJson(route('incomes.datatable', [
                'workspace' => $this->workspace->uuid,
                'origin' => 'recurring',
                'sort' => 'value',
                'direction' => 'asc',
            ]));

        // Then: hit index and check initialState
        $response = $this->actingAs($this->user)
            ->get(route('incomes.index', ['workspace' => $this->workspace->uuid]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('initialState')
            ->where('initialState.filters.origin', 'recurring')
            ->where('initialState.sort', 'value')
            ->where('initialState.direction', 'asc')
        );
    }

    public function test_index_returns_default_state_when_no_session(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('incomes.index', ['workspace' => $this->workspace->uuid]));

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
            ->getJson(route('incomes.datatable', [
                'workspace' => $this->workspace->uuid,
                'origin' => 'recurring',
                'sort' => 'value',
                'direction' => 'asc',
            ]));

        $response->assertOk();

        $sessionData = session('datatable.incomes');
        $this->assertSame(['origin' => 'recurring'], $sessionData['filters']);
        $this->assertSame('value', $sessionData['sort']);
        $this->assertSame('asc', $sessionData['direction']);
    }

    public function test_datatable_restores_state_when_no_params(): void
    {
        // First request: save state
        $this->actingAs($this->user)
            ->getJson(route('incomes.datatable', [
                'workspace' => $this->workspace->uuid,
                'origin' => 'recurring',
                'sort' => 'value',
                'direction' => 'asc',
            ]));

        // Second request: no params, should use session state
        $response = $this->actingAs($this->user)
            ->getJson(route('incomes.datatable', [
                'workspace' => $this->workspace->uuid,
            ]));

        $response->assertOk();

        // Verify session still has the state
        $sessionData = session('datatable.incomes');
        $this->assertSame(['origin' => 'recurring'], $sessionData['filters']);
    }

    public function test_datatable_request_params_override_session(): void
    {
        // First request: save state
        $this->actingAs($this->user)
            ->getJson(route('incomes.datatable', [
                'workspace' => $this->workspace->uuid,
                'origin' => 'recurring',
                'sort' => 'value',
                'direction' => 'asc',
            ]));

        // Second request: override
        $response = $this->actingAs($this->user)
            ->getJson(route('incomes.datatable', [
                'workspace' => $this->workspace->uuid,
                'origin' => 'single',
                'sort' => 'date',
                'direction' => 'desc',
            ]));

        $response->assertOk();

        $sessionData = session('datatable.incomes');
        $this->assertSame(['origin' => 'single'], $sessionData['filters']);
        $this->assertSame('date', $sessionData['sort']);
        $this->assertSame('desc', $sessionData['direction']);
    }

    public function test_origin_filter_persists_correctly(): void
    {
        // Save with origin filter
        $this->actingAs($this->user)
            ->getJson(route('incomes.datatable', [
                'workspace' => $this->workspace->uuid,
                'origin' => 'recurring',
            ]));

        // Verify it persists
        $sessionData = session('datatable.incomes');
        $this->assertSame(['origin' => 'recurring'], $sessionData['filters']);

        // Clear and verify single works too
        $this->actingAs($this->user)
            ->getJson(route('incomes.datatable', [
                'workspace' => $this->workspace->uuid,
                'origin' => 'single',
            ]));

        $sessionData = session('datatable.incomes');
        $this->assertSame(['origin' => 'single'], $sessionData['filters']);
    }
}
