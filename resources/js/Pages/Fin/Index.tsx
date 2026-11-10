import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface FinConversation {
    id: string;
    title: string;
    created_at: string;
    updated_at: string;
}

interface Props {
    conversations: FinConversation[];
}

export default function FinIndex({ conversations }: Props) {
    return (
        <AuthenticatedLayout>
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Fin
                    </h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        Assistente financeiro com IA
                    </p>
                </div>

                {conversations.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Nenhuma conversa ainda.
                    </p>
                ) : (
                    <ul className="space-y-2">
                        {conversations.map((conversation) => (
                            <li key={conversation.id} className="text-sm">
                                {conversation.title}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
