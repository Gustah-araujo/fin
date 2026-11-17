import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { useWorkspace } from '@/hooks/useWorkspace';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Banknote, CheckCircle2, Circle } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { DataTable } from '@/Components/DataTable/DataTable';
import { MonthPicker } from '@/Components/MonthPicker';
import type { ActiveFilter } from '@/Components/DataTable/ActiveFilters';
import { formatCurrency } from '@/lib/format-currency';
import { getCurrentMonth } from '@/lib/month';
import type {
    DataTableColumn,
    DataTableParams,
    PaginatedMeta,
    SelectOption,
    TableState,
} from '@/types/datatable';

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

interface CardItem {
    uuid: string;
    name: string;
    credit_limit: number;
    available_limit: number;
}

interface BillItem {
    uuid: string;
    period_label: string;
    period_year: number;
    period_month: number;
    credit_card_name: string;
}

interface ExpenseGroupItem {
    uuid: string;
    name: string;
    color: string;
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
    credit_card?: { uuid: string; name: string } | null;
    credit_card_id?: string | null;
    credit_card_bill_id?: string | null;
    bill?: { uuid: string; period_label: string; status: string } | null;
    expense_group?: ExpenseGroupItem | null;
    effective_expense_group?: ExpenseGroupItem | null;
}

interface Totals {
    total: number;
    paid: number;
    pending: number;
}

interface Props {
    accounts: AccountItem[];
    categories: CategoryItem[];
    tags: TagItem[];
    cards: CardItem[];
    bills: BillItem[];
    expenseGroups: ExpenseGroupItem[];
    initialState: TableState;
    totals: Totals;
}

function formatDate(dateStr: string): string {
    return new Date(dateStr + 'T00:00:00').toLocaleDateString('pt-BR');
}

function getTagStyle(color: string | null | undefined): React.CSSProperties {
    if (!color) {
        return {};
    }

    return {
        backgroundColor: `${color}20`,
        color: color,
        borderColor: `${color}40`,
    };
}

interface FilterContext {
    accounts: AccountItem[];
    categories: CategoryItem[];
    cards: CardItem[];
    bills: BillItem[];
    groups: ExpenseGroupItem[];
}

function buildActiveFilters(
    filters: Record<string, string>,
    accounts: AccountItem[],
    categories: CategoryItem[],
    cards: CardItem[],
    bills: BillItem[],
    groups: ExpenseGroupItem[],
): ActiveFilter[] {
    const context: FilterContext = {
        accounts,
        categories,
        cards,
        bills,
        groups,
    };

    const resolvers: Record<string, (value: string) => string> = {
        account: (value) =>
            `Conta: ${context.accounts.find((a) => a.uuid === value)?.name ?? value}`,
        category: (value) =>
            `Categoria: ${context.categories.find((c) => c.uuid === value)?.name ?? value}`,
        status: (value) => `Status: ${value === 'paid' ? 'Paga' : 'Não paga'}`,
        description: (value) => `Descrição: ${value}`,
        value: (value) => `Valor: ${value}`,
        date: (value) => `Data: ${value}`,
        credit_card_id: (value) =>
            `Cartão: ${context.cards.find((c) => c.uuid === value)?.name ?? value}`,
        credit_card_bill_id: (value) => {
            const bill = context.bills.find((b) => b.uuid === value);
            return `Fatura: ${bill ? `${bill.credit_card_name} — ${bill.period_label}` : value}`;
        },
        expense_group: (value) =>
            `Grupo: ${value === 'none' ? 'Sem Grupo' : (context.groups.find((g) => g.uuid === value)?.name ?? value)}`,
    };

    return Object.entries(filters)
        .filter(([, value]) => value !== '')
        .map(([key, value]) => ({
            key,
            label: resolvers[key]?.(value) ?? `${key}: ${value}`,
            value,
        }));
}

export default function Index({
    accounts,
    categories,
    cards,
    bills,
    expenseGroups,
    initialState,
    totals,
}: Props) {
    const workspace = useWorkspace();
    const page = usePage();
    const urlParams = new URLSearchParams(page.url.split('?')[1] ?? '');
    const [month, setMonth] = useState(
        () => urlParams.get('month') ?? getCurrentMonth(),
    );
    const [reloadTrigger, setReloadTrigger] = useState(0);
    const [liveTotals, setLiveTotals] = useState<Totals>(totals);
    const [activeFilters, setActiveFilters] = useState<ActiveFilter[]>(() =>
        buildActiveFilters(
            initialState.filters,
            accounts,
            categories,
            cards,
            bills,
            expenseGroups,
        ),
    );
    const clearState = useForm();

    // Reset live totals whenever the server-side totals prop refreshes
    // (month change or full page reload)
    useEffect(() => {
        setLiveTotals(totals);
    }, [totals]);

    const handleMetaChange = useCallback((meta: PaginatedMeta | null): void => {
        const next = meta?.totals;
        if (!next) return;

        setLiveTotals((prev) =>
            prev.total === next.total &&
            prev.paid === next.paid &&
            prev.pending === next.pending
                ? prev
                : next,
        );
    }, []);

    function handleMonthChange(newMonth: string) {
        setMonth(newMonth);
        router.visit(
            route('transactions.index', {
                workspace: workspace.uuid,
                month: newMonth,
            }),
            { preserveState: true, preserveScroll: false, replace: true },
        );
    }

    const bumpReload = useCallback(() => {
        setReloadTrigger((n) => n + 1);
    }, []);

    const syncActiveFilters = useCallback(
        (filters: Record<string, string>): void => {
            setActiveFilters(
                buildActiveFilters(
                    filters,
                    accounts,
                    categories,
                    cards,
                    bills,
                    expenseGroups,
                ),
            );
        },
        [accounts, categories, cards, bills, expenseGroups],
    );

    const handleStateChange = useCallback(
        (state: DataTableParams): void => {
            syncActiveFilters(state.filters);
        },
        [syncActiveFilters],
    );

    function handleClearFilters(): void {
        clearState.delete(
            route('datatable.state.destroy', {
                workspace: workspace.uuid,
                entity: 'transactions',
            }),
            {
                onSuccess: () => {
                    router.reload();
                },
            },
        );
    }

    const accountOptions: SelectOption[] = accounts.map((account) => ({
        label: account.name,
        value: account.uuid,
    }));

    const categoryOptions: SelectOption[] = categories.map((category) => ({
        label: category.name,
        value: category.uuid,
    }));

    const cardOptions: SelectOption[] = cards.map((card) => ({
        label: card.name,
        value: card.uuid,
    }));

    const billOptions: SelectOption[] = bills.map((bill) => ({
        label: `${bill.credit_card_name} — ${bill.period_label}`,
        value: bill.uuid,
    }));

    const groupOptions: SelectOption[] = [
        ...expenseGroups.map((group) => ({
            label: group.name,
            value: group.uuid,
        })),
        { label: 'Sem Grupo', value: 'none' },
    ];

    const columns: DataTableColumn<TransactionItem>[] = [
        {
            key: 'expense_group',
            header: 'Grupo',
            filter: { type: 'select', options: groupOptions },
            cell: (row) =>
                row.effective_expense_group ? (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <span
                                data-testid="group-dot"
                                className="inline-block h-2.5 w-2.5 rounded-full"
                                style={{
                                    backgroundColor:
                                        row.effective_expense_group.color,
                                }}
                            />
                        </TooltipTrigger>
                        <TooltipContent>
                            {row.effective_expense_group.name}
                        </TooltipContent>
                    </Tooltip>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
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
            key: 'credit_card_id',
            header: 'Cartão',
            filter: { type: 'select', options: cardOptions },
            cell: (row) => row.credit_card?.name ?? '—',
        },
        {
            key: 'credit_card_bill_id',
            header: 'Fatura',
            filter: { type: 'select', options: billOptions },
            cell: (row) => row.bill?.period_label ?? '—',
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
                        style={getTagStyle(tag.color)}
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
            cell: (row) => {
                // Card expense status
                if (row.credit_card_id != null) {
                    if (!row.bill)
                        return <span className="text-muted-foreground">—</span>;
                    switch (row.bill.status) {
                        case 'open':
                            return (
                                <span className="text-amber-600">
                                    Na fatura (Aberta)
                                </span>
                            );
                        case 'closed':
                            return (
                                <span className="text-orange-600">
                                    Na fatura (Fechada)
                                </span>
                            );
                        case 'paid':
                            return (
                                <span className="text-emerald-600">Paga</span>
                            );
                        default:
                            return (
                                <span className="text-muted-foreground">—</span>
                            );
                    }
                }
                // Account expense status
                return row.paid_at !== null ? (
                    <span className="text-emerald-600">✓</span>
                ) : (
                    <span className="text-amber-600">○</span>
                );
            },
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
                    <div className="flex items-center gap-4">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Despesas
                        </h1>
                        <MonthPicker
                            month={month}
                            onChange={handleMonthChange}
                        />
                    </div>
                    <div className="flex items-center gap-2">
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
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                <Banknote className="h-4 w-4" />
                                Total
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p
                                className="text-2xl font-semibold"
                                data-testid="total-expenses"
                            >
                                {formatCurrency(liveTotals.total)}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                                Total Pago
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p
                                className="text-2xl font-semibold text-emerald-600"
                                data-testid="total-paid"
                            >
                                {formatCurrency(liveTotals.paid)}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                <Circle className="h-4 w-4 text-amber-600" />
                                Total Pendente
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p
                                className="text-2xl font-semibold text-amber-600"
                                data-testid="total-pending"
                            >
                                {formatCurrency(liveTotals.pending)}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardContent className="pt-6">
                        <DataTable
                            key={month}
                            endpoint={route('transactions.datatable', {
                                workspace: workspace.uuid,
                                month,
                            })}
                            columns={columns}
                            initialState={initialState}
                            onStateChange={handleStateChange}
                            onMetaChange={handleMetaChange}
                            activeFilters={activeFilters}
                            onRemoveFilter={(key) => {
                                setActiveFilters((prev) =>
                                    prev.filter((filter) => filter.key !== key),
                                );
                            }}
                            onClearAll={handleClearFilters}
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
    const isCardExpense = transaction.credit_card_id != null;

    return (
        <div className="flex items-center justify-end gap-2">
            {!isCardExpense &&
                (isPaid ? (
                    <Button variant="outline" size="sm" onClick={handleUnpay}>
                        Desmarcar
                    </Button>
                ) : (
                    <Button size="sm" onClick={handlePay}>
                        Pagar
                    </Button>
                ))}
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
