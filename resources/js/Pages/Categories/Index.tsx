import { Link, useForm } from '@inertiajs/react';
import { useWorkspace } from '@/hooks/useWorkspace';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import DynamicIcon from '@/Components/DynamicIcon';
import { cn } from '@/lib/utils';

interface CategoryItem {
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
    created_at: string;
}

interface Props {
    categories: CategoryItem[];
    expenseGroups: ExpenseGroupItem[];
}

const typeLabels: Record<string, string> = {
    income: 'Receita',
    expense: 'Despesa',
    both: 'Ambos',
};

export default function Index({ categories, expenseGroups }: Props) {
    const workspace = useWorkspace();
    const { delete: destroy } = useForm();

    function handleDelete(categoryUuid: string) {
        destroy(
            route('categories.destroy', {
                workspace: workspace.uuid,
                category: categoryUuid,
            }),
            {
                onSuccess: () => window.location.reload(),
            },
        );
    }

    const categoriesByType = {
        income: categories.filter((c) => c.type === 'income'),
        expense: categories.filter((c) => c.type === 'expense'),
        both: categories.filter((c) => c.type === 'both'),
    };

    const typeOrder: Array<keyof typeof categoriesByType> = [
        'income',
        'expense',
        'both',
    ];

    const userCategories = categories.filter((c) => c.name !== 'Sem Categoria');

    return (
        <AuthenticatedLayout>
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Categorias
                        </h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            {userCategories.length} categoria
                            {userCategories.length !== 1 ? 's' : ''}
                        </p>
                    </div>
                    <Button asChild>
                        <Link
                            href={route('categories.create', {
                                workspace: workspace.uuid,
                            })}
                        >
                            Nova Categoria
                        </Link>
                    </Button>
                </div>

                {userCategories.length === 0 && (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <p className="text-sm text-muted-foreground mb-4">
                                Nenhuma categoria criada
                            </p>
                            <Button asChild>
                                <Link
                                    href={route('categories.create', {
                                        workspace: workspace.uuid,
                                    })}
                                >
                                    Criar primeira categoria
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                )}

                {typeOrder.map((type) => {
                    const items = categoriesByType[type];
                    if (items.length === 0) return null;

                    return (
                        <div key={type}>
                            {userCategories.length > 0 && (
                                <h2 className="text-sm font-medium text-muted-foreground uppercase tracking-wider mb-3">
                                    {typeLabels[type]}
                                </h2>
                            )}
                            <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                                {items.map((category) => {
                                    const isDefault =
                                        category.name === 'Sem Categoria';
                                    return (
                                        <Card
                                            key={category.uuid}
                                            className={cn(
                                                isDefault && 'opacity-75',
                                            )}
                                        >
                                            <CardHeader className="pb-2">
                                                <div className="flex items-center gap-3">
                                                    <div
                                                        className="flex size-8 shrink-0 items-center justify-center rounded-full"
                                                        style={{
                                                            backgroundColor:
                                                                category.color +
                                                                '20',
                                                        }}
                                                    >
                                                        {category.icon ? (
                                                            <DynamicIcon
                                                                name={
                                                                    category.icon
                                                                }
                                                                className="size-4"
                                                                style={{
                                                                    color: category.color,
                                                                }}
                                                            />
                                                        ) : (
                                                            <div
                                                                className="size-3 rounded-full"
                                                                style={{
                                                                    backgroundColor:
                                                                        category.color,
                                                                }}
                                                            />
                                                        )}
                                                    </div>
                                                    <div className="min-w-0 flex-1">
                                                        <CardTitle className="text-base font-medium truncate">
                                                            {category.name}
                                                        </CardTitle>
                                                        {category.expense_group && (
                                                            <div className="flex items-center gap-1.5 mt-0.5">
                                                                <span
                                                                    className="inline-block h-2 w-2 rounded-full shrink-0"
                                                                    style={{
                                                                        backgroundColor:
                                                                            category
                                                                                .expense_group
                                                                                .color,
                                                                    }}
                                                                />
                                                                <span className="text-xs text-muted-foreground truncate">
                                                                    {
                                                                        category
                                                                            .expense_group
                                                                            .name
                                                                    }
                                                                </span>
                                                            </div>
                                                        )}
                                                    </div>
                                                    {isDefault && (
                                                        <Badge
                                                            variant="secondary"
                                                            className="text-xs"
                                                        >
                                                            Padrão
                                                        </Badge>
                                                    )}
                                                </div>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="flex items-center gap-2">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={route(
                                                                'categories.edit',
                                                                {
                                                                    workspace:
                                                                        workspace.uuid,
                                                                    category:
                                                                        category.uuid,
                                                                },
                                                            )}
                                                        >
                                                            Editar
                                                        </Link>
                                                    </Button>
                                                    {!isDefault && (
                                                        <Button
                                                            variant="destructive"
                                                            size="sm"
                                                            onClick={() =>
                                                                handleDelete(
                                                                    category.uuid,
                                                                )
                                                            }
                                                        >
                                                            Excluir
                                                        </Button>
                                                    )}
                                                </div>
                                            </CardContent>
                                        </Card>
                                    );
                                })}
                            </div>
                        </div>
                    );
                })}

                <Card>
                    <CardHeader className="pb-2">
                        <div className="flex items-center justify-between">
                            <CardTitle className="text-lg font-semibold">
                                Grupos de Despesa
                            </CardTitle>
                            <Button variant="outline" size="sm" asChild>
                                <Link
                                    href={route('expense-groups.index', {
                                        workspace: workspace.uuid,
                                    })}
                                >
                                    Gerenciar grupos
                                </Link>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {expenseGroups.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Nenhum grupo de despesa cadastrado
                            </p>
                        ) : (
                            <div className="space-y-1">
                                {expenseGroups.map((group) => (
                                    <div
                                        key={group.uuid}
                                        className="flex items-center gap-2"
                                    >
                                        <div
                                            className="size-2 shrink-0 rounded-full"
                                            style={{
                                                backgroundColor: group.color,
                                            }}
                                        />
                                        <span className="text-sm truncate">
                                            {group.name}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
