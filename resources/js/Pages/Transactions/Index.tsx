import { Link, useForm } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import { useWorkspace } from '@/hooks/useWorkspace';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/Components/DataTable/DataTable';
import { formatCurrency } from '@/lib/format-currency';
import type { DataTableColumn, SelectOption } from '@/types/datatable';

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

interface TransactionItem {
    uuid: string;
    description: string;
    value: number;
    date: string;
    paid_at: string | null;
    account: AccountItem | null;
    category: CategoryItem | null;
    tags: TagItem[];
}

interface Props {
    accounts: AccountItem[];
    categories: CategoryItem[];
    tags: TagItem[];
}

function formatDate(dateStr: string): string {
    return new Date(dateStr + 'T00:00:00').toLocaleDateString('pt-BR');
}

function getTagStyle(color: string): string {
    return `background-color: ${color}20; color: ${color}; border-color: ${color}40`;
}

export default function Index({ accounts, categories }: Props) {
    const workspace = useWorkspace();
    const [reloadTrigger, setReloadTrigger] = useState(0);

    const bumpReload = useCallback(() => {
        setReloadTrigger((n) => n + 1);
    }, []);

    const accountOptions: SelectOption[] = accounts.map((account) => ({
        label: account.name,
        value: account.uuid,
    }));

    const categoryOptions: SelectOption[] = categories.map((category) => ({
        label: category.name,
        value: category.uuid,
    }));

    const columns: DataTableColumn<TransactionItem>[] = [
        {
            key: 'description',
            header: 'Descrição',
            sortable: true,
            filter: { type: 'text', placeholder: 'Filtrar descrição…' },
        },
        {
            key: 'value',
            header: 'Valor',
            align: 'right',
            sortable: true,
            filter: { type: 'number' },
            cell: (row) => formatCurrency(row.value),
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
                            className="inline-block w-2.5 h-2.5 rounded-full"
                            style={{ backgroundColor: row.category.color }}
                        />
                        <span className="text-sm text-muted-foreground">
                            {row.category.name}
                        </span>
                    </div>
                ) : (
                    '—'
                ),
        },
        {
            key: 'tags',
            header: 'Tags',
            cell: (row) =>
                row.tags.map((tag) => (
                    <Badge
                        key={tag.uuid}
                        variant="outline"
                        style={getTagStyle(tag.color) as React.CSSProperties}
                        className="text-xs mr-1"
                    >
                        {tag.name}
                    </Badge>
                )),
        },
        {
            key: 'status',
            header: 'Status',
            filter: {
                type: 'select',
                options: [
                    { label: 'Pagos', value: 'paid' },
                    { label: 'Pendentes', value: 'unpaid' },
                ],
            },
            cell: (row) =>
                row.paid_at !== null ? (
                    <span className="text-emerald-600">✓</span>
                ) : (
                    <span className="text-amber-600">○</span>
                ),
        },
        {
            key: 'actions',
            header: '',
            align: 'right',
            cell: (row) => (
                <RowActions
                    workspaceUuid={workspace.uuid}
                    transaction={row}
                    onMutated={bumpReload}
                />
            ),
        },
    ];

    return (
        <AuthenticatedLayout>
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Despesas
                        </h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Gerencie suas despesas
                        </p>
                    </div>
                    <Button asChild>
                        <Link
                            href={route('transactions.create', {
                                workspace: workspace.uuid,
                            })}
                        >
                            Nova Despesa
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardContent className="pt-6">
                        <DataTable
                            endpoint={route('transactions.datatable', {
                                workspace: workspace.uuid,
                            })}
                            columns={columns}
                            reloadTrigger={reloadTrigger}
                            emptyState={
                                <div className="flex flex-col items-center justify-center py-12">
                                    <p className="text-sm text-muted-foreground mb-4">
                                        Nenhuma despesa registrada
                                    </p>
                                    <Button asChild>
                                        <Link
                                            href={route('transactions.create', {
                                                workspace: workspace.uuid,
                                            })}
                                        >
                                            Registrar primeira despesa
                                        </Link>
                                    </Button>
                                </div>
                            }
                        />
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}

function RowActions({
    workspaceUuid,
    transaction,
    onMutated,
}: {
    workspaceUuid: string;
    transaction: TransactionItem;
    onMutated?: () => void;
}) {
    const { post } = useForm({});

    function handlePay() {
        post(
            route('transactions.pay', {
                workspace: workspaceUuid,
                transaction: transaction.uuid,
            }),
            { preserveScroll: true, onSuccess: onMutated },
        );
    }

    function handleUnpay() {
        post(
            route('transactions.unpay', {
                workspace: workspaceUuid,
                transaction: transaction.uuid,
            }),
            { preserveScroll: true, onSuccess: onMutated },
        );
    }

    const isPaid = transaction.paid_at !== null;

    return (
        <div className="flex items-center justify-end gap-2">
            {isPaid ? (
                <Button variant="outline" size="sm" onClick={handleUnpay}>
                    Desmarcar
                </Button>
            ) : (
                <Button size="sm" onClick={handlePay}>
                    Pagar
                </Button>
            )}
            <Button variant="outline" size="sm" asChild>
                <Link
                    href={route('transactions.edit', {
                        workspace: workspaceUuid,
                        transaction: transaction.uuid,
                    })}
                >
                    Editar
                </Link>
            </Button>
            <DeleteButton
                workspaceUuid={workspaceUuid}
                transactionUuid={transaction.uuid}
                onDeleted={onMutated}
            />
        </div>
    );
}

function DeleteButton({
    workspaceUuid,
    transactionUuid,
    onDeleted,
}: {
    workspaceUuid: string;
    transactionUuid: string;
    onDeleted?: () => void;
}) {
    const { delete: destroy, processing } = useForm({});

    function handleDelete() {
        destroy(
            route('transactions.destroy', {
                workspace: workspaceUuid,
                transaction: transactionUuid,
            }),
            { preserveScroll: true, onSuccess: onDeleted },
        );
    }

    return (
        <Button
            variant="destructive"
            size="sm"
            disabled={processing}
            onClick={handleDelete}
        >
            Excluir
        </Button>
    );
}
