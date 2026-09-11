<?php

declare(strict_types=1);

namespace App\Services\Datatable;

use Illuminate\Http\Request;

final class TableStateService
{
    private const SESSION_PREFIX = 'datatable.';

    /**
     * Save the table state (filters + sort) to the session.
     *
     * @param  array<string, string|null>  $filters
     */
    public function save(
        Request $request,
        string $tableKey,
        array $filters,
        ?string $sort,
        string $direction,
    ): void {
        $cleanFilters = array_filter($filters, fn ($value) => $value !== null && $value !== '');

        $request->session()->put(self::sessionKey($tableKey), [
            'filters' => $cleanFilters,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    /**
     * Restore the table state from the session.
     *
     * @return array{filters: array<string, string>, sort: string|null, direction: string}
     */
    public function restore(Request $request, string $tableKey): array
    {
        $state = $request->session()->get(self::sessionKey($tableKey));

        if ($state === null) {
            return [
                'filters' => [],
                'sort' => null,
                'direction' => 'asc',
            ];
        }

        return [
            'filters' => $state['filters'] ?? [],
            'sort' => $state['sort'] ?? null,
            'direction' => $state['direction'] ?? 'asc',
        ];
    }

    /**
     * Clear the table state from the session.
     */
    public function clear(Request $request, string $tableKey): void
    {
        $request->session()->forget(self::sessionKey($tableKey));
    }

    /**
     * Generate the session key for a table.
     */
    public static function sessionKey(string $tableKey): string
    {
        return self::SESSION_PREFIX.$tableKey;
    }
}
