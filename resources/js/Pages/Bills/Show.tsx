import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatCurrency } from '@/lib/format-currency';
import { usePage, useForm } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useState } from 'react';

interface BillExpense {
    uuid: string;
    description: string;
    value: number;
    date: string;
    installment_label: string | null;
    category: { uuid: string; name: string; color: string } | null;
    tags: { uuid: string; name: string; color: string }[];
}

interface Bill {
    uuid: string;
    period_label: string;
    closing_date: string;
    due_date: string;
    status: string;
    status_label: string;
    total_amount: number;
    paid_at: string | null;
    expenses: BillExpense[];
}

interface CardDetail {
    uuid: string;
    name: string;
}

interface Account {
    uuid: string;
    name: string;
    current_balance: number;
}

interface Props {
    bill: Bill;
    card: CardDetail;
    accounts: Account[];
}

export default function BillShow({ bill, card, accounts }: Props) {
    const { workspace } = usePage<{ workspace: { uuid: string; name: string } }>().props;
    const [showPayment, setShowPayment] = useState(false);
    const [selectedAccount, setSelectedAccount] = useState('');
    const form = useForm({ account_id: '' });

    function handlePay(e: React.FormEvent) {
        e.preventDefault();
        form.account_id = selectedAccount;
        form.post(route('bills.pay', { workspace: workspace.uuid, bill: bill.uuid }), {
            onSuccess: () => window.location.reload(),
        });
    }

    function handleUnpay() {
        form.post(route('bills.unpay', { workspace: workspace.uuid, bill: bill.uuid }), {
            onSuccess: () => window.location.reload(),
        });
    }

    return (
        <AuthenticatedLayout>
            <div className="max-w-3xl mx-auto space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Fatura {card.name} — {bill.period_label}</h1>
                        <Badge>{bill.status_label}</Badge>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Total: {formatCurrency(bill.total_amount)}</CardTitle>
                        <p className="text-xs text-muted-foreground">
                            Fechamento: {bill.closing_date} · Vencimento: {bill.due_date}
                        </p>
                        {bill.paid_at && <p className="text-xs text-emerald-600">Paga em {new Date(bill.paid_at).toLocaleDateString('pt-BR')}</p>}
                    </CardHeader>
                    <CardContent>
                        {bill.expenses.length === 0 ? (
                            <p className="text-sm text-muted-foreground py-8 text-center">Nenhuma compra nesta fatura</p>
                        ) : (
                            <div className="space-y-3">
                                {bill.expenses.map((expense) => (
                                    <div key={expense.uuid} className="flex items-center justify-between border-b pb-2" data-testid="expense-row">
                                        <div>
                                            <span className="font-medium">{expense.description}</span>
                                            {expense.installment_label && (
                                                <Badge variant="outline" className="ml-2 text-xs">{expense.installment_label}</Badge>
                                            )}
                                            <p className="text-xs text-muted-foreground">
                                                {expense.date}{expense.category && ` · ${expense.category.name}`}
                                            </p>
                                        </div>
                                        <span className="font-semibold">{formatCurrency(expense.value)}</span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {bill.status === 'closed' && !showPayment && (
                    <Button onClick={() => setShowPayment(true)}>Pagar Fatura</Button>
                )}

                {showPayment && (
                    <Card>
                        <CardContent className="py-4">
                            <form onSubmit={handlePay} className="space-y-4">
                                <div>
                                    <Label>Conta para pagamento</Label>
                                    <Select value={selectedAccount} onValueChange={setSelectedAccount}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Selecione a conta" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {accounts.map((acc) => (
                                                <SelectItem key={acc.uuid} value={acc.uuid}>
                                                    {acc.name} ({formatCurrency(acc.current_balance)})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="flex gap-2">
                                    <Button type="submit" disabled={form.processing}>Confirmar Pagamento</Button>
                                    <Button variant="outline" onClick={() => setShowPayment(false)}>Cancelar</Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {bill.status === 'paid' && (
                    <Button variant="outline" onClick={handleUnpay}>Desfazer Pagamento</Button>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
