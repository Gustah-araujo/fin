import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatCurrency } from '@/lib/format-currency';
import type { ImportPreviewItem } from '@/types/import';

interface Props {
    items: ImportPreviewItem[];
    onItemsChange: (items: ImportPreviewItem[]) => void;
    type: 'expense' | 'income';
}

function formatDate(dateStr: string): string {
    return new Date(dateStr + 'T00:00:00').toLocaleDateString('pt-BR');
}

/**
 * Editable preview of a parsed CSV import.
 *
 * The checkbox controls the `confirm_duplicate` flag, which doubles as the
 * "include in import" selection: the page seeds non-duplicate rows with
 * `confirm_duplicate: true` and duplicate rows with `false`, then only submits
 * the rows whose flag is still true. Duplicate rows are highlighted so the user
 * confirms them explicitly.
 */
export default function ImportPreviewTable({
    items,
    onItemsChange,
    type,
}: Props) {
    function updateItem<K extends keyof ImportPreviewItem>(
        id: string,
        field: K,
        value: ImportPreviewItem[K],
    ): void {
        onItemsChange(
            items.map((item) =>
                item.id === id ? { ...item, [field]: value } : item,
            ),
        );
    }

    const valueClassName =
        type === 'income' ? 'text-emerald-600' : 'text-destructive';

    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead className="w-10" />
                    <TableHead>Descrição</TableHead>
                    <TableHead>Valor</TableHead>
                    <TableHead>Data</TableHead>
                    <TableHead>Categoria</TableHead>
                    <TableHead>Duplicata</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {items.map((item) => (
                    <TableRow
                        key={item.id}
                        className={
                            item.is_duplicate ? 'bg-amber-50/60' : undefined
                        }
                    >
                        <TableCell>
                            <Checkbox
                                checked={item.confirm_duplicate}
                                onCheckedChange={(checked) =>
                                    updateItem(
                                        item.id,
                                        'confirm_duplicate',
                                        checked === true,
                                    )
                                }
                                aria-label={`Incluir ${item.description}`}
                            />
                        </TableCell>
                        <TableCell>
                            <Input
                                value={item.description}
                                onChange={(e) =>
                                    updateItem(
                                        item.id,
                                        'description',
                                        e.target.value,
                                    )
                                }
                                className="min-w-48"
                            />
                        </TableCell>
                        <TableCell>
                            <Input
                                type="number"
                                step="0.01"
                                min="0.01"
                                value={item.value}
                                onChange={(e) =>
                                    updateItem(
                                        item.id,
                                        'value',
                                        Number(e.target.value),
                                    )
                                }
                                className="w-32"
                            />
                            <p
                                className={`mt-1 text-xs whitespace-nowrap ${valueClassName}`}
                            >
                                {formatCurrency(item.value)}
                            </p>
                        </TableCell>
                        <TableCell>
                            <Input
                                type="date"
                                value={item.date}
                                onChange={(e) =>
                                    updateItem(item.id, 'date', e.target.value)
                                }
                                className="w-40"
                            />
                            <p className="mt-1 text-xs text-muted-foreground whitespace-nowrap">
                                {formatDate(item.date)}
                            </p>
                        </TableCell>
                        <TableCell>
                            <Input
                                value={item.category_name}
                                onChange={(e) =>
                                    updateItem(
                                        item.id,
                                        'category_name',
                                        e.target.value,
                                    )
                                }
                                className="min-w-40"
                            />
                        </TableCell>
                        <TableCell>
                            {item.is_duplicate ? (
                                <Badge
                                    variant="outline"
                                    className="border-amber-500 bg-amber-100 text-amber-800"
                                >
                                    Possível duplicata
                                </Badge>
                            ) : null}
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
