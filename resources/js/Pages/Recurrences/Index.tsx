import { Link, router } from '@inertiajs/react';
import { useWorkspace } from '@/hooks/useWorkspace';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { formatCurrency } from '@/lib/format-currency';

interface RecurrenceItem {
    id: string;
    description: string;
    value: number;
    frequency: string;
    frequency_day: number;
    start_date: string;
    until_date: string | null;
    next_date: string | null;
    status: string;
    account: { uuid: string; name: string } | null;
    category: { uuid: string; name: string; color: string } | null;
}

interface Props {
    recurrences: RecurrenceItem[];
}

const WEEKDAYS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

function frequencyLabel(recurrence: RecurrenceItem): string {
    if (recurrence.frequency === 'weekly') {
        return `Toda ${WEEKDAYS[recurrence.frequency_day] ?? 'Sem'}`;
    }

    return `Todo dia ${recurrence.frequency_day}`;
}

function statusInfo(recurrence: RecurrenceItem): {
    label: string;
    className: string;
} {
    if (recurrence.status === 'paused') {
        return { label: 'Pausada', className: 'text-amber-600' };
    }

    if (recurrence.next_date == null) {
        return { label: 'Esgotada', className: 'text-muted-foreground' };
    }

    return { label: 'Ativa', className: 'text-emerald-600' };
}

function formatDate(dateStr: string): string {
    return new Date(dateStr + 'T00:00:00').toLocaleDateString('pt-BR');
}

export default function Index({ recurrences }: Props) {
    const workspace = useWorkspace();

    function togglePause(recurrence: RecurrenceItem) {
        const action = recurrence.status === 'paused' ? 'restore' : 'pause';
        router.post(
            route(`recurrences.${action}`, {
                workspace: workspace.uuid,
                recurrence: recurrence.id,
            }),
            {},
            { preserveScroll: true },
        );
    }

    function generateNow(recurrence: RecurrenceItem) {
        router.post(
            route('recurrences.generate', {
                workspace: workspace.uuid,
                recurrence: recurrence.id,
            }),
            {},
            { preserveScroll: true },
        );
    }

    return (
        <AuthenticatedLayout>
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Recorrências
                    </h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        Regras de receitas recorrentes
                    </p>
                </div>

                {recurrences.length === 0 ? (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <p className="text-sm text-muted-foreground mb-4">
                                Nenhuma recorrência cadastrada
                            </p>
                            <Button asChild>
                                <Link
                                    href={route('incomes.create', {
                                        workspace: workspace.uuid,
                                    })}
                                >
                                    Criar receita recorrente
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-3">
                        {recurrences.map((recurrence) => {
                            const status = statusInfo(recurrence);
                            return (
                                <Card key={recurrence.id}>
                                    <CardContent className="py-4">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0 flex-1 space-y-1">
                                                <div className="flex items-center justify-between gap-4">
                                                    <p className="font-semibold truncate">
                                                        {recurrence.description}
                                                    </p>
                                                    <p className="font-semibold whitespace-nowrap text-emerald-600">
                                                        {formatCurrency(
                                                            recurrence.value,
                                                        )}
                                                    </p>
                                                </div>
                                                <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground">
                                                    <span>
                                                        {frequencyLabel(
                                                            recurrence,
                                                        )}
                                                    </span>
                                                    <span
                                                        className={
                                                            status.className
                                                        }
                                                    >
                                                        {status.label}
                                                    </span>
                                                    {recurrence.next_date && (
                                                        <span>
                                                            Próxima em{' '}
                                                            {formatDate(
                                                                recurrence.next_date,
                                                            )}
                                                        </span>
                                                    )}
                                                    {recurrence.until_date && (
                                                        <span>
                                                            Até{' '}
                                                            {formatDate(
                                                                recurrence.until_date,
                                                            )}
                                                        </span>
                                                    )}
                                                    {recurrence.account && (
                                                        <span>
                                                            {
                                                                recurrence
                                                                    .account
                                                                    .name
                                                            }
                                                        </span>
                                                    )}
                                                </div>
                                                {recurrence.category && (
                                                    <div className="flex items-center gap-1 pt-1">
                                                        <span
                                                            className="inline-block w-2.5 h-2.5 rounded-full"
                                                            style={{
                                                                backgroundColor:
                                                                    recurrence
                                                                        .category
                                                                        .color,
                                                            }}
                                                        />
                                                        <span className="text-sm text-muted-foreground">
                                                            {
                                                                recurrence
                                                                    .category
                                                                    .name
                                                            }
                                                        </span>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                        <div className="flex flex-wrap items-center gap-2 mt-3">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={route(
                                                        'recurrences.edit',
                                                        {
                                                            workspace:
                                                                workspace.uuid,
                                                            recurrence:
                                                                recurrence.id,
                                                        },
                                                    )}
                                                >
                                                    Editar
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    togglePause(recurrence)
                                                }
                                            >
                                                {recurrence.status === 'paused'
                                                    ? 'Reativar'
                                                    : 'Pausar'}
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    generateNow(recurrence)
                                                }
                                            >
                                                Gerar agora
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={
                                                        route('incomes.index', {
                                                            workspace:
                                                                workspace.uuid,
                                                        }) +
                                                        '?recurrence=' +
                                                        recurrence.id
                                                    }
                                                >
                                                    Ver instâncias
                                                </Link>
                                            </Button>
                                        </div>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
