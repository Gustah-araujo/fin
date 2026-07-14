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

interface AccountItem {
    uuid: string;
    name: string;
    type: string;
    current_balance: number;
}

interface CategoryItem {
    uuid: string;
    name: string;
    type: string;
    color: string;
    icon: string | null;
}

interface TagItem {
    uuid: string;
    name: string;
    color: string;
}

interface Props {
    accounts: AccountItem[];
    categories: CategoryItem[];
    tags: TagItem[];
}

export default function Create({ accounts, categories, tags }: Props) {
    const { workspace } = usePage<{ workspace: { uuid: string } }>().props;

    const today = new Date().toISOString().split('T')[0];

    const { data, setData, post, processing, errors } = useForm({
        description: '',
        value: '',
        date: today,
        account_id: '',
        category_id: '',
        tags: [] as string[],
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post(route('transactions.store', { workspace: workspace.uuid }));
    }

    function toggleTag(uuid: string) {
        if (data.tags.includes(uuid)) {
            setData('tags', data.tags.filter((t) => t !== uuid));
        } else {
            setData('tags', [...data.tags, uuid]);
        }
    }

    return (
        <AuthenticatedLayout>
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Nova Despesa</h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        Registre uma despesa em débito
                    </p>
                </div>

                <Card className="max-w-lg">
                    <CardHeader>
                        <CardTitle>Dados da Despesa</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="description">Descrição</Label>
                                <Input
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Ex: Compra no supermercado"
                                />
                                {errors.description && (
                                    <p className="text-sm text-destructive">{errors.description}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="value">Valor</Label>
                                <Input
                                    id="value"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    value={data.value}
                                    onChange={(e) => setData('value', e.target.value)}
                                    placeholder="0,00"
                                />
                                {errors.value && (
                                    <p className="text-sm text-destructive">{errors.value}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="date">Data</Label>
                                <Input
                                    id="date"
                                    type="date"
                                    value={data.date}
                                    onChange={(e) => setData('date', e.target.value)}
                                />
                                {errors.date && (
                                    <p className="text-sm text-destructive">{errors.date}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="account_id">Conta</Label>
                                <Select
                                    value={data.account_id}
                                    onValueChange={(value) => setData('account_id', value)}
                                >
                                    <SelectTrigger id="account_id">
                                        <SelectValue placeholder="Selecione a conta" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {accounts.map((account) => (
                                            <SelectItem key={account.uuid} value={account.uuid}>
                                                {account.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.account_id && (
                                    <p className="text-sm text-destructive">{errors.account_id}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="category_id">Categoria</Label>
                                <Select
                                    value={data.category_id}
                                    onValueChange={(value) => setData('category_id', value)}
                                >
                                    <SelectTrigger id="category_id">
                                        <SelectValue placeholder="Selecione a categoria" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {categories.map((category) => (
                                            <SelectItem key={category.uuid} value={category.uuid}>
                                                {category.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.category_id && (
                                    <p className="text-sm text-destructive">{errors.category_id}</p>
                                )}
                            </div>

                            {tags.length > 0 && (
                                <div className="space-y-2">
                                    <Label>Tags</Label>
                                    <div className="flex flex-wrap gap-2">
                                        {tags.map((tag) => (
                                            <div
                                                key={tag.uuid}
                                                className={`inline-flex items-center gap-2 px-3 py-1.5 rounded-md border cursor-pointer text-sm transition-colors ${
                                                    data.tags.includes(tag.uuid)
                                                        ? 'bg-primary text-primary-foreground border-primary'
                                                        : 'bg-background hover:bg-accent'
                                                }`}
                                                onClick={() => toggleTag(tag.uuid)}
                                            >
                                                <span
                                                    className="w-2.5 h-2.5 rounded-full"
                                                    style={{ backgroundColor: tag.color }}
                                                />
                                                {tag.name}
                                            </div>
                                        ))}
                                    </div>
                                    {errors.tags && (
                                        <p className="text-sm text-destructive">{errors.tags}</p>
                                    )}
                                </div>
                            )}

                            <div className="flex items-center gap-3 pt-2">
                                <Button type="submit" disabled={processing}>
                                    Criar Despesa
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={route('transactions.index', {
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
