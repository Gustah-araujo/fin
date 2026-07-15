import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatCurrency } from '@/lib/format-currency';
import { Link, usePage, useForm } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { useState } from 'react';

interface CardDetail {
    uuid: string;
    name: string;
}

interface TransactionData {
    uuid: string;
    description: string;
    value: number;
    date: string;
    installment_number: number | null;
    installments_total: number | null;
    category: { uuid: string; name: string } | null;
}

interface Category {
    uuid: string;
    name: string;
}

interface Props {
    card: CardDetail;
    transaction: TransactionData;
    categories: Category[];
    tags: { uuid: string; name: string }[];
}

export default function Edit({ card, transaction, categories }: Props) {
    const { workspace } = usePage<{ workspace: { uuid: string; name: string } }>().props;
    const isInstallment = transaction.installments_total !== null && transaction.installments_total > 1;
    const [scope, setScope] = useState('single');

    const form = useForm({
        description: transaction.description,
        value: String(transaction.value),
        date: transaction.date,
        category_id: transaction.category?.uuid ?? '',
        tags: [] as string[],
        scope: 'single',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        form.data.scope = scope;
        form.put(route('card-expenses.update', { workspace: workspace.uuid, card: card.uuid, transaction: transaction.uuid }));
    }

    return (
        <AuthenticatedLayout>
            <div className="max-w-2xl mx-auto space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Editar Compra</h1>
                </div>

                {isInstallment && (
                    <Card>
                        <CardContent className="py-4">
                            <Label>Escopo da edição</Label>
                            <RadioGroup value={scope} onValueChange={setScope} className="mt-2">
                                <div className="flex items-center gap-2">
                                    <RadioGroupItem value="single" id="single" />
                                    <Label htmlFor="single">Apenas esta parcela ({transaction.installment_number}/{transaction.installments_total})</Label>
                                </div>
                                <div className="flex items-center gap-2">
                                    <RadioGroupItem value="group" id="group" />
                                    <Label htmlFor="group">Esta e futuras</Label>
                                </div>
                            </RadioGroup>
                        </CardContent>
                    </Card>
                )}

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
                                <Label htmlFor="value">Valor</Label>
                                <Input
                                    id="value"
                                    type="number"
                                    step="0.01"
                                    value={form.data.value}
                                    onChange={(e) => form.setData('value', e.target.value)}
                                />
                                {form.errors.value && <p className="text-sm text-destructive mt-1">{form.errors.value}</p>}
                            </div>

                            <div>
                                <Label htmlFor="date">Data</Label>
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
