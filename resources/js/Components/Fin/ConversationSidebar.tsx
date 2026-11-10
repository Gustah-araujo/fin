import { cn } from '@/lib/utils';
import NewConversationButton from '@/Components/Fin/NewConversationButton';
import type { FinConversation } from '@/types/fin';

interface Props {
    conversations: FinConversation[];
    activeId: string | null;
    onSelect: (id: string) => void;
    onCreate: () => void;
    className?: string;
}

export default function ConversationSidebar({
    conversations,
    activeId,
    onSelect,
    onCreate,
    className,
}: Props) {
    return (
        <aside className={cn('flex flex-col', className)}>
            <div className="border-b px-4 py-3">
                <h2 className="text-sm font-semibold">Conversas</h2>
            </div>

            <div className="p-3">
                <NewConversationButton onCreate={onCreate} />
            </div>

            <div className="flex-1 overflow-y-auto px-2 pb-3">
                {conversations.length === 0 ? (
                    <p className="px-2 py-2 text-sm text-muted-foreground">
                        Nenhuma conversa ainda.
                    </p>
                ) : (
                    <div className="space-y-1">
                        {conversations.map((conversation) => {
                            const active = conversation.id === activeId;

                            return (
                                <button
                                    key={conversation.id}
                                    data-testid="fin-conversation-item"
                                    data-conversation-id={conversation.id}
                                    onClick={() => onSelect(conversation.id)}
                                    className={cn(
                                        'block w-full truncate rounded-lg px-3 py-2 text-left text-sm transition-colors',
                                        active
                                            ? 'bg-accent text-accent-foreground'
                                            : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground',
                                    )}
                                >
                                    {conversation.title}
                                </button>
                            );
                        })}
                    </div>
                )}
            </div>
        </aside>
    );
}
