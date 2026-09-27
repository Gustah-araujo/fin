import { Lock } from 'lucide-react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { InertiaForm } from '@inertiajs/react';

interface CardOption {
    id: string;
    name: string;
    credit_limit: number;
    available_limit: number;
}

interface CardExpenseFieldsProps {
    cards: CardOption[];
    form: InertiaForm<{
        credit_card_id: string;
        installments: number;
        total_value: string;
    }>;
    locked?: boolean;
    preselectedCardId?: string;
}

function formatCurrency(value: number): string {
    return value.toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });
}

export function CardExpenseFields({
    cards,
    form,
    locked = false,
    preselectedCardId,
}: CardExpenseFieldsProps) {
    const installments = form.data.installments;
    const totalValue = parseFloat(form.data.total_value) || 0;
    const installmentValue =
        installments > 1 && totalValue > 0 ? totalValue / installments : null;

    return (
        <div className="space-y-4">
            {/* Card select */}
            <div className="space-y-2">
                <Label htmlFor="credit_card_id">Cartão</Label>
                <Select
                    value={form.data.credit_card_id || preselectedCardId || ''}
                    onValueChange={(value) =>
                        form.setData('credit_card_id', value)
                    }
                >
                    <SelectTrigger id="credit_card_id">
                        <SelectValue placeholder="Selecione o cartão" />
                    </SelectTrigger>
                    <SelectContent>
                        {cards.map((card) => (
                            <SelectItem key={card.id} value={card.id}>
                                {card.name} — Disponível:{' '}
                                {formatCurrency(card.available_limit)}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {form.errors.credit_card_id && (
                    <p className="text-sm text-destructive">
                        {form.errors.credit_card_id}
                    </p>
                )}
            </div>

            {/* Installments input */}
            <div className="space-y-2">
                <Label
                    htmlFor="installments"
                    className={cn(locked && 'opacity-60')}
                >
                    Parcelas
                    {locked && (
                        <span className="ml-1.5 inline-flex items-center gap-1 text-xs text-muted-foreground">
                            <Lock className="size-3" />
                            Recorrência é sempre 1x
                        </span>
                    )}
                </Label>
                <Input
                    id="installments"
                    type="number"
                    min={1}
                    max={48}
                    disabled={locked}
                    value={form.data.installments}
                    onChange={(e) =>
                        form.setData(
                            'installments',
                            Math.max(
                                1,
                                Math.min(48, Number(e.target.value) || 1),
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

            {/* Total value (shown when installments > 1) */}
            {installments > 1 && (
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
                    {installmentValue !== null && (
                        <p className="text-xs text-muted-foreground">
                            Valor da parcela: {formatCurrency(installmentValue)}
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}
