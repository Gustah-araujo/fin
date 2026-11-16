import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useWorkspace } from '@/hooks/useWorkspace';
import { formatCurrency } from '@/lib/format-currency';

interface ExpenseGroupSlice {
    uuid: string | null;
    name: string;
    color: string;
    total: number;
    percent: number;
}

interface Props {
    groups: ExpenseGroupSlice[];
    hasGroups: boolean;
}

export default function ExpenseGroupsChart({ groups, hasGroups }: Props) {
    const workspace = useWorkspace();

    if (!hasGroups) {
        return (
            <Card>
                <CardHeader className="pb-2">
                    <CardTitle className="text-lg font-semibold">
                        Gastos por Grupo de Despesa
                    </CardTitle>
                </CardHeader>
                <CardContent className="flex flex-col items-center justify-center py-10 text-center">
                    <p className="text-sm font-medium">
                        Nenhum grupo de despesa cadastrado
                    </p>
                    <p className="text-xs text-muted-foreground mt-1 mb-4">
                        Crie grupos para visualizar como seus gastos se
                        distribuem por categoria macro.
                    </p>
                    <Button asChild>
                        <Link
                            href={route('expense-groups.create', {
                                workspace: workspace.uuid,
                            })}
                        >
                            Criar primeiro grupo
                        </Link>
                    </Button>
                </CardContent>
            </Card>
        );
    }

    if (groups.length === 0) {
        return (
            <Card>
                <CardHeader className="pb-2">
                    <CardTitle className="text-lg font-semibold">
                        Gastos por Grupo de Despesa
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

    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-lg font-semibold">
                    Gastos por Grupo de Despesa
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <div
                    className="flex h-3 w-full overflow-hidden rounded-full"
                    data-testid="expense-groups-bar"
                >
                    {groups.map((group) => (
                        <div
                            key={group.uuid ?? 'no-group'}
                            data-testid="group-segment"
                            className="h-full"
                            style={{
                                width: `${group.percent}%`,
                                backgroundColor: group.color,
                            }}
                        />
                    ))}
                </div>

                <ul className="space-y-2">
                    {groups.map((group) => (
                        <li
                            key={group.uuid ?? 'no-group'}
                            className="flex items-center gap-2"
                        >
                            <span
                                className="size-2.5 shrink-0 rounded-full"
                                style={{ backgroundColor: group.color }}
                            />
                            <span className="min-w-0 flex-1 truncate text-sm">
                                {group.name}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {group.percent.toFixed(1).replace('.', ',')}%
                            </span>
                            <span className="text-sm font-semibold">
                                {formatCurrency(group.total)}
                            </span>
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}
