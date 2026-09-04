import { useEffect, useState } from 'react';
import axios from 'axios';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useWorkspace } from '@/hooks/useWorkspace';
import { formatCurrency } from '@/lib/format-currency';
import type { MonthDetail } from '@/types/planning';

interface Props {
    month: string;
    isOpen: boolean;
    onClose: () => void;
}

export default function MonthDetailModal({ month, isOpen, onClose }: Props) {
    const workspace = useWorkspace();
    const [detail, setDetail] = useState<MonthDetail | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!isOpen || !month) return;

        setLoading(true);
        setError(null);
        setDetail(null);

        axios
            .get(
                route('planning.month-detail', {
                    workspace: workspace.uuid,
                }),
                { params: { month } },
            )
            .then((res) => setDetail(res.data))
            .catch(() => setError('Erro ao carregar detalhamento'))
            .finally(() => setLoading(false));
    }, [isOpen, month, workspace.uuid]);

    const formatDate = (dateStr: string): string =>
        new Date(dateStr + 'T00:00:00').toLocaleDateString('pt-BR');

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-2xl max-h-[80vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Detalhamento — {month}</DialogTitle>
                </DialogHeader>

                {loading && (
                    <div className="py-8 text-center text-muted-foreground">
                        Carregando...
                    </div>
                )}

                {error && (
                    <div className="py-8 text-center text-destructive">
                        {error}
                    </div>
                )}

                {detail && !loading && (
                    <div className="space-y-6">
                        {detail.expenses.length > 0 && (
                            <div>
                                <h3 className="font-semibold mb-2 text-destructive">
                                    Gastos
                                </h3>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Descrição</TableHead>
                                            <TableHead className="text-right">
                                                Valor
                                            </TableHead>
                                            <TableHead>Categoria</TableHead>
                                            <TableHead>Data</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {detail.expenses.map((t) => (
                                            <TableRow key={t.id}>
                                                <TableCell>
                                                    {t.description}
                                                </TableCell>
                                                <TableCell className="text-right text-destructive font-semibold whitespace-nowrap">
                                                    {formatCurrency(t.value)}
                                                </TableCell>
                                                <TableCell>
                                                    {t.category ?? '—'}
                                                </TableCell>
                                                <TableCell className="whitespace-nowrap">
                                                    {formatDate(t.date)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}

                        {detail.incomes.length > 0 && (
                            <div>
                                <h3 className="font-semibold mb-2 text-emerald-600">
                                    Rendas
                                </h3>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Descrição</TableHead>
                                            <TableHead className="text-right">
                                                Valor
                                            </TableHead>
                                            <TableHead>Categoria</TableHead>
                                            <TableHead>Data</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {detail.incomes.map((t) => (
                                            <TableRow key={t.id}>
                                                <TableCell>
                                                    {t.description}
                                                </TableCell>
                                                <TableCell className="text-right text-emerald-600 font-semibold whitespace-nowrap">
                                                    {formatCurrency(t.value)}
                                                </TableCell>
                                                <TableCell>
                                                    {t.category ?? '—'}
                                                </TableCell>
                                                <TableCell className="whitespace-nowrap">
                                                    {formatDate(t.date)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}

                        {detail.expenses.length === 0 &&
                            detail.incomes.length === 0 && (
                                <p className="text-center text-muted-foreground py-8">
                                    Nenhuma transação neste mês
                                </p>
                            )}
                    </div>
                )}

                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        Fechar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
