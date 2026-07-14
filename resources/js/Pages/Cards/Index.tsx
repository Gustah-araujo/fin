import { Link, useForm, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatCurrency } from '@/lib/format-currency';

interface CreditCard {
    uuid: string;
    name: string;
    credit_limit: number;
    available_limit: number;
    closing_day: number;
    due_day: number;
    created_at: string;
}

interface Props {
    cards: CreditCard[];
}

export default function Index({ cards }: Props) {
    const { workspace } = usePage<{ workspace: { uuid: string; name: string } }>().props;
    const { delete: destroy } = useForm();

    function handleDelete(cardUuid: string) {
        destroy(route('cards.destroy', { workspace: workspace.uuid, card: cardUuid }), {
            onSuccess: () => window.location.reload(),
        });
    }

    return (
        <AuthenticatedLayout>
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Cartões</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            {cards.length} cartão{cards.length !== 1 ? 'ões' : ''}
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={route('cards.create', { workspace: workspace.uuid })}>
                            Novo Cartão
                        </Link>
                    </Button>
                </div>

                {cards.length === 0 ? (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <p className="text-sm text-muted-foreground mb-4">
                                Nenhum cartão cadastrado
                            </p>
                            <Button asChild>
                                <Link href={route('cards.create', { workspace: workspace.uuid })}>
                                    Criar primeiro cartão
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {cards.map((card) => (
                            <Card key={card.uuid}>
                                <CardHeader className="pb-2">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0 flex-1">
                                            <CardTitle className="text-lg font-semibold truncate">
                                                {card.name}
                                            </CardTitle>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="flex items-baseline justify-between">
                                        <div>
                                            <p className="text-xs text-muted-foreground">Limite</p>
                                            <p className="text-lg font-semibold">
                                                {formatCurrency(card.credit_limit)}
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-xs text-muted-foreground">Disponível</p>
                                            <p className="text-lg font-semibold text-emerald-600">
                                                {formatCurrency(card.available_limit)}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="mt-4 flex items-center gap-2">
                                        <Badge variant="outline">
                                            Fechamento: Dia {card.closing_day}
                                        </Badge>
                                        <Badge variant="outline">
                                            Vencimento: Dia {card.due_day}
                                        </Badge>
                                    </div>
                                    <div className="mt-4 flex items-center gap-2">
                                        <Button variant="outline" size="sm" asChild>
                                            <Link
                                                href={route('cards.edit', {
                                                    workspace: workspace.uuid,
                                                    card: card.uuid,
                                                })}
                                            >
                                                Editar
                                            </Link>
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            onClick={() => handleDelete(card.uuid)}
                                        >
                                            Excluir
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
