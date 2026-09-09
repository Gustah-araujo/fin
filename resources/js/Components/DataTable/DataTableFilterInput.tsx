import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

import type { DataTableFilter } from '@/types/datatable';

interface DataTableFilterInputProps {
    columnKey: string;
    filter: DataTableFilter;
    values: Record<string, string>;
    onChange(key: string, value: string): void;
}

function currentValue(
    columnKey: string,
    values: Record<string, string>,
): string {
    return values[columnKey] ?? '';
}

function rangeValue(
    columnKey: string,
    suffix: string,
    values: Record<string, string>,
): string {
    return values[`${columnKey}${suffix}`] ?? '';
}

export function DataTableFilterInput({
    columnKey,
    filter,
    values,
    onChange,
}: DataTableFilterInputProps) {
    switch (filter.type) {
        case 'text':
            return (
                <Input
                    type="text"
                    placeholder={filter.placeholder ?? 'Filtrar…'}
                    value={currentValue(columnKey, values)}
                    onChange={(event) =>
                        onChange(columnKey, event.target.value)
                    }
                    className="h-8 text-xs"
                />
            );

        case 'number': {
            const minValue = rangeValue(columnKey, '_min', values);

            return (
                <div className="flex w-full flex-col gap-1">
                    <Input
                        type="number"
                        placeholder="Mín"
                        value={minValue}
                        onChange={(event) =>
                            onChange(`${columnKey}_min`, event.target.value)
                        }
                        className="h-8 w-full text-xs"
                    />
                    {minValue !== '' && (
                        <Input
                            type="number"
                            placeholder="Máx"
                            value={rangeValue(columnKey, '_max', values)}
                            onChange={(event) =>
                                onChange(`${columnKey}_max`, event.target.value)
                            }
                            className="h-8 w-full text-xs"
                        />
                    )}
                </div>
            );
        }

        case 'date': {
            const fromValue = rangeValue(columnKey, '_from', values);

            return (
                <div className="flex w-full flex-col gap-1">
                    <div className="flex items-center gap-1">
                        <span className="shrink-0 text-[10px] text-muted-foreground">
                            De
                        </span>
                        <Input
                            type="date"
                            value={fromValue}
                            onChange={(event) =>
                                onChange(
                                    `${columnKey}_from`,
                                    event.target.value,
                                )
                            }
                            className="h-8 min-w-0 flex-1 text-xs"
                        />
                    </div>
                    {fromValue !== '' && (
                        <div className="flex items-center gap-1">
                            <span className="shrink-0 text-[10px] text-muted-foreground">
                                Até
                            </span>
                            <Input
                                type="date"
                                value={rangeValue(columnKey, '_to', values)}
                                onChange={(event) =>
                                    onChange(
                                        `${columnKey}_to`,
                                        event.target.value,
                                    )
                                }
                                className="h-8 min-w-0 flex-1 text-xs"
                            />
                        </div>
                    )}
                </div>
            );
        }

        case 'select': {
            const value = currentValue(columnKey, values);

            return (
                <Select
                    value={value}
                    onValueChange={(next) => onChange(columnKey, next)}
                >
                    <SelectTrigger className="h-8 text-xs">
                        <SelectValue
                            placeholder={filter.placeholder ?? 'Filtrar…'}
                        />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="">Todos</SelectItem>
                        {filter.options?.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            );
        }
    }
}

export default DataTableFilterInput;
