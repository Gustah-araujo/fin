import { Link } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { AdminWorkspace } from '@/types/admin';

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('pt-BR');
}

interface Props {
    workspaces: AdminWorkspace[];
}

export default function Index({ workspaces }: Props) {
    return (
        <AdminLayout>
            <div className="space-y-6">
                <h1 className="text-3xl font-bold tracking-tight">
                    Workspaces
                </h1>

                {workspaces.length === 0 ? (
                    <Card>
                        <CardContent className="py-12">
                            <p className="text-center text-sm text-muted-foreground">
                                Nenhum workspace encontrado.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg font-semibold">
                                Todos os Workspaces
                            </CardTitle>
                            <CardDescription>
                                {workspaces.length === 1
                                    ? '1 workspace'
                                    : `${workspaces.length} workspaces`}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ul className="divide-y">
                                {workspaces.map((workspace) => (
                                    <li
                                        key={workspace.uuid}
                                        className="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0"
                                    >
                                        <div className="flex items-center gap-3">
                                            <Building2 className="h-4 w-4 shrink-0 text-muted-foreground" />
                                            <div>
                                                <p className="text-sm font-medium">
                                                    {workspace.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {workspace.members_count ??
                                                        0}{' '}
                                                    membros · criado em{' '}
                                                    {formatDate(
                                                        workspace.created_at,
                                                    )}
                                                </p>
                                            </div>
                                        </div>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={route(
                                                    'admin.workspaces.show',
                                                    {
                                                        workspace:
                                                            workspace.uuid,
                                                    },
                                                )}
                                                data-testid="admin-workspace-view"
                                            >
                                                Visualizar
                                            </Link>
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AdminLayout>
    );
}
