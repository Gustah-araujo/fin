import { useForm } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

interface Workspace {
    uuid: string;
    name: string;
    description: string | null;
    members_count: number;
    role: string;
}

interface SelectProps {
    workspaces: Workspace[];
}

export default function Select({ workspaces }: SelectProps) {
    const { post } = useForm({});

    return (
        <div className="min-h-screen flex items-center justify-center bg-background p-4">
            <div className="w-full max-w-2xl space-y-6">
                <div className="text-center">
                    <h1 className="text-2xl font-semibold tracking-tight">Seus workspaces</h1>
                    <p className="text-sm text-muted-foreground mt-2">
                        Selecione um workspace para continuar ou crie um novo
                    </p>
                </div>

                {workspaces.length === 0 && (
                    <div className="text-center py-12">
                        <p className="text-muted-foreground">Nenhum workspace encontrado.</p>
                    </div>
                )}

                <div className="grid gap-4 sm:grid-cols-2">
                    {workspaces.map((workspace) => (
                        <Card
                            key={workspace.uuid}
                            className="cursor-pointer hover:border-primary transition-colors"
                            onClick={() =>
                                post('/workspace/activate', {
                                    data: { workspace_uuid: workspace.uuid },
                                })
                            }
                        >
                            <CardHeader>
                                <CardTitle className="text-lg">{workspace.name}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {workspace.description && (
                                    <p className="text-sm text-muted-foreground mb-2">
                                        {workspace.description}
                                    </p>
                                )}
                                <p className="text-xs text-muted-foreground">
                                    {workspace.members_count} membro{workspace.members_count !== 1 ? 's' : ''} &middot;{' '}
                                    {workspace.role === 'admin' ? 'Administrador' : workspace.role === 'editor' ? 'Editor' : 'Visualizador'}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="text-center">
                    <a href={route('workspace.create')}>
                        <Button variant="outline">Criar novo workspace</Button>
                    </a>
                </div>
            </div>
        </div>
    );
}
