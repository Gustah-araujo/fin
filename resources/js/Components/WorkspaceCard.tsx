import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface WorkspaceCardProps {
    uuid: string;
    name: string;
    description: string | null;
    membersCount: number;
    role: string;
    onClick: () => void;
}

const roleLabels: Record<string, string> = {
    admin: 'Administrador',
    editor: 'Editor',
    viewer: 'Visualizador',
};

export default function WorkspaceCard({ name, description, membersCount, role, onClick }: WorkspaceCardProps) {
    return (
        <Card
            className="cursor-pointer hover:border-primary transition-colors"
            onClick={onClick}
        >
            <CardHeader>
                <CardTitle className="text-lg">{name}</CardTitle>
            </CardHeader>
            <CardContent>
                {description && (
                    <p className="text-sm text-muted-foreground mb-2">{description}</p>
                )}
                <p className="text-xs text-muted-foreground">
                    {membersCount} membro{membersCount !== 1 ? 's' : ''} &middot;{' '}
                    {roleLabels[role] ?? role}
                </p>
            </CardContent>
        </Card>
    );
}
