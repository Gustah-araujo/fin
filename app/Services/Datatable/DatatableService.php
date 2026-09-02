<?php

declare(strict_types=1);

namespace App\Services\Datatable;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DatatableService
{
    public function paginate(Builder|Relation $query, Request $request, DatatableConfig $config): JsonResponse
    {
        if ($query instanceof Relation) {
            $query = $query->getQuery();
        }

        $this->applySearch($query, $request, $config);
        $this->applyFilters($query, $request, $config);
        $this->applySort($query, $request, $config);

        $paginator = $query->paginate($config->perPageValue());

        return response()->json([
            'data' => $config->resourceClass()::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    private function applySearch(Builder $query, Request $request, DatatableConfig $config): void
    {
        $search = $request->input('search');

        if ($search === null || $search === '') {
            return;
        }

        $columns = $config->searchableColumns();

        if ($columns === []) {
            return;
        }

        $query->where(function (Builder $inner) use ($columns, $search): void {
            foreach ($columns as $column) {
                $inner->orWhere($column, 'like', '%'.$search.'%');
            }
        });
    }

    private function applyFilters(Builder $query, Request $request, DatatableConfig $config): void
    {
        foreach ($config->getFilters() as $key => $filter) {
            match ($filter->type) {
                'text', 'relation', 'select' => $this->applyValueFilter($query, $request, $key, $filter),
                'number' => $this->applyNumberRange($query, $request, $key, $filter),
                'date' => $this->applyDateRange($query, $request, $key, $filter),
                default => null,
            };
        }
    }

    private function applyValueFilter(Builder $query, Request $request, string $key, Filter $filter): void
    {
        $value = $request->input($key);

        if ($value === null || $value === '') {
            return;
        }

        if ($filter->type === 'text') {
            $query->where($filter->column, 'like', '%'.$value.'%');

            return;
        }

        if ($filter->type === 'relation') {
            $query->whereHas($filter->relation, function (Builder $inner) use ($filter, $value): void {
                $inner->where($filter->relationColumn, $value);
            });

            return;
        }

        // select
        ($filter->apply)($query, (string) $value);
    }

    private function applyNumberRange(Builder $query, Request $request, string $key, Filter $filter): void
    {
        $min = $request->input($key.'_min');
        $max = $request->input($key.'_max');

        if ($min !== null && $min !== '') {
            $query->where($filter->column, '>=', (float) $min);
        }

        if ($max !== null && $max !== '') {
            $query->where($filter->column, '<=', (float) $max);
        }
    }

    private function applyDateRange(Builder $query, Request $request, string $key, Filter $filter): void
    {
        $from = $request->input($key.'_from');
        $to = $request->input($key.'_to');

        if ($from !== null && $from !== '') {
            $query->whereDate($filter->column, '>=', $from);
        }

        if ($to !== null && $to !== '') {
            $query->whereDate($filter->column, '<=', $to);
        }
    }

    private function applySort(Builder $query, Request $request, DatatableConfig $config): void
    {
        $sortable = $config->sortableColumns();
        $sort = $request->input('sort');
        $direction = strtolower((string) $request->input('direction', ''));
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : '';

        if ($sort === null || ! in_array($sort, $sortable, true)) {
            $query->orderBy($config->defaultSortColumn(), $config->defaultSortDirection());

            return;
        }

        $query->orderBy($sort, $direction === '' ? 'asc' : $direction);
    }
}
