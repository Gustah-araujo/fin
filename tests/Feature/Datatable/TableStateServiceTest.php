<?php

declare(strict_types=1);

namespace Tests\Feature\Datatable;

use App\Services\Datatable\TableStateService;
use Illuminate\Http\Request;
use Tests\TestCase;

class TableStateServiceTest extends TestCase
{
    public function test_save_persists_filters_and_sort_to_session(): void
    {
        $service = new TableStateService;
        $request = Request::create('/test', 'GET');
        $request->setLaravelSession(app('session')->driver());

        $service->save($request, 'transactions', ['category' => 'uuid-here', 'status' => 'paid'], 'date', 'desc');

        $state = $service->restore($request, 'transactions');

        $this->assertSame(['category' => 'uuid-here', 'status' => 'paid'], $state['filters']);
        $this->assertSame('date', $state['sort']);
        $this->assertSame('desc', $state['direction']);
    }

    public function test_restore_returns_saved_state(): void
    {
        $service = new TableStateService;
        $request = Request::create('/test', 'GET');
        $request->setLaravelSession(app('session')->driver());

        $service->save($request, 'incomes', ['origin' => 'recurring'], 'value', 'asc');

        $state = $service->restore($request, 'incomes');

        $this->assertSame(['origin' => 'recurring'], $state['filters']);
        $this->assertSame('value', $state['sort']);
        $this->assertSame('asc', $state['direction']);
    }

    public function test_restore_returns_default_when_no_session(): void
    {
        $service = new TableStateService;
        $request = Request::create('/test', 'GET');
        $request->setLaravelSession(app('session')->driver());

        $state = $service->restore($request, 'transactions');

        $this->assertSame([], $state['filters']);
        $this->assertNull($state['sort']);
        $this->assertSame('asc', $state['direction']);
    }

    public function test_clear_removes_state_from_session(): void
    {
        $service = new TableStateService;
        $request = Request::create('/test', 'GET');
        $request->setLaravelSession(app('session')->driver());

        $service->save($request, 'transactions', ['status' => 'paid'], 'date', 'desc');
        $service->clear($request, 'transactions');

        $state = $service->restore($request, 'transactions');

        $this->assertSame([], $state['filters']);
        $this->assertNull($state['sort']);
        $this->assertSame('asc', $state['direction']);
    }

    public function test_session_key_generates_correct_prefix(): void
    {
        $this->assertSame('datatable.transactions', TableStateService::sessionKey('transactions'));
        $this->assertSame('datatable.incomes', TableStateService::sessionKey('incomes'));
        $this->assertSame('datatable.recurrences', TableStateService::sessionKey('recurrences'));
    }

    public function test_save_cleans_empty_filter_values(): void
    {
        $service = new TableStateService;
        $request = Request::create('/test', 'GET');
        $request->setLaravelSession(app('session')->driver());

        $service->save($request, 'transactions', ['category' => 'uuid-here', 'status' => '', 'account' => null], 'date', 'desc');

        $state = $service->restore($request, 'transactions');

        $this->assertSame(['category' => 'uuid-here'], $state['filters']);
    }

    public function test_state_is_isolated_per_table(): void
    {
        $service = new TableStateService;
        $request = Request::create('/test', 'GET');
        $request->setLaravelSession(app('session')->driver());

        $service->save($request, 'transactions', ['status' => 'paid'], 'date', 'desc');
        $service->save($request, 'incomes', ['origin' => 'recurring'], 'value', 'asc');

        $txState = $service->restore($request, 'transactions');
        $incState = $service->restore($request, 'incomes');

        $this->assertSame(['status' => 'paid'], $txState['filters']);
        $this->assertSame(['origin' => 'recurring'], $incState['filters']);
    }
}
