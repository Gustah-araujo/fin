import { Link, router, useForm } from '@inertiajs/react';
import { useWorkspace } from '@/hooks/useWorkspace';
import { useRef, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatCurrency } from '@/lib/format-currency';

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

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedIncomes {
    data: IncomeItem[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
}

interface Props {
    incomes: PaginatedIncomes;
    accounts: AccountItem[];
    categories: CategoryItem[];
    tags: TagItem[];
}

function getQueryParams(): URLSearchParams {
    return new URLSearchParams(window.location.search);
}

export default function Index({ incomes, accounts, categories }: Props) {
    const workspace = useWorkspace();
    const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const params = getQueryParams();
    const activeFilters = [
        'search',
        'category',
        'account',
        'from_date',
        'to_date',
        'status',
        'origin',
    ].filter((key) => params.get(key)).length;

    function updateFilter(key: string, value: string) {
        const currentParams = getQueryParams();
        if (value) {
            currentParams.set(key, value);
        } else {
            currentParams.delete(key);
        }
        currentParams.delete('page');
        router.get(
            route('incomes.index', { workspace: workspace.uuid }) +
                '?' +
                currentParams.toString(),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function handleSearchChange(value: string) {
        if (searchTimeoutRef.current) {
            clearTimeout(searchTimeoutRef.current);
        }
        searchTimeoutRef.current = setTimeout(() => {
            updateFilter('search', value);
        }, 300);
    }

    function clearFilters() {
        router.get(
            route('incomes.index', { workspace: workspace.uuid }),
            {},
            { preserveState: true },
        );
    }

    function handlePay(uuid: string) {
        router.post(
            route('incomes.pay', {
                workspace: workspace.uuid,
                transaction: uuid,
            }),
            {},
            { preserveScroll: true },
        );
    }

    function handleUnpay(uuid: string) {
        router.post(
            route('incomes.unpay', {
                workspace: workspace.uuid,
                transaction: uuid,
            }),
            {},
            { preserveScroll: true },
        );
    }

    function formatDate(dateStr: string): string {
        return new Date(dateStr + 'T00:00:00').toLocaleDateString('pt-BR');
    }

    function getTagStyle(color: string): string {
        return `background-color: ${color}20; color: ${color}; border-color: ${color}40`;
    }

    const pageParams = getQueryParams();

    return (
        <AuthenticatedLayout>
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Receitas
                        </h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            {incomes.data.length} receita
                            {incomes.data.length !== 1 ? 's' : ''}
                        </p>
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

                <Card>
                    <CardContent className="pt-6">
                        <div className="flex flex-wrap items-end gap-3">
                            <div className="flex-1 min-w-[200px] space-y-1">
                                <Label htmlFor="search">Buscar</Label>
                                <Input
                                    id="search"
                                    placeholder="Buscar por descrição..."
                                    defaultValue={
                                        pageParams.get('search') ?? ''
                                    }
                                    onChange={(e) =>
                                        handleSearchChange(e.target.value)
                                    }
                                />
                            </div>

                            <div className="w-[180px] space-y-1">
                                <Label>Categoria</Label>
                                <Select
                                    value={pageParams.get('category') ?? 'all'}
                                    onValueChange={(v) =>
                                        updateFilter(
                                            'category',
                                            v === 'all' ? '' : v,
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Todas" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Todas
                                        </SelectItem>
                                        {categories.map((cat) => (
                                            <SelectItem
                                                key={cat.uuid}
                                                value={cat.uuid}
                                            >
                                                {cat.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="w-[180px] space-y-1">
                                <Label>Conta</Label>
                                <Select
                                    value={pageParams.get('account') ?? 'all'}
                                    onValueChange={(v) =>
                                        updateFilter(
                                            'account',
                                            v === 'all' ? '' : v,
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Todas" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Todas
                                        </SelectItem>
                                        {accounts.map((acc) => (
                                            <SelectItem
                                                key={acc.uuid}
                                                value={acc.uuid}
                                            >
                                                {acc.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="w-[140px] space-y-1">
                                <Label htmlFor="from_date">De</Label>
                                <Input
                                    id="from_date"
                                    type="date"
                                    defaultValue={
                                        pageParams.get('from_date') ?? ''
                                    }
                                    onChange={(e) =>
                                        updateFilter(
                                            'from_date',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>

                            <div className="w-[140px] space-y-1">
                                <Label htmlFor="to_date">Até</Label>
                                <Input
                                    id="to_date"
                                    type="date"
                                    defaultValue={
                                        pageParams.get('to_date') ?? ''
                                    }
                                    onChange={(e) =>
                                        updateFilter('to_date', e.target.value)
                                    }
                                />
                            </div>

                            <div className="w-[150px] space-y-1">
                                <Label>Status</Label>
                                <Select
                                    value={pageParams.get('status') ?? 'all'}
                                    onValueChange={(v) =>
                                        updateFilter(
                                            'status',
                                            v === 'all' ? '' : v,
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Todos" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Todos
                                        </SelectItem>
                                        <SelectItem value="paid">
                                            Confirmadas
                                        </SelectItem>
                                        <SelectItem value="unpaid">
                                            Previstas
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="w-[150px] space-y-1">
                                <Label>Origem</Label>
                                <Select
                                    value={pageParams.get('origin') ?? 'all'}
                                    onValueChange={(v) =>
                                        updateFilter(
                                            'origin',
                                            v === 'all' ? '' : v,
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Todas" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Todas
                                        </SelectItem>
                                        <SelectItem value="recurring">
                                            Recorrentes
                                        </SelectItem>
                                        <SelectItem value="single">
                                            Avulsas
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            {activeFilters > 0 && (
                                <div className="flex items-center gap-2 pb-1">
                                    <Badge variant="secondary">
                                        {activeFilters} filtro
                                        {activeFilters > 1 ? 's' : ''}
                                    </Badge>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={clearFilters}
                                    >
                                        Limpar filtros
                                    </Button>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {incomes.data.length === 0 ? (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <p className="text-sm text-muted-foreground mb-4">
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
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-3">
                        {incomes.data.map((income) => {
                            const isPaid = income.is_paid;
                            const isRecurring = income.recurrence_id != null;
                            return (
                                <Card
                                    key={income.uuid}
                                    className={isPaid ? 'opacity-75' : ''}
                                >
                                    <CardContent className="py-4">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="flex items-start gap-3 min-w-0 flex-1">
                                                <span
                                                    className={`mt-0.5 text-lg ${isPaid ? 'text-emerald-600' : 'text-amber-600'}`}
                                                >
                                                    {isPaid ? '✓' : '○'}
                                                </span>
                                                <div className="min-w-0 flex-1 space-y-1">
                                                    <div className="flex items-center justify-between gap-4">
                                                        <p className="font-semibold truncate">
                                                            {income.description}
                                                        </p>
                                                        <p className="font-semibold whitespace-nowrap text-emerald-600">
                                                            {formatCurrency(
                                                                income.value,
                                                            )}
                                                        </p>
                                                    </div>
                                                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground">
                                                        <span>
                                                            {formatDate(
                                                                income.date,
                                                            )}
                                                        </span>
                                                        {income.account && (
                                                            <span>
                                                                {
                                                                    income
                                                                        .account
                                                                        .name
                                                                }
                                                            </span>
                                                        )}
                                                        {isRecurring && (
                                                            <Badge variant="secondary">
                                                                Recorrente
                                                            </Badge>
                                                        )}
                                                        {isPaid && (
                                                            <span className="text-emerald-600">
                                                                Recebida
                                                            </span>
                                                        )}
                                                        {!isPaid && (
                                                            <span className="text-amber-600">
                                                                Prevista
                                                            </span>
                                                        )}
                                                    </div>
                                                    <div className="flex flex-wrap items-center gap-2 pt-1">
                                                        {income.category && (
                                                            <div className="flex items-center gap-1">
                                                                <span
                                                                    className="inline-block w-2.5 h-2.5 rounded-full"
                                                                    style={{
                                                                        backgroundColor:
                                                                            income
                                                                                .category
                                                                                .color,
                                                                    }}
                                                                />
                                                                <span className="text-sm text-muted-foreground">
                                                                    {
                                                                        income
                                                                            .category
                                                                            .name
                                                                    }
                                                                </span>
                                                            </div>
                                                        )}
                                                        {income.tags.map(
                                                            (tag) => (
                                                                <Badge
                                                                    key={
                                                                        tag.uuid
                                                                    }
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
                                                            ),
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2 mt-3">
                                            {isPaid ? (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        handleUnpay(income.uuid)
                                                    }
                                                >
                                                    Desmarcar
                                                </Button>
                                            ) : (
                                                <Button
                                                    size="sm"
                                                    onClick={() =>
                                                        handlePay(income.uuid)
                                                    }
                                                >
                                                    Confirmar
                                                </Button>
                                            )}
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={route(
                                                        'incomes.edit',
                                                        {
                                                            workspace:
                                                                workspace.uuid,
                                                            transaction:
                                                                income.uuid,
                                                        },
                                                    )}
                                                >
                                                    Editar
                                                </Link>
                                            </Button>
                                            <DeleteButton
                                                workspaceUuid={workspace.uuid}
                                                income={income}
                                            />
                                        </div>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                )}

                {incomes.last_page > 1 && (
                    <div className="flex items-center justify-center gap-1">
                        {incomes.links.map((link, index) => {
                            if (link.url === null) {
                                return (
                                    <span
                                        key={index}
                                        className="px-3 py-2 text-sm text-muted-foreground"
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                );
                            }

                            const url = new URL(link.url);
                            const targetUrl =
                                route('incomes.index', {
                                    workspace: workspace.uuid,
                                }) + url.search;

                            return (
                                <Button
                                    key={index}
                                    variant={
                                        link.active ? 'default' : 'outline'
                                    }
                                    size="sm"
                                    asChild
                                >
                                    <Link
                                        href={targetUrl}
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                </Button>
                            );
                        })}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

function DeleteButton({
    workspaceUuid,
    income,
}: {
    workspaceUuid: string;
    income: IncomeItem;
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
                onFinish: () => setOpen(false),
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
