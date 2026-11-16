import { Link, useForm } from '@inertiajs/react';
import { useWorkspace } from '@/hooks/useWorkspace';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import IconPicker from '@/Components/IconPicker';
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
import { ColorPicker } from '@/components/ui/color-picker';

interface CategoryData {
    uuid: string;
    name: string;
    type: string;
    color: string;
    icon: string | null;
    position: number | null;
    created_at: string;
    expense_group?: {
        uuid: string;
        name: string;
        color: string;
    } | null;
}

interface ExpenseGroupItem {
    uuid: string;
    name: string;
    color: string;
}

interface Props {
    category: CategoryData;
    expenseGroups: ExpenseGroupItem[];
}

export default function Edit({ category, expenseGroups }: Props) {
    const workspace = useWorkspace();
    const { data, setData, put, processing, errors } = useForm({
        name: category.name,
        type: category.type,
        color: category.color,
        icon: category.icon ?? '',
        expense_group_id: category.expense_group?.uuid ?? '',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(
            route('categories.update', {
                workspace: workspace.uuid,
                category: category.uuid,
            }),
        );
    }

    return (
        <AuthenticatedLayout>
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Editar Categoria
                    </h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        Atualize os dados da categoria
                    </p>
                </div>

                <Card className="max-w-lg">
                    <CardHeader>
                        <CardTitle>Dados da Categoria</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">Nome</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                />
                                {errors.name && (
                                    <p className="text-sm text-destructive">
                                        {errors.name}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="type">Tipo</Label>
                                <Select
                                    value={data.type}
                                    onValueChange={(value) =>
                                        setData('type', value)
                                    }
                                >
                                    <SelectTrigger id="type">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="income">
                                            Receita
                                        </SelectItem>
                                        <SelectItem value="expense">
                                            Despesa
                                        </SelectItem>
                                        <SelectItem value="both">
                                            Ambos
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                {errors.type && (
                                    <p className="text-sm text-destructive">
                                        {errors.type}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label>Cor</Label>
                                <ColorPicker
                                    value={data.color}
                                    onChange={(color) =>
                                        setData('color', color)
                                    }
                                />
                                {errors.color && (
                                    <p className="text-sm text-destructive">
                                        {errors.color}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="expense_group_id">
                                    Grupo de Despesa
                                </Label>
                                <Select
                                    value={data.expense_group_id}
                                    onValueChange={(value) =>
                                        setData('expense_group_id', value)
                                    }
                                >
                                    <SelectTrigger id="expense_group_id">
                                        <SelectValue placeholder="Selecione o grupo" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="">
                                            Sem grupo
                                        </SelectItem>
                                        {expenseGroups.map((group) => (
                                            <SelectItem
                                                key={group.uuid}
                                                value={group.uuid}
                                            >
                                                {group.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.expense_group_id && (
                                    <p className="text-sm text-destructive">
                                        {errors.expense_group_id}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label>Ícone</Label>
                                <IconPicker
                                    value={data.icon}
                                    onChange={(icon) =>
                                        setData('icon', icon ?? '')
                                    }
                                />
                                {errors.icon && (
                                    <p className="text-sm text-destructive">
                                        {errors.icon}
                                    </p>
                                )}
                            </div>

                            <div className="flex items-center gap-3 pt-2">
                                <Button type="submit" disabled={processing}>
                                    Salvar
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={route('categories.index', {
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
