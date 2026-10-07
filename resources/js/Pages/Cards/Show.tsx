import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatCurrency } from '@/lib/format-currency';
import { useForm } from '@inertiajs/react';
import { useWorkspace } from '@/hooks/useWorkspace';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Link } from '@inertiajs/react';
import { useState, useMemo, useCallback } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface BillExpense {
    uuid: string;
    description: string;
    value: number;
    date: string;
    installment_number: number | null;
    installments_total: number | null;
    installment_label: string | null;
    category: {
        uuid: string;
        name: string;
        color: string;
        icon: string | null;
    } | null;
    tags: { uuid: string; name: string; color: string }[];
}

interface BillItem {
    uuid: string;
    period_year: number;
    period_month: number;
    period_label: string;
    closing_date: string;
    due_date: string;
    status: string;
    status_label: string;
    total_amount: number;
    closed_at: string | null;
    paid_at: string | null;
    payment_account: { uuid: string; name: string } | null;
    expenses: BillExpense[];
}

interface Account {
    uuid: string;
    name: string;
    type: string;
    current_balance: number;
}

interface CardDetail {
    uuid: string;
    name: string;
    credit_limit: number;
    available_limit: number;
    closing_day: number;
    due_day: number;
    archived?: boolean;
}

interface Props {
    card: CardDetail;
    bills: BillItem[];
    currentBill: BillItem;
    accounts: Account[];
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function getStatusBadgeVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'paid':
            return 'default';
        case 'closed':
            return 'secondary';
        case 'open':
            return 'outline';
        default:
            return 'outline';
    }
}

function formatDate(dateStr: string): string {
    return new Date(dateStr + 'T00:00:00').toLocaleDateString('pt-BR');
}

/**
 * Radix UI leaks `pointer-events: none` / `data-scroll-locked` onto <body> when
 * a Dialog closes while another Radix layer (e.g. Select) is open. Clear the
 * inline lock so subsequent interactions (and Cypress clicks) keep working.
 */
function resetBodyScrollLock(): void {
    document.body.style.removeProperty('pointer-events');
    document.body.removeAttribute('data-scroll-locked');
}

// ---------------------------------------------------------------------------
// PaymentConfirmDialog
// ---------------------------------------------------------------------------

interface PaymentConfirmDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    accounts: Account[];
    onSubmit: (accountId: string) => void;
    processing: boolean;
}

function PaymentConfirmDialog({
    open,
    onOpenChange,
    accounts,
    onSubmit,
    processing,
}: PaymentConfirmDialogProps) {
    const [selectedAccount, setSelectedAccount] = useState('');

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        if (selectedAccount) {
            onSubmit(selectedAccount);
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                onCloseAutoFocus={(e) => {
                    e.preventDefault();
                    resetBodyScrollLock();
                }}
            >
                <DialogHeader>
                    <DialogTitle>Marcar fatura como paga</DialogTitle>
                    <DialogDescription>
                        Selecione a conta que será utilizada para o pagamento.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <Label>Conta para pagamento</Label>
                        <Select
                            value={selectedAccount}
                            onValueChange={setSelectedAccount}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Selecione a conta" />
                            </SelectTrigger>
                            <SelectContent>
                                {accounts.map((acc) => (
                                    <SelectItem key={acc.uuid} value={acc.uuid}>
                                        {acc.name} (
                                        {formatCurrency(acc.current_balance)})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing || !selectedAccount}
                        >
                            Confirmar Pagamento
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

// ---------------------------------------------------------------------------
// BillPaymentForm — inline action for closed bills
// ---------------------------------------------------------------------------

interface BillPaymentFormProps {
    bill: BillItem;
    accounts: Account[];
    isViewer: boolean;
}

function BillPaymentForm({ bill, accounts, isViewer }: BillPaymentFormProps) {
    const workspace = useWorkspace();
    const [showDialog, setShowDialog] = useState(false);
    const form = useForm({ account_id: '' });

    function handleDialogOpenChange(open: boolean) {
        if (!open) {
            resetBodyScrollLock();
        }
        setShowDialog(open);
    }

    function handlePay(accountId: string) {
        form.setData('account_id', accountId);
        form.post(
            route('bills.pay', {
                workspace: workspace.uuid,
                bill: bill.uuid,
            }),
            {
                onSuccess: () => {
                    setShowDialog(false);
                    resetBodyScrollLock();
                    window.location.reload();
                },
            },
        );
    }

    function handleUnpay() {
        form.post(
            route('bills.unpay', {
                workspace: workspace.uuid,
                bill: bill.uuid,
            }),
            {
                onSuccess: () => window.location.reload(),
            },
        );
    }

    if (bill.status === 'closed') {
        return (
            <>
                {!isViewer && (
                    <Button onClick={() => setShowDialog(true)}>
                        Marcar fatura como paga
                    </Button>
                )}
                <PaymentConfirmDialog
                    open={showDialog}
                    onOpenChange={handleDialogOpenChange}
                    accounts={accounts}
                    onSubmit={handlePay}
                    processing={form.processing}
                />
            </>
        );
    }

    if (bill.status === 'open') {
        return (
            <p className="text-sm text-muted-foreground">
                Aguardando fechamento em {formatDate(bill.closing_date)}
            </p>
        );
    }

    if (bill.status === 'paid') {
        return (
            <div className="flex items-center gap-3">
                <p className="text-sm text-emerald-600">
                    Pago em {formatDate(bill.paid_at!)}{' '}
                    {bill.payment_account && `via ${bill.payment_account.name}`}
                </p>
                {!isViewer && (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={handleUnpay}
                        disabled={form.processing}
                    >
                        Desfazer pagamento
                    </Button>
                )}
            </div>
        );
    }

    return null;
}

// ---------------------------------------------------------------------------
// Main Component
// ---------------------------------------------------------------------------

export default function Show({ card, bills, currentBill, accounts }: Props) {
    const workspace = useWorkspace();
    const isViewer = workspace.role === 'viewer';

    const sortedBills = useMemo(
        () =>
            [...bills].sort((a, b) => {
                if (a.period_year !== b.period_year) {
                    return a.period_year - b.period_year;
                }
                return a.period_month - b.period_month;
            }),
        [bills],
    );

    const defaultIndex = useMemo(() => {
        const idx = sortedBills.findIndex((b) => b.uuid === currentBill.uuid);
        return idx >= 0 ? idx : sortedBills.length - 1;
    }, [sortedBills, currentBill]);

    const [selectedIndex, setSelectedIndex] = useState(defaultIndex);

    const selectedBill = sortedBills[selectedIndex] ?? currentBill;

    const goToPrev = useCallback(() => {
        setSelectedIndex((i) => Math.max(0, i - 1));
    }, []);

    const goToNext = useCallback(() => {
        setSelectedIndex((i) => Math.min(sortedBills.length - 1, i + 1));
    }, [sortedBills.length]);

    const consumedLimit = card.credit_limit - card.available_limit;

    return (
        <AuthenticatedLayout>
            <div className="space-y-6">
                {/* ------------------------------------------------------------------ */}
                {/* 1. Page Header                                                      */}
                {/* ------------------------------------------------------------------ */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {card.name}
                        </h1>
                        {card.archived && (
                            <Badge variant="secondary">(Arquivado)</Badge>
                        )}
                    </div>
                    {!isViewer && (
                        <Button asChild>
                            <Link
                                href={route('transactions.create', {
                                    workspace: workspace.uuid,
                                    payment_method: 'card',
                                    card_id: card.uuid,
                                })}
                            >
                                Nova despesa neste cartão
                            </Link>
                        </Button>
                    )}
                </div>

                {/* ------------------------------------------------------------------ */}
                {/* 2. Limit Cards                                                      */}
                {/* ------------------------------------------------------------------ */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Limite total
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-bold">
                                {formatCurrency(card.credit_limit)}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Consumido
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-bold">
                                {formatCurrency(consumedLimit)}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Disponível
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-bold">
                                {formatCurrency(card.available_limit)}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Fatura em vista
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-bold">
                                {formatCurrency(selectedBill.total_amount)}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* ------------------------------------------------------------------ */}
                {/* 3. Invoice Selector (month-picker style)                            */}
                {/* ------------------------------------------------------------------ */}
                {sortedBills.length > 1 && (
                    <div className="flex items-center justify-center gap-4">
                        <Button
                            variant="outline"
                            size="icon"
                            onClick={goToPrev}
                            disabled={selectedIndex === 0}
                        >
                            <ChevronLeft className="h-4 w-4" />
                        </Button>
                        <div className="flex items-center gap-2">
                            <span className="font-medium">
                                {selectedBill.period_label}
                            </span>
                            <Badge
                                variant={getStatusBadgeVariant(
                                    selectedBill.status,
                                )}
                            >
                                {selectedBill.status_label}
                            </Badge>
                        </div>
                        <Button
                            variant="outline"
                            size="icon"
                            onClick={goToNext}
                            disabled={selectedIndex === sortedBills.length - 1}
                        >
                            <ChevronRight className="h-4 w-4" />
                        </Button>
                    </div>
                )}

                {/* ------------------------------------------------------------------ */}
                {/* 4. Invoice Details                                                  */}
                {/* ------------------------------------------------------------------ */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <CardTitle>
                                Fatura — {selectedBill.period_label}
                            </CardTitle>
                            <Badge
                                variant={getStatusBadgeVariant(
                                    selectedBill.status,
                                )}
                            >
                                {selectedBill.status_label}
                            </Badge>
                        </div>
                        <p className="text-2xl font-bold">
                            {formatCurrency(selectedBill.total_amount)}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Fechamento: {formatDate(selectedBill.closing_date)}{' '}
                            · Vencimento: {formatDate(selectedBill.due_date)}
                        </p>
                    </CardHeader>
                    <CardContent>
                        {/* -------------------------------------------------------------- */}
                        {/* 5. Invoice Expenses (Table)                                     */}
                        {/* -------------------------------------------------------------- */}
                        {(selectedBill.expenses?.length ?? 0) === 0 ? (
                            <p className="text-sm text-muted-foreground py-8 text-center">
                                Nenhuma despesa nesta fatura
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Descrição</TableHead>
                                        <TableHead className="text-right">
                                            Valor
                                        </TableHead>
                                        <TableHead>Data</TableHead>
                                        <TableHead>Categoria</TableHead>
                                        <TableHead>Tags</TableHead>
                                        <TableHead>Parcela</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {selectedBill.expenses!.map((expense) => (
                                        <TableRow
                                            key={expense.uuid}
                                            data-testid="expense-row"
                                        >
                                            <TableCell className="font-medium">
                                                {expense.description}
                                            </TableCell>
                                            <TableCell className="text-right font-semibold">
                                                {formatCurrency(expense.value)}
                                            </TableCell>
                                            <TableCell>
                                                {formatDate(expense.date)}
                                            </TableCell>
                                            <TableCell>
                                                {expense.category ? (
                                                    <div className="flex items-center gap-1.5">
                                                        <span
                                                            className="inline-block h-2.5 w-2.5 rounded-full"
                                                            style={{
                                                                backgroundColor:
                                                                    expense
                                                                        .category
                                                                        .color,
                                                            }}
                                                        />
                                                        <span className="text-sm">
                                                            {
                                                                expense.category
                                                                    .name
                                                            }
                                                        </span>
                                                    </div>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex flex-wrap gap-1">
                                                    {expense.tags.map((tag) => (
                                                        <Badge
                                                            key={tag.uuid}
                                                            variant="secondary"
                                                            className="text-xs"
                                                            style={{
                                                                backgroundColor:
                                                                    tag.color +
                                                                    '20',
                                                                color: tag.color,
                                                            }}
                                                        >
                                                            {tag.name}
                                                        </Badge>
                                                    ))}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {expense.installment_label ? (
                                                    <Badge variant="outline">
                                                        {
                                                            expense.installment_label
                                                        }
                                                    </Badge>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}

                        {/* -------------------------------------------------------------- */}
                        {/* 6. Inline Bill Action                                            */}
                        {/* -------------------------------------------------------------- */}
                        <div className="mt-4 pt-4 border-t">
                            <BillPaymentForm
                                bill={selectedBill}
                                accounts={accounts}
                                isViewer={isViewer}
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
