import { Link, useForm } from '@inertiajs/react';
import { useWorkspace } from '@/hooks/useWorkspace';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ColorPicker } from '@/components/ui/color-picker';

interface ExpenseGroupItem {
    uuid: string;
    name: string;
    color: string;
    created_at: string;
}

interface Props {
    expenseGroup: ExpenseGroupItem;
}

export default function Edit({ expenseGroup }: Props) {
    const workspace = useWorkspace();
    const { data, setData, put, processing, errors } = useForm({
        name: expenseGroup.name,
        color: expenseGroup.color,
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(
            route('expense-groups.update', {
                workspace: workspace.uuid,
                expense_group: expenseGroup.uuid,
            }),
        );
    }

    return (
        <AuthenticatedLayout>
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Editar Grupo de Despesa
                    </h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        Atualize os dados do grupo
                    </p>
                </div>

                <Card className="max-w-lg">
                    <CardHeader>
                        <CardTitle>Dados do Grupo</CardTitle>
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

                            <div className="flex items-center gap-3 pt-2">
                                <Button type="submit" disabled={processing}>
                                    Salvar
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={route('expense-groups.index', {
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
