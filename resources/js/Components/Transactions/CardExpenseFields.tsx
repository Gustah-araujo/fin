import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Label } from '@/components/ui/label';
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
    }>;
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
    preselectedCardId,
}: CardExpenseFieldsProps) {
    return (
        <div className="space-y-2">
            <Label htmlFor="credit_card_id">Cartão</Label>
            <Select
                value={form.data.credit_card_id || preselectedCardId || ''}
                onValueChange={(value) => form.setData('credit_card_id', value)}
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
    );
}
