import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatCurrency } from '@/lib/format-currency';
import { Link, usePage } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

interface BillExpense {
    uuid: string;
    description: string;
    value: number;
    date: string;
    installment_number: number | null;
    installments_total: number | null;
    installment_label: string | null;
    category: { uuid: string; name: string; color: string; icon: string | null } | null;
    tags: { uuid: string; name: string; color: string }[];
}

interface Bill {
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
    expenses: BillExpense[];
}

interface CardDetail {
    uuid: string;
    name: string;
    credit_limit: number;
    available_limit: number;
    closing_day: number;
    due_day: number;
}

interface Props {
    card: CardDetail;
    openBill: Bill | null;
    bills: Bill[];
}

export default function Show({ card, openBill, bills }: Props) {
    const { workspace } = usePage<{ workspace: { uuid: string; name: string } }>().props;

    return (
        <AuthenticatedLayout>
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Fatura — {card.name}</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Limite: {formatCurrency(card.credit_limit)} · Disponível: {formatCurrency(card.available_limit)}
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={route('card-expenses.create', { workspace: workspace.uuid, card: card.uuid })}>
                            Nova Compra
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    <div className="md:col-span-2">
                        {openBill ? (
                            <Card>
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <CardTitle>Fatura Atual — {openBill.period_label}</CardTitle>
                                        <Badge>{openBill.status_label}</Badge>
                                    </div>
                                    <p className="text-2xl font-bold">{formatCurrency(openBill.total_amount)}</p>
                                    <p className="text-xs text-muted-foreground">
                                        Fechamento: {openBill.closing_date} · Vencimento: {openBill.due_date}
                                    </p>
                                </CardHeader>
                                <CardContent>
                                    {openBill.expenses.length === 0 ? (
                                        <p className="text-sm text-muted-foreground py-8 text-center">
                                            Nenhuma compra neste ciclo
                                        </p>
                                    ) : (
                                        <div className="space-y-3">
                                            {openBill.expenses.map((expense) => (
                                                <div key={expense.uuid} className="flex items-center justify-between border-b pb-2" data-testid="expense-row">
                                                    <div>
                                                        <span className="font-medium">{expense.description}</span>
                                                        {expense.installment_label && (
                                                            <Badge variant="outline" className="ml-2 text-xs">
                                                                {expense.installment_label}
                                                            </Badge>
                                                        )}
                                                        <p className="text-xs text-muted-foreground">
                                                            {expense.date}
                                                            {expense.category && ` · ${expense.category.name}`}
                                                        </p>
                                                    </div>
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-semibold">{formatCurrency(expense.value)}</span>
                                                        <Button variant="outline" size="sm" asChild>
                                                            <Link href={route('card-expenses.edit', { workspace: workspace.uuid, card: card.uuid, transaction: expense.uuid })}>
                                                                Editar
                                                            </Link>
                                                        </Button>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ) : (
                            <Card className="border-dashed">
                                <CardContent className="flex flex-col items-center justify-center py-12">
                                    <p className="text-sm text-muted-foreground mb-4">Nenhuma fatura aberta</p>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    <div>
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-lg">Faturas Anteriores</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {bills.filter(b => b.status !== 'open').length === 0 ? (
                                    <p className="text-sm text-muted-foreground">Não há faturas anteriores</p>
                                ) : (
                                    <div className="space-y-2">
                                        {bills.filter(b => b.status !== 'open').map((bill) => (
                                            <Link
                                                key={bill.uuid}
                                                href={route('bills.show', { workspace: workspace.uuid, bill: bill.uuid })}
                                                className="flex items-center justify-between rounded-md border p-2 hover:bg-accent"
                                            >
                                                <span className="text-sm">{bill.period_label}</span>
                                                <div className="flex items-center gap-2">
                                                    <span className="text-sm font-medium">{formatCurrency(bill.total_amount)}</span>
                                                    <Badge variant="outline">{bill.status_label}</Badge>
                                                </div>
                                            </Link>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
