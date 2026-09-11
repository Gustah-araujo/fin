import type { ReactNode } from 'react';

export interface PaginatedMeta {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    from: number | null;
    to: number | null;
}

export interface Paginated<T> {
    data: T[];
    meta: PaginatedMeta;
}

export type DataTableFilterType = 'text' | 'number' | 'date' | 'select';

export interface SelectOption {
    label: string;
    value: string;
}

export interface DataTableFilter {
    type: DataTableFilterType;
    options?: SelectOption[]; // required for 'select'
    placeholder?: string;
}

export type SortDirection = 'asc' | 'desc';

export interface DataTableColumn<T> {
    key: string; // data field key AND query param key
    header: string; // pt-BR title
    sortable?: boolean;
    filter?: DataTableFilter;
    align?: 'left' | 'right';
    cell?: (row: T) => ReactNode;
}

export interface DataTableParams {
    page: number;
    per_page: number;
    sort: string | null;
    direction: SortDirection;
    filters: Record<string, string>; // includes _min/_max/_from/_to suffixes
}

export interface TableState {
    filters: Record<string, string>;
    sort: string | null;
    direction: SortDirection;
}
