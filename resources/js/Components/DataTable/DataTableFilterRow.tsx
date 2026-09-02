import { TableHead, TableRow } from '@/components/ui/table';

import type { DataTableColumn } from '@/types/datatable';
import DataTableFilterInput from './DataTableFilterInput';

interface DataTableFilterRowProps<T> {
    columns: DataTableColumn<T>[];
    values: Record<string, string>;
    onChange(key: string, value: string): void;
}

export function DataTableFilterRow<T>({
    columns,
    values,
    onChange,
}: DataTableFilterRowProps<T>) {
    return (
        <TableRow>
            {columns.map((column) => (
                <TableHead
                    key={`filter-${column.key}`}
                    className={
                        column.align === 'right' ? 'text-right' : undefined
                    }
                >
                    {column.filter ? (
                        <DataTableFilterInput
                            columnKey={column.key}
                            filter={column.filter}
                            values={values}
                            onChange={onChange}
                        />
                    ) : null}
                </TableHead>
            ))}
        </TableRow>
    );
}

export default DataTableFilterRow;
