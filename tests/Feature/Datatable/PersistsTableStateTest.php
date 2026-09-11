<?php

declare(strict_types=1);

namespace Tests\Feature\Datatable;

use App\Http\Controllers\Concerns\PersistsTableState;
use App\Http\Resources\TransactionResource;
use App\Services\Datatable\DatatableConfig;
use App\Services\Datatable\Filter;
use Illuminate\Http\Request;
use Tests\TestCase;

class TestableController
{
    use PersistsTableState;

    public function publicResolveTableState(
        Request $request,
        string $tableKey,
        DatatableConfig $config,
    ): array {
        return $this->resolveTableState($request, $tableKey, $config);
    }
}

class PersistsTableStateTest extends TestCase
{
    private function config(): DatatableConfig
    {
        return DatatableConfig::make(TransactionResource::class)
            ->filter('category', Filter::relation('category', 'uuid'))
            ->filter('status', Filter::select(fn ($q, $v) => null))
            ->sortable(['date', 'value'])
            ->defaultSort('date', 'desc');
    }

    private function makeRequest(array $params = []): Request
    {
        return Request::create('/test', 'GET', $params);
    }

    public function test_resolve_uses_session_when_no_request_params(): void
    {
        $controller = new TestableController;
        $config = $this->config();

        // First request: save state to session
        $request1 = $this->makeRequest(['category' => 'uuid-cat', 'sort' => 'value', 'direction' => 'asc']);
        $request1->setLaravelSession(app('session')->driver());
        $controller->publicResolveTableState($request1, 'transactions', $config);

        // Second request: no params, should restore from session
        $request2 = $this->makeRequest();
        $request2->setLaravelSession(app('session')->driver());
        $state = $controller->publicResolveTableState($request2, 'transactions', $config);

        $this->assertSame(['category' => 'uuid-cat'], $state['filters']);
        $this->assertSame('value', $state['sort']);
        $this->assertSame('asc', $state['direction']);
    }

    public function test_resolve_request_params_override_session(): void
    {
        $controller = new TestableController;
        $config = $this->config();

        // First request: save state
        $request1 = $this->makeRequest(['category' => 'uuid-old', 'sort' => 'value', 'direction' => 'asc']);
        $request1->setLaravelSession(app('session')->driver());
        $controller->publicResolveTableState($request1, 'transactions', $config);

        // Second request: explicit params override session
        $request2 = $this->makeRequest(['category' => 'uuid-new', 'sort' => 'date', 'direction' => 'desc']);
        $request2->setLaravelSession(app('session')->driver());
        $state = $controller->publicResolveTableState($request2, 'transactions', $config);

        $this->assertSame(['category' => 'uuid-new'], $state['filters']);
        $this->assertSame('date', $state['sort']);
        $this->assertSame('desc', $state['direction']);
    }

    public function test_resolve_partial_request_overrides_only_provided(): void
    {
        $controller = new TestableController;
        $config = $this->config();

        // First request: save state with category filter + sort
        $request1 = $this->makeRequest(['category' => 'uuid-cat', 'status' => 'paid', 'sort' => 'value', 'direction' => 'asc']);
        $request1->setLaravelSession(app('session')->driver());
        $controller->publicResolveTableState($request1, 'transactions', $config);

        // Second request: only sort provided (no filters), should keep category from session
        $request2 = $this->makeRequest(['sort' => 'date']);
        $request2->setLaravelSession(app('session')->driver());
        $state = $controller->publicResolveTableState($request2, 'transactions', $config);

        // No explicit filters in request → use session filters
        $this->assertSame(['category' => 'uuid-cat', 'status' => 'paid'], $state['filters']);
        // Sort from request takes priority
        $this->assertSame('date', $state['sort']);
    }

    public function test_resolve_saves_consolidated_state_to_session(): void
    {
        $controller = new TestableController;
        $config = $this->config();

        $request = $this->makeRequest(['category' => 'uuid-cat', 'sort' => 'value', 'direction' => 'asc']);
        $request->setLaravelSession(app('session')->driver());
        $controller->publicResolveTableState($request, 'transactions', $config);

        $sessionData = $request->session()->get('datatable.transactions');

        $this->assertSame(['category' => 'uuid-cat'], $sessionData['filters']);
        $this->assertSame('value', $sessionData['sort']);
        $this->assertSame('asc', $sessionData['direction']);
    }

    public function test_resolve_ignores_filters_not_in_config(): void
    {
        $controller = new TestableController;
        $config = $this->config();

        $request = $this->makeRequest(['unknown_filter' => 'value', 'category' => 'uuid-cat']);
        $request->setLaravelSession(app('session')->driver());
        $state = $controller->publicResolveTableState($request, 'transactions', $config);

        $this->assertSame(['category' => 'uuid-cat'], $state['filters']);
        $this->assertArrayNotHasKey('unknown_filter', $state['filters']);
    }

    public function test_filter_keys_returns_config_filter_keys(): void
    {
        $config = $this->config();

        $keys = $config->filterKeys();

        $this->assertSame(['category', 'status'], $keys);
    }
}
