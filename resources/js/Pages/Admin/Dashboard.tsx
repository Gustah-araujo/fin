import { Users } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface Props {
    activeUsers: number;
}

export default function Dashboard({ activeUsers }: Props) {
    return (
        <AdminLayout>
            <div className="space-y-6">
                <h1 className="text-3xl font-bold tracking-tight">Dashboard</h1>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                <Users className="h-4 w-4" />
                                Total de Usuários Ativos
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p
                                className="text-2xl font-semibold"
                                data-testid="admin-dashboard-active-users"
                            >
                                {activeUsers}
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AdminLayout>
    );
}
