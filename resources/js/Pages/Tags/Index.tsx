import { Link, useForm, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

interface TagItem {
    uuid: string;
    name: string;
    color: string;
    created_at: string;
}

interface Props {
    tags: TagItem[];
}

export default function Index({ tags }: Props) {
    const { workspace } = usePage<{ workspace: { uuid: string; name: string } }>().props;
    const { delete: destroy } = useForm();

    function handleDelete(tagUuid: string) {
        destroy(route('tags.destroy', { workspace: workspace.uuid, tag: tagUuid }), {
            onSuccess: () => window.location.reload(),
        });
    }

    return (
        <AuthenticatedLayout>
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Tags</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            {tags.length} tag{tags.length !== 1 ? 's' : ''}
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={route('tags.create', { workspace: workspace.uuid })}>
                            Nova Tag
                        </Link>
                    </Button>
                </div>

                {tags.length === 0 ? (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <p className="text-sm text-muted-foreground mb-4">
                                Nenhuma tag cadastrada
                            </p>
                            <Button asChild>
                                <Link href={route('tags.create', { workspace: workspace.uuid })}>
                                    Criar primeira tag
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                        {tags.map((tag) => (
                            <Card key={tag.uuid}>
                                <CardHeader className="pb-2">
                                    <div className="flex items-center gap-3">
                                        <div
                                            className="size-4 shrink-0 rounded-full"
                                            style={{ backgroundColor: tag.color }}
                                        />
                                        <CardTitle className="text-base font-medium truncate">
                                            {tag.name}
                                        </CardTitle>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="flex items-center gap-2">
                                        <Button variant="outline" size="sm" asChild>
                                            <Link
                                                href={route('tags.edit', {
                                                    workspace: workspace.uuid,
                                                    tag: tag.uuid,
                                                })}
                                            >
                                                Editar
                                            </Link>
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            onClick={() => handleDelete(tag.uuid)}
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
