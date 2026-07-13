import { Link, useForm, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatCurrency } from '@/lib/format-currency';

interface Account {
    uuid: string;
    name: string;
    type: string;
    initial_balance: number;
    current_balance: number;
    created_at: string;
}

interface Props {
    accounts: Account[];
}

const typeBadgeStyles: Record<string, string> = {
    checking: 'bg-blue-100 text-blue-800 border-blue-200',
    savings: 'bg-emerald-100 text-emerald-800 border-emerald-200',
    investment: 'bg-amber-100 text-amber-800 border-amber-200',
};

const typeLabels: Record<string, string> = {
    checking: 'Corrente',
    savings: 'Poupança',
    investment: 'Investimento',
};

export default function Index({ accounts }: Props) {
    const { workspace } = usePage<{ workspace: { uuid: string; name: string } }>().props;
    const { delete: destroy } = useForm();

    function handleDelete(accountUuid: string) {
        destroy(route('accounts.destroy', { workspace: workspace.uuid, account: accountUuid }), {
            onSuccess: () => window.location.reload(),
        });
    }

    return (
        <AuthenticatedLayout>
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Contas</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            {accounts.length} conta{accounts.length !== 1 ? 's' : ''}
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={route('accounts.create', { workspace: workspace.uuid })}>
                            Nova Conta
                        </Link>
                    </Button>
                </div>

                {accounts.length === 0 ? (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <p className="text-sm text-muted-foreground mb-4">
                                Nenhuma conta cadastrada
                            </p>
                            <Button asChild>
                                <Link href={route('accounts.create', { workspace: workspace.uuid })}>
                                    Criar primeira conta
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {accounts.map((account) => (
                            <Card key={account.uuid}>
                                <CardHeader className="pb-2">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0 flex-1">
                                            <CardTitle className="text-lg font-semibold truncate">
                                                {account.name}
                                            </CardTitle>
                                        </div>
                                        <Badge
                                            variant="outline"
                                            className={typeBadgeStyles[account.type] ?? ''}
                                        >
                                            {typeLabels[account.type] ?? account.type}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-2xl font-semibold">
                                        {formatCurrency(account.current_balance)}
                                    </p>
                                    <div className="mt-4 flex items-center gap-2">
                                        <Button variant="outline" size="sm" asChild>
                                            <Link
                                                href={route('accounts.edit', {
                                                    workspace: workspace.uuid,
                                                    account: account.uuid,
                                                })}
                                            >
                                                Editar
                                            </Link>
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            onClick={() => handleDelete(account.uuid)}
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
