<?php

declare(strict_types=1);

namespace App\Services\Datatable;

final class DatatableConfig
{
    private string $resourceClass;

    /** @var string[] */
    private array $searchable = [];

    /** @var array<string, Filter> */
    private array $filters = [];

    /** @var string[] */
    private array $sortable = [];

    private string $defaultSortColumn = '';

    private string $defaultSortDirection = 'asc';

    private int $perPage = 25;

    private function __construct(string $resourceClass)
    {
        $this->resourceClass = $resourceClass;
    }

    public static function make(string $resourceClass): self
    {
        return new self($resourceClass);
    }

    /**
     * @param  string[]  $columns
     */
    public function searchable(array $columns): self
    {
        $this->searchable = $columns;

        return $this;
    }

    public function filter(string $key, Filter $filter): self
    {
        $this->filters[$key] = $filter;

        return $this;
    }

    /**
     * @param  string[]  $columns
     */
    public function sortable(array $columns): self
    {
        $this->sortable = $columns;

        return $this;
    }

    public function defaultSort(string $column, string $direction = 'asc'): self
    {
        $this->defaultSortColumn = $column;
        $this->defaultSortDirection = $direction;

        return $this;
    }

    public function perPage(int $perPage): self
    {
        $this->perPage = $perPage;

        return $this;
    }

    public function resourceClass(): string
    {
        return $this->resourceClass;
    }

    /**
     * @return string[]
     */
    public function searchableColumns(): array
    {
        return $this->searchable;
    }

    public function getFilter(string $key): ?Filter
    {
        return $this->filters[$key] ?? null;
    }

    /**
     * @return array<string, Filter>
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * @return string[]
     */
    public function sortableColumns(): array
    {
        return $this->sortable;
    }

    public function defaultSortColumn(): string
    {
        return $this->defaultSortColumn;
    }

    public function defaultSortDirection(): string
    {
        return $this->defaultSortDirection;
    }

    public function perPageValue(): int
    {
        return $this->perPage;
    }
}
