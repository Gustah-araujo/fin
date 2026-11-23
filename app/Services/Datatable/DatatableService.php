<?php

declare(strict_types=1);

namespace App\Services\Datatable;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DatatableService
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>|Relation<TModel, Model, mixed>  $query
     */
    public function paginate(Builder|Relation $query, Request $request, DatatableConfig $config, ?Closure $totals = null): JsonResponse
    {
        if ($query instanceof Relation) {
            $query = $query->getQuery();
        }

        $this->applySearch($query, $request, $config);
        $this->applyFilters($query, $request, $config);
        $this->applySort($query, $request, $config);

        $totalsData = $totals !== null
            ? $totals((clone $query)->reorder())
            : null;

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
                'totals' => $totalsData,
            ],
        ]);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    private function applySearch(Builder $query, Request $request, DatatableConfig $config): void
    {
        $search = $request->input('search');

        if (! is_string($search) || $search === '') {
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

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
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

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    private function applyValueFilter(Builder $query, Request $request, string $key, Filter $filter): void
    {
        $value = $request->input($key);

        if ($value === null || $value === '') {
            return;
        }

        if ($filter->type === 'text') {
            $this->applyTextFilter($query, $filter, $value);

            return;
        }

        if ($filter->type === 'relation') {
            $this->applyRelationFilter($query, $filter, $value);

            return;
        }

        // select
        $apply = $filter->apply;

        if ($apply instanceof Closure) {
            $apply($query, self::stringValue($value));
        }
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    private function applyTextFilter(Builder $query, Filter $filter, mixed $value): void
    {
        $column = $filter->column;

        if ($column !== null && is_string($value)) {
            $query->where($column, 'like', '%'.$value.'%');
        }
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    private function applyRelationFilter(Builder $query, Filter $filter, mixed $value): void
    {
        $relation = $filter->relation;
        $relationColumn = $filter->relationColumn;

        if ($relation !== null && $relationColumn !== null) {
            $query->whereHas($relation, function (Builder $inner) use ($relationColumn, $value): void {
                $inner->where($relationColumn, $value);
            });
        }
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    private function applyNumberRange(Builder $query, Request $request, string $key, Filter $filter): void
    {
        $column = $filter->column;
        $min = $request->input($key.'_min');
        $max = $request->input($key.'_max');

        if ($column === null) {
            return;
        }

        if ($min !== null && $min !== '' && is_numeric($min)) {
            $query->where($column, '>=', (float) $min);
        }

        if ($max !== null && $max !== '' && is_numeric($max)) {
            $query->where($column, '<=', (float) $max);
        }
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    private function applyDateRange(Builder $query, Request $request, string $key, Filter $filter): void
    {
        $column = $filter->column;
        $from = $request->input($key.'_from');
        $to = $request->input($key.'_to');

        if ($column === null) {
            return;
        }

        if ($from !== null && $from !== '' && is_string($from)) {
            $query->whereDate($column, '>=', $from);
        }

        if ($to !== null && $to !== '' && is_string($to)) {
            $query->whereDate($column, '<=', $to);
        }
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    private function applySort(Builder $query, Request $request, DatatableConfig $config): void
    {
        $sortable = $config->sortableColumns();
        $sort = $request->input('sort');
        $directionInput = $request->input('direction', '');
        $direction = is_string($directionInput) ? strtolower($directionInput) : '';
        $direction = match ($direction) {
            'asc', 'desc' => $direction,
            default => '',
        };

        if (! is_string($sort) || ! in_array($sort, $sortable, true)) {
            $defaultDirection = $config->defaultSortDirection();

            $query->orderBy($config->defaultSortColumn(), $defaultDirection === 'desc' ? 'desc' : 'asc');

            return;
        }

        $query->orderBy($sort, $direction === 'desc' ? 'desc' : 'asc');
    }

    private static function stringValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return is_numeric($value) ? (string) $value : '';
    }
}
