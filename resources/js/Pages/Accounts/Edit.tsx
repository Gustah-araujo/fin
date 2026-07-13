import { Link, useForm, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface Account {
    uuid: string;
    name: string;
    type: string;
    initial_balance: number;
    current_balance: number;
}

interface Props {
    account: Account;
}

export default function Edit({ account }: Props) {
    const { workspace } = usePage<{ workspace: { uuid: string; name: string } }>().props;
    const { data, setData, put, processing, errors } = useForm({
        name: account.name,
        type: account.type,
        initial_balance: String(account.initial_balance),
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(route('accounts.update', { workspace: workspace.uuid, account: account.uuid }));
    }

    return (
        <AuthenticatedLayout>
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Editar Conta</h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        Altere os dados da conta {account.name}
                    </p>
                </div>

                <Card className="max-w-lg">
                    <CardHeader>
                        <CardTitle>Dados da Conta</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">Nome da Conta</Label>
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
                                <Label htmlFor="type">Tipo da Conta</Label>
                                <Select
                                    value={data.type}
                                    onValueChange={(value) => setData('type', value)}
                                >
                                    <SelectTrigger id="type">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="checking">Corrente</SelectItem>
                                        <SelectItem value="savings">Poupança</SelectItem>
                                        <SelectItem value="investment">Investimento</SelectItem>
                                    </SelectContent>
                                </Select>
                                {errors.type && (
                                    <p className="text-sm text-destructive">{errors.type}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="initial_balance">Saldo Inicial</Label>
                                <Input
                                    id="initial_balance"
                                    type="number"
                                    step="0.01"
                                    value={data.initial_balance}
                                    onChange={(e) => setData('initial_balance', e.target.value)}
                                />
                                {errors.initial_balance && (
                                    <p className="text-sm text-destructive">
                                        {errors.initial_balance}
                                    </p>
                                )}
                            </div>

                            <div className="flex items-center gap-3 pt-2">
                                <Button type="submit" disabled={processing}>
                                    Salvar
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={route('accounts.index', {
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
