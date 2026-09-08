import { Link, useForm } from '@inertiajs/react';
import { useWorkspace } from '@/hooks/useWorkspace';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Checkbox } from '@/components/ui/checkbox';
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

const WEEKDAYS = [
    { value: 0, label: 'Domingo' },
    { value: 1, label: 'Segunda' },
    { value: 2, label: 'Terça' },
    { value: 3, label: 'Quarta' },
    { value: 4, label: 'Quinta' },
    { value: 5, label: 'Sexta' },
    { value: 6, label: 'Sábado' },
];

export default function Create({ accounts, categories, tags }: Props) {
    const workspace = useWorkspace();

    const today = new Date().toISOString().split('T')[0];

    const { data, setData, post, processing, errors } = useForm({
        description: '',
        value: '',
        date: today,
        account_id: '',
        category_id: '',
        tags: [] as string[],
        is_recurring: false,
        frequency: 'monthly' as 'weekly' | 'monthly',
        frequency_day: 1,
        until_date: '',
        has_until_date: false,
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();

        const payload: Record<string, unknown> = {
            description: data.description,
            value: data.value,
            date: data.date,
            account_id: data.account_id,
            category_id: data.category_id,
            tags: data.tags,
            is_recurring: data.is_recurring,
        };

        if (data.is_recurring) {
            payload.frequency = data.frequency;
            payload.frequency_day = data.frequency_day;
            if (data.has_until_date) {
                payload.until_date = data.until_date;
            }
        }

        post(route('incomes.store', { workspace: workspace.uuid }), payload);
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
                        Nova Receita
                    </h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        Registre uma receita avulsa ou recorrente
                    </p>
                </div>

                <Card className="max-w-lg">
                    <CardHeader>
                        <CardTitle>Dados da Receita</CardTitle>
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
                                    placeholder="Ex: Salário, Freelance"
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
                                    placeholder="0,00"
                                />
                                {errors.value && (
                                    <p className="text-sm text-destructive">
                                        {errors.value}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="date">
                                    {data.is_recurring
                                        ? 'Data de início'
                                        : 'Data'}
                                </Label>
                                <Input
                                    id="date"
                                    type="date"
                                    value={data.date}
                                    onChange={(e) =>
                                        setData('date', e.target.value)
                                    }
                                />
                                {errors.date && (
                                    <p className="text-sm text-destructive">
                                        {errors.date}
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

                            <div className="flex items-center justify-between rounded-lg border p-3">
                                <div className="space-y-0.5">
                                    <Label htmlFor="is_recurring">
                                        É recorrente?
                                    </Label>
                                    <p className="text-xs text-muted-foreground">
                                        Repetir esta receita automaticamente
                                    </p>
                                </div>
                                <Switch
                                    id="is_recurring"
                                    checked={data.is_recurring}
                                    onCheckedChange={(checked) =>
                                        setData('is_recurring', checked)
                                    }
                                />
                            </div>

                            {data.is_recurring && (
                                <div className="space-y-4 rounded-lg border p-3">
                                    <div className="space-y-2">
                                        <Label>Frequência</Label>
                                        <Select
                                            value={data.frequency}
                                            onValueChange={(value) =>
                                                setData(
                                                    'frequency',
                                                    value as
                                                        'weekly' | 'monthly',
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
                                                value={String(
                                                    data.frequency_day,
                                                )}
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
                                                            value={String(
                                                                day.value,
                                                            )}
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

                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id="has_until_date"
                                            checked={data.has_until_date}
                                            onCheckedChange={(checked) =>
                                                setData(
                                                    'has_until_date',
                                                    checked,
                                                )
                                            }
                                        />
                                        <Label
                                            htmlFor="has_until_date"
                                            className="text-sm"
                                        >
                                            Definir data final
                                        </Label>
                                    </div>

                                    {data.has_until_date && (
                                        <div className="space-y-2">
                                            <Label htmlFor="until_date">
                                                Data final
                                            </Label>
                                            <Input
                                                id="until_date"
                                                type="date"
                                                value={data.until_date}
                                                onChange={(e) =>
                                                    setData(
                                                        'until_date',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            {errors.until_date && (
                                                <p className="text-sm text-destructive">
                                                    {errors.until_date}
                                                </p>
                                            )}
                                        </div>
                                    )}
                                </div>
                            )}

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
                                <Button type="submit">
                                    Criar Receita
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={route('incomes.index', {
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
