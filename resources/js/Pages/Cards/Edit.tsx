import { Link, useForm, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface CreditCard {
    uuid: string;
    name: string;
    credit_limit: number;
    available_limit: number;
    closing_day: number;
    due_day: number;
}

interface Props {
    card: CreditCard;
}

export default function Edit({ card }: Props) {
    const { workspace } = usePage<{ workspace: { uuid: string; name: string } }>().props;
    const { data, setData, put, processing, errors } = useForm({
        name: card.name,
        credit_limit: String(card.credit_limit),
        closing_day: String(card.closing_day),
        due_day: String(card.due_day),
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(route('cards.update', { workspace: workspace.uuid, card: card.uuid }));
    }

    return (
        <AuthenticatedLayout>
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Editar Cartão</h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        Altere os dados do cartão {card.name}
                    </p>
                </div>

                <Card className="max-w-lg">
                    <CardHeader>
                        <CardTitle>Dados do Cartão</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">Nome do Cartão</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                />
                                {errors.name && (
                                    <p className="text-sm text-destructive">{errors.name}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="credit_limit">Limite</Label>
                                <Input
                                    id="credit_limit"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={data.credit_limit}
                                    onChange={(e) => setData('credit_limit', e.target.value)}
                                />
                                {errors.credit_limit && (
                                    <p className="text-sm text-destructive">
                                        {errors.credit_limit}
                                    </p>
                                )}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="closing_day">Dia de Fechamento</Label>
                                    <Input
                                        id="closing_day"
                                        type="number"
                                        min="1"
                                        max="31"
                                        value={data.closing_day}
                                        onChange={(e) => setData('closing_day', e.target.value)}
                                    />
                                    {errors.closing_day && (
                                        <p className="text-sm text-destructive">
                                            {errors.closing_day}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="due_day">Dia de Vencimento</Label>
                                    <Input
                                        id="due_day"
                                        type="number"
                                        min="1"
                                        max="31"
                                        value={data.due_day}
                                        onChange={(e) => setData('due_day', e.target.value)}
                                    />
                                    {errors.due_day && (
                                        <p className="text-sm text-destructive">
                                            {errors.due_day}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="flex items-center gap-3 pt-2">
                                <Button type="submit" disabled={processing}>
                                    Salvar
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={route('cards.index', {
                                            workspace: workspace.uuid,
                                        })}
                                    >
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
