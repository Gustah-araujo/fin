import { Link, router } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/components/ui/button';
import DataTable from '@/Components/DataTable/DataTable';
import { useWorkspace } from '@/hooks/useWorkspace';
import { formatCurrency } from '@/lib/format-currency';
import type { DataTableColumn } from '@/types/datatable';

interface AccountItem {
    uuid: string;
    name: string;
}

interface CategoryItem {
    uuid: string;
    name: string;
    color: string;
}

interface RecurrenceItem {
    uuid: string;
    description: string;
    value: number;
    frequency: string;
    frequency_day: number;
    next_date: string | null;
    status: string;
    account: AccountItem | null;
    category: CategoryItem | null;
}

interface Props {
    accounts: AccountItem[];
    categories: CategoryItem[];
}

const STATUS_OPTIONS = [
    { label: 'Ativa', value: 'active' },
    { label: 'Pausada', value: 'paused' },
    { label: 'Esgotada', value: 'exhausted' },
];

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

export default function Index({ accounts, categories }: Props) {
    const workspace = useWorkspace();
    const [reloadTrigger, setReloadTrigger] = useState(0);

    const bumpReload = useCallback(() => {
        setReloadTrigger((n) => n + 1);
    }, []);

    const accountOptions = useMemo(
        () =>
            accounts.map((account) => ({
                label: account.name,
                value: account.uuid,
            })),
        [accounts],
    );

    const categoryOptions = useMemo(
        () =>
            categories.map((category) => ({
                label: category.name,
                value: category.uuid,
            })),
        [categories],
    );

    const togglePause = useCallback(
        (recurrence: RecurrenceItem): void => {
            const action = recurrence.status === 'paused' ? 'restore' : 'pause';
            router.post(
                route(`recurrences.${action}`, {
                    workspace: workspace.uuid,
                    recurrence: recurrence.uuid,
                }),
                {},
                { preserveScroll: true, onSuccess: bumpReload },
            );
        },
        [workspace.uuid, bumpReload],
    );

    const generateNow = useCallback(
        (recurrence: RecurrenceItem): void => {
            router.post(
                route('recurrences.generate', {
                    workspace: workspace.uuid,
                    recurrence: recurrence.uuid,
                }),
                {},
                { preserveScroll: true },
            );
        },
        [workspace.uuid],
    );

    const columns = useMemo<DataTableColumn<RecurrenceItem>[]>(
        () => [
            {
                key: 'description',
                header: 'Descrição',
                sortable: true,
                filter: { type: 'text' },
                cell: (row) => (
                    <div className="min-w-0">
                        <p className="truncate font-medium">
                            {row.description}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {frequencyLabel(row)}
                        </p>
                    </div>
                ),
            },
            {
                key: 'value',
                header: 'Valor',
                align: 'right',
                sortable: true,
                filter: { type: 'number' },
                cell: (row) => (
                    <span className="whitespace-nowrap font-semibold text-emerald-600">
                        {formatCurrency(row.value)}
                    </span>
                ),
            },
            {
                key: 'next_date',
                header: 'Próxima',
                sortable: true,
                filter: { type: 'date' },
                cell: (row) =>
                    row.next_date ? formatDate(row.next_date) : '—',
            },
            {
                key: 'status',
                header: 'Status',
                filter: { type: 'select', options: STATUS_OPTIONS },
                cell: (row) => {
                    const status = statusInfo(row);

                    return (
                        <span className={status.className}>{status.label}</span>
                    );
                },
            },
            {
                key: 'account',
                header: 'Conta',
                filter: { type: 'select', options: accountOptions },
                cell: (row) => row.account?.name ?? '—',
            },
            {
                key: 'category',
                header: 'Categoria',
                filter: { type: 'select', options: categoryOptions },
                cell: (row) =>
                    row.category ? (
                        <div className="flex items-center gap-1.5">
                            <span
                                className="inline-block h-2.5 w-2.5 rounded-full"
                                style={{ backgroundColor: row.category.color }}
                            />
                            <span>{row.category.name}</span>
                        </div>
                    ) : (
                        '—'
                    ),
            },
            {
                key: 'actions',
                header: '',
                align: 'right',
                cell: (row) => (
                    <div className="flex items-center justify-end gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <Link
                                href={route('recurrences.edit', {
                                    workspace: workspace.uuid,
                                    recurrence: row.uuid,
                                })}
                            >
                                Editar
                            </Link>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => togglePause(row)}
                        >
                            {row.status === 'paused' ? 'Reativar' : 'Pausar'}
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => generateNow(row)}
                        >
                            Gerar agora
                        </Button>
                        <Button variant="outline" size="sm" asChild>
                            <Link
                                href={
                                    route('incomes.index', {
                                        workspace: workspace.uuid,
                                    }) +
                                    '?recurrence=' +
                                    row.uuid
                                }
                            >
                                Ver instâncias
                            </Link>
                        </Button>
                    </div>
                ),
            },
        ],
        [
            accountOptions,
            categoryOptions,
            generateNow,
            togglePause,
            workspace.uuid,
        ],
    );

    return (
        <AuthenticatedLayout>
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Recorrências
                        </h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Regras de receitas recorrentes
                        </p>
                    </div>
                    <Button asChild>
                        <Link
                            href={route('incomes.create', {
                                workspace: workspace.uuid,
                            })}
                        >
                            Nova recorrência
                        </Link>
                    </Button>
                </div>

                <DataTable
                    endpoint={route('recurrences.datatable', {
                        workspace: workspace.uuid,
                    })}
                    columns={columns}
                    reloadTrigger={reloadTrigger}
                    emptyState={
                        <div className="flex flex-col items-center gap-4 py-12">
                            <p className="text-sm text-muted-foreground">
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
                        </div>
                    }
                />
            </div>
        </AuthenticatedLayout>
    );
}
