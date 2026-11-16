import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatCurrency } from '@/lib/format-currency';

interface CategorySlice {
    uuid: string | null;
    name: string;
    color: string;
    group_color: string | null;
    group_name: string | null;
    total: number;
    percent: number;
}

const MAX_ROWS = 6;

interface Props {
    categories: CategorySlice[];
}

export default function CategoriesChart({ categories }: Props) {
    if (categories.length === 0) {
        return (
            <Card>
                <CardHeader className="pb-2">
                    <CardTitle className="text-lg font-semibold">
                        Gastos por Categoria
                    </CardTitle>
                </CardHeader>
                <CardContent className="flex flex-col items-center justify-center py-10 text-center">
                    <p className="text-sm text-muted-foreground">
                        Nenhuma despesa registrada neste mês.
                    </p>
                </CardContent>
            </Card>
        );
    }

    const visible = categories.slice(0, MAX_ROWS);
    const hiddenCount = categories.length - visible.length;

    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-lg font-semibold">
                    Gastos por Categoria
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
                {visible.map((category) => (
                    <div key={category.uuid ?? 'unknown'} className="space-y-1">
                        <div className="flex items-center gap-2">
                            <span
                                className="size-2.5 shrink-0 rounded-full"
                                style={{ backgroundColor: category.color }}
                            />
                            <span className="min-w-0 flex-1 truncate text-sm">
                                {category.name}
                            </span>
                            {category.group_name !== null && (
                                <Badge
                                    variant="outline"
                                    className="gap-1 px-1.5 text-[10px]"
                                >
                                    <span
                                        className="size-1.5 rounded-full"
                                        style={{
                                            backgroundColor:
                                                category.group_color ??
                                                '#9CA3AF',
                                        }}
                                    />
                                    {category.group_name}
                                </Badge>
                            )}
                            <span className="text-xs text-muted-foreground">
                                {category.percent.toFixed(1).replace('.', ',')}%
                            </span>
                            <span className="text-sm font-semibold">
                                {formatCurrency(category.total)}
                            </span>
                        </div>
                        <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full rounded-full"
                                style={{
                                    width: `${category.percent}%`,
                                    backgroundColor: category.color,
                                }}
                            />
                        </div>
                    </div>
                ))}

                {hiddenCount > 0 && (
                    <p className="text-xs text-muted-foreground">
                        +{hiddenCount} outras
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
