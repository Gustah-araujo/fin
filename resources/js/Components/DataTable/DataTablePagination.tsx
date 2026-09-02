import { ChevronLeft, ChevronRight } from 'lucide-react';

import { Button } from '@/components/ui/button';
import type { PaginatedMeta } from '@/types/datatable';

interface DataTablePaginationProps {
    meta: PaginatedMeta;
    onPageChange(page: number): void;
}

export function DataTablePagination({
    meta,
    onPageChange,
}: DataTablePaginationProps) {
    if (meta.last_page <= 1) {
        return null;
    }

    return (
        <div className="flex items-center justify-between px-2 py-3">
            <p className="text-sm text-muted-foreground">
                Página {meta.current_page} de {meta.last_page}{' '}
                <span className="ml-1">
                    ({meta.total.toLocaleString('pt-BR')} registros)
                </span>
            </p>

            <div className="flex items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={meta.current_page <= 1}
                    onClick={() => onPageChange(meta.current_page - 1)}
                >
                    <ChevronLeft />
                    Anterior
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={meta.current_page >= meta.last_page}
                    onClick={() => onPageChange(meta.current_page + 1)}
                >
                    Próxima
                    <ChevronRight />
                </Button>
            </div>
        </div>
    );
}

export default DataTablePagination;
