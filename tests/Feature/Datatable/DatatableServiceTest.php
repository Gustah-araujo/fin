<?php

declare(strict_types=1);

namespace Tests\Feature\Datatable;

use App\Http\Resources\TransactionResource;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Datatable\DatatableConfig;
use App\Services\Datatable\DatatableService;
use App\Services\Datatable\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Tests\TestCase;

class DatatableServiceTest extends TestCase
{
    private Workspace $workspace;

    private Account $account;

    private Category $category;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->userId = $user->id;
        $this->workspace = Workspace::factory()->create();
        $this->workspace->members()->attach($user, ['role' => 'admin']);

        $this->account = Account::factory()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $user->id,
        ]);
        $this->category = Category::factory()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
        ]);
    }

    private function config(array $filters = []): DatatableConfig
    {
        $config = DatatableConfig::make(TransactionResource::class)
            ->searchable(['description'])
            ->sortable(['date', 'value', 'description'])
            ->defaultSort('date', 'desc')
            ->perPage(25);

        foreach ($filters as $key => $filter) {
            $config->filter($key, $filter);
        }

        return $config;
    }

    private function makeRequest(array $params = []): Request
    {
        return Request::create('/datatable', 'GET', $params);
    }

    private function createTransaction(array $overrides = []): Transaction
    {
        $defaults = [
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'created_by' => $this->userId,
        ];

        return Transaction::factory()->create(array_merge($defaults, $overrides));
    }

    private function runPaginate(Builder $query, Request $request, DatatableConfig $config): array
    {
        $response = app(DatatableService::class)->paginate($query, $request, $config);

        return json_decode($response->getContent(), true);
    }

    public function test_search_matches_description_case_insensitively(): void
    {
        $this->createTransaction(['description' => 'Mercado Extra']);
        $this->createTransaction(['description' => 'Padaria Pão Doce']);

        $query = $this->workspace->transactions()->getQuery();
        $data = $this->runPaginate($query, $this->makeRequest(['search' => 'MERCADO']), $this->config());

        $this->assertSame(1, $data['meta']['total']);
        $this->assertSame('Mercado Extra', $data['data'][0]['description']);
    }

    public function test_search_empty_or_absent_applies_no_filtering(): void
    {
        $this->createTransaction(['description' => 'Mercado']);
        $this->createTransaction(['description' => 'Padaria']);

        $query = $this->workspace->transactions()->getQuery();
        $data = $this->runPaginate($query, $this->makeRequest(), $this->config());

        $this->assertSame(2, $data['meta']['total']);
    }

    public function test_number_range_applies_only_min(): void
    {
        $this->createTransaction(['value' => 100]);
        $this->createTransaction(['value' => 200]);
        $this->createTransaction(['value' => 300]);

        $filters = ['value' => Filter::numberRange('value')];
        $query = $this->workspace->transactions()->getQuery();
        $data = $this->runPaginate($query, $this->makeRequest(['value_min' => 150]), $this->config($filters));

        $this->assertSame(2, $data['meta']['total']);
    }

    public function test_number_range_applies_only_max(): void
    {
        $this->createTransaction(['value' => 100]);
        $this->createTransaction(['value' => 200]);
        $this->createTransaction(['value' => 300]);

        $filters = ['value' => Filter::numberRange('value')];
        $query = $this->workspace->transactions()->getQuery();
        $data = $this->runPaginate($query, $this->makeRequest(['value_max' => 250]), $this->config($filters));

        $this->assertSame(2, $data['meta']['total']);
    }

    public function test_number_range_applies_both_min_and_max(): void
    {
        $this->createTransaction(['value' => 100]);
        $this->createTransaction(['value' => 200]);
        $this->createTransaction(['value' => 300]);

        $filters = ['value' => Filter::numberRange('value')];
        $query = $this->workspace->transactions()->getQuery();
        $data = $this->runPaginate($query, $this->makeRequest(['value_min' => 150, 'value_max' => 250]), $this->config($filters));

        $this->assertSame(1, $data['meta']['total']);
    }

    public function test_date_range_applies_from_and_to_via_where_date(): void
    {
        $this->createTransaction(['date' => '2026-01-01']);
        $this->createTransaction(['date' => '2026-06-15']);
        $this->createTransaction(['date' => '2026-12-31']);

        $filters = ['date' => Filter::dateRange('date')];
        $config = $this->config($filters)->defaultSort('date', 'asc');

        $query = $this->workspace->transactions()->getQuery();
        $data = $this->runPaginate($query, $this->makeRequest(['date_from' => '2026-03-01', 'date_to' => '2026-09-30']), $config);

        $this->assertSame(1, $data['meta']['total']);
    }

    public function test_relation_filter_resolves_by_uuid(): void
    {
        $categoryB = Category::factory()->create([
            'workspace_id' => $this->workspace->id,
            'type' => 'expense',
            'name' => 'Transporte',
        ]);

        $this->createTransaction(['category_id' => $this->category->id]);
        $this->createTransaction(['category_id' => $this->category->id]);
        $this->createTransaction(['category_id' => $categoryB->id]);

        $filters = ['category' => Filter::relation('category', 'uuid')];
        $query = $this->workspace->transactions()->with('category')->getQuery();
        $data = $this->runPaginate($query, $this->makeRequest(['category' => $this->category->uuid]), $this->config($filters));

        $this->assertSame(2, $data['meta']['total']);
        foreach ($data['data'] as $item) {
            $this->assertSame($this->category->uuid, $item['category']['uuid']);
        }
    }

    public function test_select_filter_status_paid_via_closure(): void
    {
        $this->createTransaction(['paid_at' => now()]);
        $this->createTransaction(['paid_at' => now()]);
        $this->createTransaction(['paid_at' => null]);

        $filters = ['status' => Filter::select(
            fn (Builder $q, string $v) => $v === 'paid' ? $q->whereNotNull('paid_at') : $q->whereNull('paid_at')
        )];
        $config = $this->config($filters);

        $query = $this->workspace->transactions()->getQuery();
        $paidData = $this->runPaginate($query->clone(), $this->makeRequest(['status' => 'paid']), $config);
        $this->assertSame(2, $paidData['meta']['total']);

        $query2 = $this->workspace->transactions()->getQuery();
        $unpaidData = $this->runPaginate($query2, $this->makeRequest(['status' => 'unpaid']), $config);
        $this->assertSame(1, $unpaidData['meta']['total']);
    }

    public function test_multiple_filters_combine_with_and(): void
    {
        $categoryB = Category::factory()->create([
            'workspace_id' => $this->workspace->id,
            'type' => 'expense',
            'name' => 'Transporte',
        ]);

        $this->createTransaction(['category_id' => $this->category->id, 'value' => 100, 'paid_at' => now()]);
        $this->createTransaction(['category_id' => $this->category->id, 'value' => 200, 'paid_at' => now()]);
        $this->createTransaction(['category_id' => $categoryB->id, 'value' => 200, 'paid_at' => now()]);
        $this->createTransaction(['category_id' => $this->category->id, 'value' => 200, 'paid_at' => null]);

        $filters = [
            'category' => Filter::relation('category', 'uuid'),
            'value' => Filter::numberRange('value'),
            'status' => Filter::select(
                fn (Builder $q, string $v) => $v === 'paid' ? $q->whereNotNull('paid_at') : $q->whereNull('paid_at')
            ),
        ];
        $query = $this->workspace->transactions()->getQuery();
        $data = $this->runPaginate($query, $this->makeRequest([
            'category' => $this->category->uuid,
            'value_min' => 150,
            'status' => 'paid',
        ]), $this->config($filters));

        $this->assertSame(1, $data['meta']['total']);
    }

    public function test_valid_sort_applies_order(): void
    {
        $this->createTransaction(['value' => 100]);
        $this->createTransaction(['value' => 300]);
        $this->createTransaction(['value' => 200]);

        $query = $this->workspace->transactions()->getQuery();
        $data = $this->runPaginate($query, $this->makeRequest(['sort' => 'value', 'direction' => 'asc']), $this->config());

        $this->assertSame([100, 200, 300], array_column($data['data'], 'value'));
    }

    public function test_invalid_sort_is_ignored_and_falls_back_to_default(): void
    {
        $this->createTransaction(['value' => 100, 'date' => '2026-01-01']);
        $this->createTransaction(['value' => 300, 'date' => '2026-03-01']);
        $this->createTransaction(['value' => 200, 'date' => '2026-02-01']);

        $query = $this->workspace->transactions()->getQuery();
        $data = $this->runPaginate($query, $this->makeRequest(['sort' => 'id_not_sortable']), $this->config());

        $this->assertSame(['2026-03-01', '2026-02-01', '2026-01-01'], array_column($data['data'], 'date'));
    }

    public function test_paginates_at_25_with_correct_meta(): void
    {
        Transaction::factory()->count(30)->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'created_by' => $this->userId,
        ]);

        $query = $this->workspace->transactions()->getQuery();
        $data = $this->runPaginate($query, $this->makeRequest(), $this->config());

        $this->assertSame(1, $data['meta']['current_page']);
        $this->assertSame(25, $data['meta']['per_page']);
        $this->assertSame(30, $data['meta']['total']);
        $this->assertSame(2, $data['meta']['last_page']);
        $this->assertSame(1, $data['meta']['from']);
        $this->assertSame(25, $data['meta']['to']);
        $this->assertCount(25, $data['data']);
    }

    public function test_response_items_use_resource_shape(): void
    {
        $transaction = $this->createTransaction();

        $query = $this->workspace->transactions()->with('category')->getQuery();
        $data = $this->runPaginate($query, $this->makeRequest(), $this->config());

        $this->assertSame($transaction->uuid, $data['data'][0]['uuid']);
    }

    public function test_default_direction_is_asc_when_missing(): void
    {
        $this->createTransaction(['value' => 100]);
        $this->createTransaction(['value' => 300]);
        $this->createTransaction(['value' => 200]);

        $config = $this->config()->defaultSort('value', 'desc');

        $query = $this->workspace->transactions()->getQuery();
        $data = $this->runPaginate($query, $this->makeRequest(['sort' => 'value']), $config);

        $this->assertSame([100, 200, 300], array_column($data['data'], 'value'));
    }
}
