import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';

import { Button } from '@/components/ui/button';
import type { DataTableColumn, SortDirection } from '@/types/datatable';

interface DataTableColumnHeaderProps<T> {
    column: DataTableColumn<T>;
    sort: string | null;
    direction: SortDirection;
    onSort(key: string): void;
}

export function DataTableColumnHeader<T>({
    column,
    sort,
    direction,
    onSort,
}: DataTableColumnHeaderProps<T>) {
    const isSorted = sort === column.key;
    const SortIcon = !isSorted
        ? ArrowUpDown
        : direction === 'asc'
          ? ArrowUp
          : ArrowDown;

    if (!column.sortable) {
        return (
            <span className="text-sm font-medium text-center">
                {column.header}
            </span>
        );
    }

    return (
        <Button
            type="button"
            variant="ghost"
            size="sm"
            className="px-2 text-sm font-medium justify-center"
            onClick={() => onSort(column.key)}
        >
            {column.header}
            <SortIcon className="size-3.5" />
        </Button>
    );
}

export default DataTableColumnHeader;
