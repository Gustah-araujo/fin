import { useCallback, useEffect, useRef, useState } from 'react';
import axios from 'axios';

import { getJson } from '@/lib/api';
import type {
    DataTableParams,
    Paginated,
    PaginatedMeta,
    SortDirection,
    TableState,
} from '@/types/datatable';

const DEBOUNCE_MS = 300;
const DEFAULT_PER_PAGE = 25;

export interface UseDataTableResult<T> {
    rows: T[];
    meta: PaginatedMeta | null;
    loading: boolean;
    error: string | null;
    params: DataTableParams;
    setPage(page: number): void;
    setSort(key: string): void;
    setFilter(key: string, value: string): void;
    clearFilters(): void;
    reload(): void;
}

export function buildQueryParams(
    params: DataTableParams,
): Record<string, unknown> {
    const query: Record<string, unknown> = {
        page: params.page,
        per_page: params.per_page,
    };

    if (params.sort) {
        query.sort = params.sort;
        query.direction = params.direction;
    }

    for (const [key, value] of Object.entries(params.filters)) {
        if (value !== '') {
            query[key] = value;
        }
    }

    return query;
}

export function useDataTable<T>(
    endpoint: string,
    initialState?: TableState,
    onStateChange?: (state: DataTableParams) => void,
): UseDataTableResult<T> {
    const [rows, setRows] = useState<T[]>([]);
    const [meta, setMeta] = useState<PaginatedMeta | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [reloadNonce, setReloadNonce] = useState(0);

    const [params, setParams] = useState<DataTableParams>(() => ({
        page: 1,
        per_page: DEFAULT_PER_PAGE,
        sort: initialState?.sort ?? null,
        direction: initialState?.direction ?? 'asc',
        filters: initialState?.filters ?? {},
    }));

    // Track whether this is the initial mount
    const isInitialMount = useRef(true);

    // Debounced onStateChange callback
    useEffect(() => {
        // Skip on initial mount
        if (isInitialMount.current) {
            isInitialMount.current = false;
            return;
        }

        if (!onStateChange) {
            return;
        }

        const timer = window.setTimeout(() => {
            onStateChange(params);
        }, DEBOUNCE_MS);

        return () => window.clearTimeout(timer);
    }, [params, onStateChange]);

    useEffect(() => {
        let active = true;
        const controller = new AbortController();

        setLoading(true);
        setError(null);

        const timer = window.setTimeout(async () => {
            try {
                const data = await getJson<Paginated<T>>(
                    endpoint,
                    buildQueryParams(params),
                    controller.signal,
                );

                if (!active) {
                    return;
                }

                setRows(data.data);
                setMeta(data.meta);
                setLoading(false);
            } catch (err) {
                if (!active) {
                    return;
                }

                if (axios.isCancel(err)) {
                    return;
                }

                setError(
                    err instanceof Error
                        ? err.message
                        : 'Não foi possível carregar os dados.',
                );
                setLoading(false);
            }
        }, DEBOUNCE_MS);

        return () => {
            active = false;
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [endpoint, params, reloadNonce]);

    function setPage(page: number): void {
        setParams((prev) => ({ ...prev, page }));
    }

    function setSort(key: string): void {
        setParams((prev) => {
            const direction: SortDirection =
                prev.sort === key && prev.direction === 'asc' ? 'desc' : 'asc';

            return {
                ...prev,
                page: 1,
                sort: key,
                direction,
            };
        });
    }

    function setFilter(key: string, value: string): void {
        setParams((prev) => {
            const filters = { ...prev.filters };

            if (value === '') {
                delete filters[key];
            } else {
                filters[key] = value;
            }

            return { ...prev, page: 1, filters };
        });
    }

    function clearFilters(): void {
        setParams((prev) => ({ ...prev, page: 1, filters: {} }));
    }

    const reload = useCallback((): void => {
        setReloadNonce((nonce) => nonce + 1);
    }, []);

    return {
        rows,
        meta,
        loading,
        error,
        params,
        setPage,
        setSort,
        setFilter,
        clearFilters,
        reload,
    };
}
