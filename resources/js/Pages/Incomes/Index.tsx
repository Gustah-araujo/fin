import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import DataTable from '@/Components/DataTable/DataTable';
import { useWorkspace } from '@/hooks/useWorkspace';
import { formatCurrency } from '@/lib/format-currency';
import type { DataTableColumn } from '@/types/datatable';

interface TagItem {
    uuid: string;
    name: string;
    color: string;
}

interface CategoryItem {
    uuid: string;
    name: string;
    type: string;
    color: string;
    icon: string | null;
}

interface AccountItem {
    uuid: string;
    name: string;
    type: string;
    current_balance: number;
}

interface IncomeItem {
    uuid: string;
    description: string;
    value: number;
    date: string;
    paid_at: string | null;
    is_paid: boolean;
    recurrence_id: string | null;
    account: AccountItem | null;
    category: CategoryItem | null;
    tags: TagItem[];
}

interface Props {
    accounts: AccountItem[];
    categories: CategoryItem[];
    tags: TagItem[];
}

const STATUS_OPTIONS = [
    { label: 'Confirmadas', value: 'paid' },
    { label: 'Previstas', value: 'unpaid' },
];

const ORIGIN_OPTIONS = [
    { label: 'Recorrentes', value: 'recurring' },
    { label: 'Avulsas', value: 'single' },
];

function formatDate(dateStr: string): string {
    return new Date(dateStr + 'T00:00:00').toLocaleDateString('pt-BR');
}

function getTagStyle(color: string): string {
    return `background-color: ${color}20; color: ${color}; border-color: ${color}40`;
}

export default function Index({ accounts, categories }: Props) {
    const workspace = useWorkspace();
    const pageUrl = usePage().url;
    const [reloadTrigger, setReloadTrigger] = useState(0);

    const bumpReload = useCallback(() => {
        setReloadTrigger((n) => n + 1);
    }, []);

    const recurrenceParam = useMemo(() => {
        const search = pageUrl.includes('?') ? pageUrl.split('?')[1] : '';
        return new URLSearchParams(search).get('recurrence') ?? undefined;
    }, [pageUrl]);

    const initialFilters = useMemo(
        () => (recurrenceParam ? { recurrence: recurrenceParam } : undefined),
        [recurrenceParam],
    );

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

    const handlePay = useCallback(
        (uuid: string): void => {
            router.post(
                route('incomes.pay', {
                    workspace: workspace.uuid,
                    transaction: uuid,
                }),
                {},
                { preserveScroll: true, onSuccess: bumpReload },
            );
        },
        [workspace.uuid, bumpReload],
    );

    const handleUnpay = useCallback(
        (uuid: string): void => {
            router.post(
                route('incomes.unpay', {
                    workspace: workspace.uuid,
                    transaction: uuid,
                }),
                {},
                { preserveScroll: true, onSuccess: bumpReload },
            );
        },
        [workspace.uuid, bumpReload],
    );

    const columns = useMemo<DataTableColumn<IncomeItem>[]>(
        () => [
            {
                key: 'description',
                header: 'Descrição',
                sortable: true,
                filter: { type: 'text' },
                cell: (row) => (
                    <span className="font-medium">{row.description}</span>
                ),
            },
            {
                key: 'value',
                header: 'Valor',
                align: 'right',
                sortable: true,
                filter: { type: 'number' },
                cell: (row) => (
                    <span className="font-semibold text-emerald-600 whitespace-nowrap">
                        {formatCurrency(row.value)}
                    </span>
                ),
            },
            {
                key: 'date',
                header: 'Data',
                sortable: true,
                filter: { type: 'date' },
                cell: (row) => formatDate(row.date),
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
                key: 'tags',
                header: 'Tags',
                cell: (row) =>
                    row.tags.length > 0 ? (
                        <div className="flex flex-wrap items-center gap-1">
                            {row.tags.map((tag) => (
                                <Badge
                                    key={tag.uuid}
                                    variant="outline"
                                    style={
                                        getTagStyle(
                                            tag.color,
                                        ) as React.CSSProperties
                                    }
                                    className="text-xs"
                                >
                                    {tag.name}
                                </Badge>
                            ))}
                        </div>
                    ) : null,
            },
            {
                key: 'status',
                header: 'Status',
                filter: { type: 'select', options: STATUS_OPTIONS },
                cell: (row) => (
                    <span
                        className={
                            row.is_paid ? 'text-emerald-600' : 'text-amber-600'
                        }
                    >
                        {row.is_paid ? '✓ Recebida' : '○ Prevista'}
                    </span>
                ),
            },
            {
                key: 'origin',
                header: 'Origem',
                filter: { type: 'select', options: ORIGIN_OPTIONS },
                cell: (row) =>
                    row.recurrence_id != null ? (
                        <Badge variant="secondary">Recorrente</Badge>
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
                        {row.is_paid ? (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => handleUnpay(row.uuid)}
                            >
                                Desmarcar
                            </Button>
                        ) : (
                            <Button
                                size="sm"
                                onClick={() => handlePay(row.uuid)}
                            >
                                Confirmar
                            </Button>
                        )}
                        <Button variant="outline" size="sm" asChild>
                            <Link
                                href={route('incomes.edit', {
                                    workspace: workspace.uuid,
                                    transaction: row.uuid,
                                })}
                            >
                                Editar
                            </Link>
                        </Button>
                        <DeleteButton
                            workspaceUuid={workspace.uuid}
                            income={row}
                            onDeleted={bumpReload}
                        />
                    </div>
                ),
            },
        ],
        [
            accountOptions,
            categoryOptions,
            handlePay,
            handleUnpay,
            workspace.uuid,
            bumpReload,
        ],
    );

    return (
        <AuthenticatedLayout>
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Receitas
                        </h1>
                        {recurrenceParam ? (
                            <div className="flex items-center gap-2 mt-1">
                                <Badge variant="secondary">
                                    Mostrando instâncias de uma recorrência
                                </Badge>
                                <Button variant="ghost" size="sm" asChild>
                                    <Link
                                        href={route('incomes.index', {
                                            workspace: workspace.uuid,
                                        })}
                                    >
                                        Mostrar todas
                                    </Link>
                                </Button>
                            </div>
                        ) : null}
                    </div>
                    <Button asChild>
                        <Link
                            href={route('incomes.create', {
                                workspace: workspace.uuid,
                            })}
                        >
                            Nova Receita
                        </Link>
                    </Button>
                </div>

                <DataTable
                    endpoint={route('incomes.datatable', {
                        workspace: workspace.uuid,
                    })}
                    columns={columns}
                    initialFilters={initialFilters}
                    reloadTrigger={reloadTrigger}
                    emptyState={
                        <div className="flex flex-col items-center gap-4 py-12">
                            <p className="text-sm text-muted-foreground">
                                Nenhuma receita registrada
                            </p>
                            <Button asChild>
                                <Link
                                    href={route('incomes.create', {
                                        workspace: workspace.uuid,
                                    })}
                                >
                                    Registrar primeira receita
                                </Link>
                            </Button>
                        </div>
                    }
                />
            </div>
        </AuthenticatedLayout>
    );
}

function DeleteButton({
    workspaceUuid,
    income,
    onDeleted,
}: {
    workspaceUuid: string;
    income: IncomeItem;
    onDeleted?: () => void;
}) {
    const { delete: destroy, processing } = useForm({});
    const [open, setOpen] = useState(false);

    function handleDelete(scope: string) {
        destroy(
            route('incomes.destroy', {
                workspace: workspaceUuid,
                transaction: income.uuid,
            }),
            {
                data: { scope },
                preserveScroll: true,
                onFinish: () => {
                    setOpen(false);
                    onDeleted?.();
                },
            },
        );
    }

    if (income.recurrence_id == null) {
        return (
            <Button
                variant="destructive"
                size="sm"
                disabled={processing}
                onClick={() => handleDelete('single')}
            >
                Excluir
            </Button>
        );
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <Button
                variant="destructive"
                size="sm"
                onClick={() => setOpen(true)}
            >
                Excluir
            </Button>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Excluir receita recorrente</DialogTitle>
                    <DialogDescription>
                        Escolha o escopo da exclusão.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter className="flex-col gap-2 sm:flex-col">
                    <Button
                        variant="outline"
                        onClick={() => handleDelete('single')}
                        disabled={processing}
                    >
                        Apenas esta
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={() => handleDelete('future')}
                        disabled={processing}
                    >
                        Esta e parar futuras
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
