import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatCurrency } from '@/lib/format-currency';
import { Link, usePage, useForm } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useState } from 'react';

interface CardDetail {
    uuid: string;
    name: string;
    credit_limit: number;
    available_limit: number;
    closing_day: number;
    due_day: number;
}

interface Category {
    uuid: string;
    name: string;
    color: string;
}

interface Tag {
    uuid: string;
    name: string;
    color: string;
}

interface Props {
    card: CardDetail;
    categories: Category[];
    tags: Tag[];
}

export default function Create({ card, categories, tags }: Props) {
    const { workspace } = usePage<{ workspace: { uuid: string; name: string } }>().props;
    const [installments, setInstallments] = useState(1);
    const [totalValue, setTotalValue] = useState('');

    const form = useForm({
        description: '',
        value: '',
        total_value: '',
        date: new Date().toISOString().split('T')[0],
        credit_card_id: card.uuid,
        category_id: '',
        installments: 1,
        tags: [] as string[],
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        if (installments > 1) {
            form.installments = installments;
            form.total_value = totalValue;
        } else {
            form.installments = 1;
        }
        form.post(route('card-expenses.store', { workspace: workspace.uuid, card: card.uuid }));
    }

    const perInstallment = installments > 1 && totalValue
        ? formatCurrency(parseFloat(totalValue) / installments)
        : null;

    return (
        <AuthenticatedLayout>
            <div className="max-w-2xl mx-auto space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Nova Compra no Cartão</h1>
                    <p className="text-sm text-muted-foreground mt-1">{card.name}</p>
                </div>

                <Card>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <Label htmlFor="description">Descrição</Label>
                                <Input
                                    id="description"
                                    value={form.data.description}
                                    onChange={(e) => form.setData('description', e.target.value)}
                                />
                                {form.errors.description && <p className="text-sm text-destructive mt-1">{form.errors.description}</p>}
                            </div>

                            <div>
                                <Label htmlFor="installments">Parcelas</Label>
                                <Input
                                    id="installments"
                                    type="number"
                                    min={1}
                                    max={48}
                                    value={installments}
                                    onChange={(e) => setInstallments(parseInt(e.target.value) || 1)}
                                />
                                {form.errors.installments && <p className="text-sm text-destructive mt-1">{form.errors.installments}</p>}
                            </div>

                            <div>
                                <Label htmlFor="value">
                                    {installments > 1 ? 'Valor Total' : 'Valor'}
                                </Label>
                                <Input
                                    id="value"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    value={installments > 1 ? totalValue : form.data.value}
                                    onChange={(e) => {
                                        if (installments > 1) {
                                            setTotalValue(e.target.value);
                                        } else {
                                            form.setData('value', e.target.value);
                                        }
                                    }}
                                />
                                {perInstallment && (
                                    <p className="text-xs text-muted-foreground mt-1">
                                        Valor por parcela: {perInstallment}
                                    </p>
                                )}
                                {form.errors.value && <p className="text-sm text-destructive mt-1">{form.errors.value}</p>}
                                {form.errors.total_value && <p className="text-sm text-destructive mt-1">{form.errors.total_value}</p>}
                            </div>

                            <div>
                                <Label htmlFor="date">
                                    {installments > 1 ? 'Data da primeira parcela' : 'Data'}
                                </Label>
                                <Input
                                    id="date"
                                    type="date"
                                    value={form.data.date}
                                    onChange={(e) => form.setData('date', e.target.value)}
                                />
                                {form.errors.date && <p className="text-sm text-destructive mt-1">{form.errors.date}</p>}
                            </div>

                            <div>
                                <Label htmlFor="category_id">Categoria</Label>
                                <Select value={form.data.category_id} onValueChange={(v) => form.setData('category_id', v)}>
                                    <SelectTrigger id="category_id">
                                        <SelectValue placeholder="Selecione" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {categories.map((cat) => (
                                            <SelectItem key={cat.uuid} value={cat.uuid}>{cat.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {form.errors.category_id && <p className="text-sm text-destructive mt-1">{form.errors.category_id}</p>}
                            </div>

                            <div className="flex gap-2 pt-4">
                                <Button type="submit" disabled={form.processing}>Salvar</Button>
                                <Button variant="outline" asChild>
                                    <Link href={route('cards.show', { workspace: workspace.uuid, card: card.uuid })}>
                                        Cancelar
                                    </Link>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
