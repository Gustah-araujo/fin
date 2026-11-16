import { Link, useForm, router } from '@inertiajs/react';
import { useWorkspace } from '@/hooks/useWorkspace';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

interface ExpenseGroupItem {
    uuid: string;
    name: string;
    color: string;
    created_at: string;
}

interface Props {
    expenseGroups: ExpenseGroupItem[];
}

export default function Index({ expenseGroups }: Props) {
    const workspace = useWorkspace();
    const { delete: destroy } = useForm();

    function handleDelete(groupUuid: string) {
        destroy(
            route('expense-groups.destroy', {
                workspace: workspace.uuid,
                expense_group: groupUuid,
            }),
            {
                onSuccess: () => router.reload(),
            },
        );
    }

    return (
        <AuthenticatedLayout>
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Grupos de Despesa
                        </h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            {expenseGroups.length} grupo
                            {expenseGroups.length !== 1 ? 's' : ''}
                        </p>
                    </div>
                    <Button asChild>
                        <Link
                            href={route('expense-groups.create', {
                                workspace: workspace.uuid,
                            })}
                        >
                            Novo Grupo
                        </Link>
                    </Button>
                </div>

                {expenseGroups.length === 0 && (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <p className="text-sm text-muted-foreground mb-4">
                                Nenhum grupo de despesa cadastrado
                            </p>
                            <Button asChild>
                                <Link
                                    href={route('expense-groups.create', {
                                        workspace: workspace.uuid,
                                    })}
                                >
                                    Criar primeiro grupo
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                    {expenseGroups.map((group) => (
                        <Card key={group.uuid}>
                            <CardHeader className="pb-2">
                                <div className="flex items-center gap-3">
                                    <div
                                        className="size-4 shrink-0 rounded-full"
                                        style={{
                                            backgroundColor: group.color,
                                        }}
                                    />
                                    <div className="min-w-0 flex-1">
                                        <CardTitle className="text-base font-medium truncate">
                                            {group.name}
                                        </CardTitle>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="flex items-center gap-2">
                                    <Button variant="outline" size="sm" asChild>
                                        <Link
                                            href={route('expense-groups.edit', {
                                                workspace: workspace.uuid,
                                                expense_group: group.uuid,
                                            })}
                                        >
                                            Editar
                                        </Link>
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        onClick={() => handleDelete(group.uuid)}
                                    >
                                        Excluir
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
