import { Link, useForm } from '@inertiajs/react';
import { useWorkspace } from '@/hooks/useWorkspace';
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

interface RecurrenceItem {
    id: string;
    description: string;
    value: number;
    frequency: string;
    frequency_day: number;
    start_date: string;
    until_date: string | null;
    next_date: string | null;
    status: string;
    account: AccountItem | null;
    category: CategoryItem | null;
    tags: TagItem[];
}

interface Props {
    recurrence: RecurrenceItem;
    accounts: AccountItem[];
    categories: CategoryItem[];
    tags: TagItem[];
    has_transactions: boolean;
}

const WEEKDAYS = [
    { value: 0, label: 'Domingo' },
    { value: 1, label: 'Segunda' },
    { value: 2, label: 'Terça' },
    { value: 3, label: 'Quarta' },
    { value: 4, label: 'Quinta' },
    { value: 5, label: 'Sexta' },
    { value: 6, label: 'Sábado' },
];

export default function Edit({
    recurrence,
    accounts,
    categories,
    tags,
    has_transactions,
}: Props) {
    const workspace = useWorkspace();

    const { data, setData, put, processing, errors } = useForm({
        description: recurrence.description,
        value: String(recurrence.value),
        account_id: recurrence.account?.uuid ?? '',
        category_id: recurrence.category?.uuid ?? '',
        frequency: recurrence.frequency as 'weekly' | 'monthly',
        frequency_day: recurrence.frequency_day,
        start_date: recurrence.start_date,
        until_date: recurrence.until_date ?? '',
        tags: recurrence.tags.map((t) => t.uuid),
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(
            route('recurrences.update', {
                workspace: workspace.uuid,
                recurrence: recurrence.id,
            }),
        );
    }

    function toggleTag(uuid: string) {
        if (data.tags.includes(uuid)) {
            setData(
                'tags',
                data.tags.filter((t) => t !== uuid),
            );
        } else {
            setData('tags', [...data.tags, uuid]);
        }
    }

    return (
        <AuthenticatedLayout>
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Editar Recorrência
                    </h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        {recurrence.description}
                    </p>
                </div>

                <Card className="max-w-lg">
                    <CardHeader>
                        <CardTitle>Regra da Recorrência</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="description">Descrição</Label>
                                <Input
                                    id="description"
                                    value={data.description}
                                    onChange={(e) =>
                                        setData('description', e.target.value)
                                    }
                                />
                                {errors.description && (
                                    <p className="text-sm text-destructive">
                                        {errors.description}
                                    </p>
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
                                    onChange={(e) =>
                                        setData('value', e.target.value)
                                    }
                                />
                                {errors.value && (
                                    <p className="text-sm text-destructive">
                                        {errors.value}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="account_id">Conta</Label>
                                <Select
                                    value={data.account_id}
                                    onValueChange={(value) =>
                                        setData('account_id', value)
                                    }
                                >
                                    <SelectTrigger id="account_id">
                                        <SelectValue placeholder="Selecione a conta" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {accounts.map((account) => (
                                            <SelectItem
                                                key={account.uuid}
                                                value={account.uuid}
                                            >
                                                {account.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.account_id && (
                                    <p className="text-sm text-destructive">
                                        {errors.account_id}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="category_id">Categoria</Label>
                                <Select
                                    value={data.category_id}
                                    onValueChange={(value) =>
                                        setData('category_id', value)
                                    }
                                >
                                    <SelectTrigger id="category_id">
                                        <SelectValue placeholder="Selecione a categoria" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {categories.map((category) => (
                                            <SelectItem
                                                key={category.uuid}
                                                value={category.uuid}
                                            >
                                                {category.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.category_id && (
                                    <p className="text-sm text-destructive">
                                        {errors.category_id}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label>Frequência</Label>
                                <Select
                                    value={data.frequency}
                                    onValueChange={(value) =>
                                        setData(
                                            'frequency',
                                            value as 'weekly' | 'monthly',
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="weekly">
                                            Semanal
                                        </SelectItem>
                                        <SelectItem value="monthly">
                                            Mensal
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                {errors.frequency && (
                                    <p className="text-sm text-destructive">
                                        {errors.frequency}
                                    </p>
                                )}
                            </div>

                            {data.frequency === 'weekly' ? (
                                <div className="space-y-2">
                                    <Label>Dia da semana</Label>
                                    <Select
                                        value={String(data.frequency_day)}
                                        onValueChange={(value) =>
                                            setData(
                                                'frequency_day',
                                                Number(value),
                                            )
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {WEEKDAYS.map((day) => (
                                                <SelectItem
                                                    key={day.value}
                                                    value={String(day.value)}
                                                >
                                                    {day.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    <Label htmlFor="frequency_day">
                                        Dia do mês
                                    </Label>
                                    <Input
                                        id="frequency_day"
                                        type="number"
                                        min="1"
                                        max="31"
                                        value={data.frequency_day}
                                        onChange={(e) =>
                                            setData(
                                                'frequency_day',
                                                Number(e.target.value),
                                            )
                                        }
                                    />
                                </div>
                            )}
                            {errors.frequency_day && (
                                <p className="text-sm text-destructive">
                                    {errors.frequency_day}
                                </p>
                            )}

                            <div className="space-y-2">
                                <Label htmlFor="start_date">
                                    Data de início
                                </Label>
                                <Input
                                    id="start_date"
                                    type="date"
                                    value={data.start_date}
                                    disabled={has_transactions}
                                    onChange={(e) =>
                                        setData('start_date', e.target.value)
                                    }
                                />
                                {has_transactions && (
                                    <p className="text-xs text-muted-foreground">
                                        Não pode ser alterada pois já existem
                                        transações geradas.
                                    </p>
                                )}
                                {errors.start_date && (
                                    <p className="text-sm text-destructive">
                                        {errors.start_date}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="until_date">Data final</Label>
                                <Input
                                    id="until_date"
                                    type="date"
                                    value={data.until_date}
                                    onChange={(e) =>
                                        setData('until_date', e.target.value)
                                    }
                                />
                                <p className="text-xs text-muted-foreground">
                                    Deixe vazio para recorrência infinita.
                                </p>
                                {errors.until_date && (
                                    <p className="text-sm text-destructive">
                                        {errors.until_date}
                                    </p>
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
                                                onClick={() =>
                                                    toggleTag(tag.uuid)
                                                }
                                            >
                                                <span
                                                    className="w-2.5 h-2.5 rounded-full"
                                                    style={{
                                                        backgroundColor:
                                                            tag.color,
                                                    }}
                                                />
                                                {tag.name}
                                            </div>
                                        ))}
                                    </div>
                                    {errors.tags && (
                                        <p className="text-sm text-destructive">
                                            {errors.tags}
                                        </p>
                                    )}
                                </div>
                            )}

                            <div className="flex items-center gap-3 pt-2">
                                <Button type="submit" disabled={processing}>
                                    Salvar
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={route('recurrences.index', {
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
