<?php

declare(strict_types=1);

namespace App\Services\Datatable;

use Closure;

final class Filter
{
    private function __construct(
        public readonly string $type,
        public readonly ?string $column,
        public readonly ?string $relation,
        public readonly ?string $relationColumn,
        public readonly ?Closure $apply,
    ) {}

    public static function text(string $column): self
    {
        return new self('text', $column, null, null, null);
    }

    public static function numberRange(string $column): self
    {
        return new self('number', $column, null, null, null);
    }

    public static function dateRange(string $column): self
    {
        return new self('date', $column, null, null, null);
    }

    public static function relation(string $relation, string $relationColumn): self
    {
        return new self('relation', null, $relation, $relationColumn, null);
    }

    public static function select(Closure $apply): self
    {
        return new self('select', null, null, null, $apply);
    }
}
