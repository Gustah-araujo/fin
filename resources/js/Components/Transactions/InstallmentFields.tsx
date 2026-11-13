import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { InertiaForm } from '@inertiajs/react';

interface InstallmentFieldsProps {
    description: string;
    form: InertiaForm<{
        installments: number;
        installment_day: string;
        total_value: string;
    }>;
}

function formatCurrency(value: number): string {
    return value.toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });
}

export function InstallmentFields({
    description,
    form,
}: InstallmentFieldsProps) {
    const installments = form.data.installments;
    const total = parseFloat(form.data.total_value) || 0;

    const showPreview = installments > 1 && total > 0;

    const baseValue = showPreview
        ? Math.round((total * 100) / installments) / 100
        : 0;
    const lastValue = showPreview
        ? Math.round((total - baseValue * (installments - 1)) * 100) / 100
        : 0;

    return (
        <div className="space-y-4 rounded-lg border p-3">
            <div className="space-y-2">
                <Label htmlFor="installments">Parcelas</Label>
                <Input
                    id="installments"
                    type="number"
                    min={2}
                    max={48}
                    value={installments}
                    onChange={(e) =>
                        form.setData(
                            'installments',
                            Math.max(
                                2,
                                Math.min(48, Number(e.target.value) || 2),
                            ),
                        )
                    }
                />
                {form.errors.installments && (
                    <p className="text-sm text-destructive">
                        {form.errors.installments}
                    </p>
                )}
            </div>

            <div className="space-y-2">
                <Label htmlFor="total_value">Valor total</Label>
                <Input
                    id="total_value"
                    type="number"
                    step="0.01"
                    min="0.01"
                    value={form.data.total_value}
                    onChange={(e) =>
                        form.setData('total_value', e.target.value)
                    }
                    placeholder="0,00"
                />
                {form.errors.total_value && (
                    <p className="text-sm text-destructive">
                        {form.errors.total_value}
                    </p>
                )}
            </div>

            <div className="space-y-2">
                <Label htmlFor="installment_day">Dia do mês</Label>
                <Input
                    id="installment_day"
                    type="number"
                    min="1"
                    max="31"
                    value={form.data.installment_day}
                    onChange={(e) =>
                        form.setData('installment_day', e.target.value)
                    }
                    placeholder="Ex: 5"
                />
                <p className="text-xs text-muted-foreground">
                    Opcional — deixe vazio para usar o dia da data informada
                </p>
                {form.errors.installment_day && (
                    <p className="text-sm text-destructive">
                        {form.errors.installment_day}
                    </p>
                )}
            </div>

            {showPreview && (
                <div className="space-y-1 text-xs text-muted-foreground">
                    <p>
                        Serão lançadas {installments} parcelas de{' '}
                        <span className="font-semibold text-foreground">
                            {formatCurrency(baseValue)}
                        </span>
                        {lastValue !== baseValue && (
                            <>
                                {' '}
                                (última parcela de {formatCurrency(lastValue)})
                            </>
                        )}
                    </p>
                    <p>
                        Exemplo: "{description || 'Despesa'} (1/
                        {installments})"
                    </p>
                </div>
            )}
        </div>
    );
}
