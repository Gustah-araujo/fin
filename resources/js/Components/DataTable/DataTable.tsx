import { useMemo, type ReactElement, type ReactNode } from 'react';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

import { useDataTable } from '@/hooks/use-data-table';
import type { DataTableColumn } from '@/types/datatable';
import DataTableColumnHeader from './DataTableColumnHeader';
import DataTableFilterRow from './DataTableFilterRow';
import DataTablePagination from './DataTablePagination';

interface DataTableProps<T> {
    endpoint: string;
    columns: DataTableColumn<T>[];
    initialFilters?: Record<string, string>;
    emptyState: ReactNode;
}

const SKELETON_ROWS = [0, 1, 2, 3, 4];

export function DataTable<T extends { uuid: string }>({
    endpoint,
    columns,
    initialFilters,
    emptyState,
}: DataTableProps<T>): ReactElement {
    const { rows, meta, loading, error, params, setPage, setSort, setFilter } =
        useDataTable<T>(endpoint, { filters: initialFilters });

    const hasAnyFilter = useMemo(
        () => columns.some((column) => column.filter !== undefined),
        [columns],
    );

    return (
        <div className="space-y-4">
            {error && (
                <div className="rounded-md border border-destructive px-4 py-3 text-sm text-destructive">
                    {error}
                </div>
            )}

            <div className="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            {columns.map((column) => (
                                <TableHead
                                    key={`header-${column.key}`}
                                    className={
                                        column.align === 'right'
                                            ? 'text-right'
                                            : undefined
                                    }
                                >
                                    <DataTableColumnHeader
                                        column={column}
                                        sort={params.sort}
                                        direction={params.direction}
                                        onSort={setSort}
                                    />
                                </TableHead>
                            ))}
                        </TableRow>

                        {hasAnyFilter && (
                            <DataTableFilterRow
                                columns={columns}
                                values={params.filters}
                                onChange={setFilter}
                            />
                        )}
                    </TableHeader>

                    <TableBody>
                        {loading && rows.length === 0
                            ? SKELETON_ROWS.map((index) => (
                                  <TableRow key={index}>
                                      {columns.map((column) => (
                                          <TableCell key={column.key}>
                                              <div className="h-4 animate-pulse rounded bg-muted" />
                                          </TableCell>
                                      ))}
                                  </TableRow>
                              ))
                            : null}

                        {!loading && rows.length === 0 ? (
                            <TableRow>
                                <TableCell
                                    colSpan={columns.length}
                                    className="px-2 py-8 text-center"
                                >
                                    {emptyState}
                                </TableCell>
                            </TableRow>
                        ) : null}

                        {rows.map((row) => (
                            <TableRow key={row.uuid}>
                                {columns.map((column) => (
                                    <TableCell
                                        key={column.key}
                                        className={
                                            column.align === 'right'
                                                ? 'text-right'
                                                : undefined
                                        }
                                    >
                                        {column.cell
                                            ? column.cell(row)
                                            : String(
                                                  row[column.key as keyof T],
                                              )}
                                    </TableCell>
                                ))}
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>

            {meta && meta.last_page > 0 && (
                <DataTablePagination meta={meta} onPageChange={setPage} />
            )}
        </div>
    );
}

export default DataTable;
