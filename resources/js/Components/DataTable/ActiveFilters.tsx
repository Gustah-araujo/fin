import type React from 'react';
import { X } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

export interface ActiveFilter {
    key: string;
    label: string;
    value: string;
}

interface ActiveFiltersProps {
    filters: ActiveFilter[];
    onRemoveFilter(key: string): void;
    onClearAll(): void;
}

export function ActiveFilters({
    filters,
    onRemoveFilter,
    onClearAll,
}: ActiveFiltersProps): React.ReactElement | null {
    if (filters.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center gap-2">
            <span className="text-sm text-muted-foreground">
                Filtros ativos:
            </span>

            {filters.map((filter) => (
                <Badge
                    key={filter.key}
                    variant="secondary"
                    className="gap-1 pr-1"
                >
                    {filter.label}
                    <button
                        type="button"
                        onClick={() => onRemoveFilter(filter.key)}
                        className="ml-0.5 rounded-full p-0.5 hover:bg-muted-foreground/20"
                        aria-label={`Remover filtro ${filter.label}`}
                    >
                        <X className="h-3 w-3" />
                    </button>
                </Badge>
            ))}

            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={onClearAll}
                className="h-7 text-xs"
            >
                Limpar Filtros
            </Button>
        </div>
    );
}

export default ActiveFilters;
