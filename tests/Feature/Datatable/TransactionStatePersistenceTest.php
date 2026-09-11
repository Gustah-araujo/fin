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

class TransactionStatePersistenceTest extends TestCase
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
            'type' => 'expense',
        ]);
    }

    private function createTransaction(array $overrides = []): Transaction
    {
        return Transaction::factory()->create(array_merge([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'type' => TransactionType::Expense,
        ], $overrides));
    }

    public function test_index_returns_initial_state_from_session(): void
    {
        // First: hit datatable to save state
        $this->actingAs($this->user)
            ->getJson(route('transactions.datatable', [
                'workspace' => $this->workspace->uuid,
                'category' => $this->category->uuid,
                'sort' => 'value',
                'direction' => 'asc',
            ]));

        // Then: hit index and check initialState
        $response = $this->actingAs($this->user)
            ->get(route('transactions.index', ['workspace' => $this->workspace->uuid]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('initialState')
            ->where('initialState.filters.category', $this->category->uuid)
            ->where('initialState.sort', 'value')
            ->where('initialState.direction', 'asc')
        );
    }

    public function test_index_returns_default_state_when_no_session(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('transactions.index', ['workspace' => $this->workspace->uuid]));

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
            ->getJson(route('transactions.datatable', [
                'workspace' => $this->workspace->uuid,
                'category' => $this->category->uuid,
                'sort' => 'value',
                'direction' => 'asc',
            ]));

        $response->assertOk();

        // Verify session has the state
        $sessionData = session('datatable.transactions');
        $this->assertSame(['category' => $this->category->uuid], $sessionData['filters']);
        $this->assertSame('value', $sessionData['sort']);
        $this->assertSame('asc', $sessionData['direction']);
    }

    public function test_datatable_restores_state_when_no_params(): void
    {
        // First request: save state
        $this->actingAs($this->user)
            ->getJson(route('transactions.datatable', [
                'workspace' => $this->workspace->uuid,
                'category' => $this->category->uuid,
                'sort' => 'value',
                'direction' => 'asc',
            ]));

        // Second request: no params, should use session state
        $this->createTransaction(['value' => 100]);
        $this->createTransaction(['value' => 200]);

        $response = $this->actingAs($this->user)
            ->getJson(route('transactions.datatable', [
                'workspace' => $this->workspace->uuid,
            ]));

        $response->assertOk();
        $data = json_decode($response->getContent(), true);

        // All returned transactions should be from the category (filter applied from session)
        foreach ($data['data'] as $item) {
            $this->assertSame($this->category->uuid, $item['category']['uuid']);
        }
    }

    public function test_datatable_request_params_override_session(): void
    {
        // First request: save state with category
        $this->actingAs($this->user)
            ->getJson(route('transactions.datatable', [
                'workspace' => $this->workspace->uuid,
                'category' => $this->category->uuid,
                'sort' => 'value',
                'direction' => 'asc',
            ]));

        // Second request: different category overrides
        $otherCategory = Category::factory()->create([
            'workspace_id' => $this->workspace->id,
            'type' => 'expense',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('transactions.datatable', [
                'workspace' => $this->workspace->uuid,
                'category' => $otherCategory->uuid,
                'sort' => 'date',
                'direction' => 'desc',
            ]));

        $response->assertOk();

        // Verify session was updated
        $sessionData = session('datatable.transactions');
        $this->assertSame(['category' => $otherCategory->uuid], $sessionData['filters']);
        $this->assertSame('date', $sessionData['sort']);
        $this->assertSame('desc', $sessionData['direction']);
    }

    public function test_datatable_partial_params_override_session(): void
    {
        // First request: save state with category + sort
        $this->actingAs($this->user)
            ->getJson(route('transactions.datatable', [
                'workspace' => $this->workspace->uuid,
                'category' => $this->category->uuid,
                'sort' => 'value',
                'direction' => 'asc',
            ]));

        // Second request: only sort provided (no filters)
        $response = $this->actingAs($this->user)
            ->getJson(route('transactions.datatable', [
                'workspace' => $this->workspace->uuid,
                'sort' => 'date',
            ]));

        $response->assertOk();

        // Filters should come from session, sort from request
        $sessionData = session('datatable.transactions');
        $this->assertSame(['category' => $this->category->uuid], $sessionData['filters']);
        $this->assertSame('date', $sessionData['sort']);
    }

    public function test_state_is_isolated_per_table(): void
    {
        // Save state for transactions
        $this->actingAs($this->user)
            ->getJson(route('transactions.datatable', [
                'workspace' => $this->workspace->uuid,
                'category' => $this->category->uuid,
                'sort' => 'value',
                'direction' => 'asc',
            ]));

        // Check incomes session is empty
        $this->assertNull(session('datatable.incomes'));

        // Check transactions session has data
        $this->assertNotNull(session('datatable.transactions'));
    }
}
