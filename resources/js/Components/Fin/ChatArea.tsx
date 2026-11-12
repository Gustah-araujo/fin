import { useState } from 'react';
import { Pencil, Trash2, Sparkles } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import MessageList from '@/Components/Fin/MessageList';
import ChatInput from '@/Components/Fin/ChatInput';
import RenameConversationDialog from '@/Components/Fin/RenameConversationDialog';
import DeleteConversationDialog from '@/Components/Fin/DeleteConversationDialog';
import type { FinConversation, FinMessage } from '@/types/fin';

interface Props {
    conversation: FinConversation | null;
    messages: FinMessage[];
    isProcessing: boolean;
    loadingMessages?: boolean;
    onSend: (content: string, files: File[]) => void;
    onRename: (title: string) => void;
    onDelete: () => void;
    className?: string;
}

export default function ChatArea({
    conversation,
    messages,
    isProcessing,
    loadingMessages = false,
    onSend,
    onRename,
    onDelete,
    className,
}: Props) {
    const [renameOpen, setRenameOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);

    if (conversation === null) {
        return (
            <div
                data-testid="fin-no-conversation"
                className={cn('flex flex-col', className)}
            >
                <div className="flex flex-1 flex-col items-center justify-center gap-3 p-6 text-center">
                    <Sparkles className="h-10 w-10 text-muted-foreground" />
                    <h2 className="text-xl font-semibold tracking-tight">
                        Assistente Fin
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Selecione uma conversa ou crie uma nova.
                    </p>
                </div>
                <ChatInput disabled={isProcessing} onSend={onSend} />
            </div>
        );
    }

    return (
        <div className={cn('flex flex-col', className)}>
            <div className="flex items-center justify-between gap-3 border-b px-4 py-3">
                <h2 className="truncate text-sm font-semibold">
                    {conversation.title || 'Fin'}
                </h2>
                <div className="flex shrink-0 items-center gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        data-testid="fin-rename"
                        aria-label="Renomear conversa"
                        onClick={() => setRenameOpen(true)}
                    >
                        <Pencil className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        data-testid="fin-delete"
                        aria-label="Excluir conversa"
                        onClick={() => setDeleteOpen(true)}
                    >
                        <Trash2 className="h-4 w-4" />
                    </Button>
                </div>
            </div>

            <div className="flex-1 overflow-hidden">
                {loadingMessages ? (
                    <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
                        Carregando…
                    </div>
                ) : (
                    <MessageList
                        messages={messages}
                        isProcessing={isProcessing}
                    />
                )}
            </div>

            <ChatInput disabled={isProcessing} onSend={onSend} />

            <RenameConversationDialog
                open={renameOpen}
                title={conversation.title}
                onOpenChange={setRenameOpen}
                onSubmit={onRename}
            />
            <DeleteConversationDialog
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
                onConfirm={onDelete}
            />
        </div>
    );
}
