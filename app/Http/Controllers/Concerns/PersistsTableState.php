<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Services\Datatable\DatatableConfig;
use App\Services\Datatable\TableStateService;
use Illuminate\Http\Request;

trait PersistsTableState
{
    /**
     * Resolve consolidated table state: request params > session.
     * Saves the result back to the session.
     *
     * @return array{filters: array<string, string>, sort: string|null, direction: string}
     */
    protected function resolveTableState(
        Request $request,
        string $tableKey,
        DatatableConfig $config,
    ): array {
        $requestFilters = $this->extractFiltersFromRequest($request, $config);
        $requestSort = $request->query('sort');
        $requestDirection = $request->query('direction');

        $sessionState = app(TableStateService::class)->restore($request, $tableKey);

        $filters = ! empty($requestFilters) ? $requestFilters : $sessionState['filters'];
        $sort = $requestSort ?? $sessionState['sort'] ?? null;
        $direction = $requestDirection ?? $sessionState['direction'] ?? 'asc';

        app(TableStateService::class)->save($request, $tableKey, $filters, $sort, $direction);

        return compact('filters', 'sort', 'direction');
    }

    /**
     * Extract filter values from the request, only for keys defined in the config.
     *
     * @return array<string, string>
     */
    private function extractFiltersFromRequest(Request $request, DatatableConfig $config): array
    {
        $filters = [];

        foreach ($config->filterKeys() as $key) {
            $value = $request->query($key);
            if ($value !== null && $value !== '') {
                $filters[$key] = $value;
            }
        }

        return $filters;
    }
}
