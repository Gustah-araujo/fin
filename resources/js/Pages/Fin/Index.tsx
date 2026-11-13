import { useCallback, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { toast } from 'sonner';
import { dispatchToast } from '@/lib/toast';
import { attachmentKind } from '@/lib/fin-attachments';
import { useWorkspace } from '@/hooks/useWorkspace';
import { useFinPolling } from '@/hooks/use-fin-polling';
import ConversationSidebar from '@/Components/Fin/ConversationSidebar';
import ChatArea from '@/Components/Fin/ChatArea';
import type {
    FinConversation,
    FinConversationStart,
    FinMessage,
    FinRun,
} from '@/types/fin';

interface Props {
    conversations: FinConversation[];
}

const getCsrfToken = (): string =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';

const jsonHeaders = (): Record<string, string> => ({
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-CSRF-TOKEN': getCsrfToken(),
    Accept: 'application/json',
});

export default function FinIndex({
    conversations: initialConversations,
}: Props) {
    const workspace = useWorkspace();
    const [conversations, setConversations] =
        useState<FinConversation[]>(initialConversations);
    const [activeId, setActiveId] = useState<string | null>(null);
    const [messages, setMessages] = useState<FinMessage[]>([]);
    const [loadingMessages, setLoadingMessages] = useState(false);
    const [isProcessing, setIsProcessing] = useState(false);
    const [pendingRunId, setPendingRunId] = useState<string | null>(null);

    const loadConversation = useCallback(
        async (id: string): Promise<void> => {
            try {
                const response = await fetch(
                    route('fin.conversations.show', {
                        workspace: workspace.uuid,
                        conversation: id,
                    }),
                );

                if (!response.ok) {
                    return;
                }

                const data = (await response.json()) as FinConversation;
                setMessages(data.messages ?? []);
                setConversations((prev) =>
                    prev.map((conversation) =>
                        conversation.id === id
                            ? { ...conversation, title: data.title }
                            : conversation,
                    ),
                );
            } catch {
                // Silently ignore — loading messages is best-effort.
            }
        },
        [workspace.uuid],
    );

    const fetchConversations = useCallback(async (): Promise<
        FinConversation[] | null
    > => {
        try {
            const response = await fetch(
                route('fin.conversations.index', {
                    workspace: workspace.uuid,
                }),
            );

            if (!response.ok) {
                return null;
            }

            const list = (await response.json()) as FinConversation[];
            setConversations(list);
            return list;
        } catch {
            // Silently ignore — refreshing the list is best-effort.
            return null;
        }
    }, [workspace.uuid]);

    const syncTitleWithRetry = useCallback(async (): Promise<void> => {
        let list = await fetchConversations();

        for (let attempt = 0; attempt < 3; attempt++) {
            const active = activeId
                ? list?.find((conversation) => conversation.id === activeId)
                : null;

            if (!active || active.title !== 'Nova conversa') {
                break;
            }

            await new Promise((resolve) => setTimeout(resolve, 1500));
            list = (await fetchConversations()) ?? list;
        }
    }, [activeId, fetchConversations]);

    const handleSelect = useCallback(
        async (id: string): Promise<void> => {
            setActiveId(id);
            setLoadingMessages(true);
            await loadConversation(id);
            setLoadingMessages(false);
        },
        [loadConversation],
    );

    const handleCreate = useCallback((): void => {
        if (activeId === null) {
            return;
        }

        setActiveId(null);
        setMessages([]);
    }, [activeId]);

    const handleSend = useCallback(
        async (content: string, files: File[]): Promise<void> => {
            const trimmedContent = content.trim();
            const attachments = files.map((file) => ({
                name: file.name,
                kind: attachmentKind(file.name),
            }));

            if (trimmedContent === '' && attachments.length === 0) {
                return;
            }

            const isDraft = activeId === null;

            const tempId = crypto.randomUUID();
            setMessages((prev) => [
                ...prev,
                {
                    id: tempId,
                    role: 'user',
                    content: trimmedContent,
                    attachments,
                    created_at: new Date().toISOString(),
                },
            ]);
            setIsProcessing(true);

            try {
                const formData = new FormData();
                if (trimmedContent !== '') {
                    formData.append('content', trimmedContent);
                }
                files.forEach((file) => formData.append('attachments[]', file));

                const url = isDraft
                    ? route('fin.conversations.store', {
                          workspace: workspace.uuid,
                      })
                    : route('fin.conversations.messages.store', {
                          workspace: workspace.uuid,
                          conversation: activeId,
                      });

                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': getCsrfToken(),
                        Accept: 'application/json',
                    },
                    body: formData,
                });

                if (!response.ok) {
                    setIsProcessing(false);
                    return;
                }

                if (isDraft) {
                    const data =
                        (await response.json()) as FinConversationStart;
                    setConversations((prev) => [data.conversation, ...prev]);
                    setActiveId(data.conversation.id);
                    setPendingRunId(data.run.id);
                } else {
                    const run = (await response.json()) as FinRun;
                    setPendingRunId(run.id);
                }
            } catch {
                setIsProcessing(false);
                toast.error('Erro ao enviar mensagem.');
                dispatchToast('error', 'Erro ao enviar mensagem.');
            }
        },
        [activeId, workspace.uuid],
    );

    const handleRename = useCallback(
        async (title: string): Promise<void> => {
            if (!activeId) {
                return;
            }

            try {
                const response = await fetch(
                    route('fin.conversations.update', {
                        workspace: workspace.uuid,
                        conversation: activeId,
                    }),
                    {
                        method: 'PUT',
                        headers: jsonHeaders(),
                        body: JSON.stringify({ title }),
                    },
                );

                if (!response.ok) {
                    return;
                }

                const updated = (await response.json()) as FinConversation;
                setConversations((prev) =>
                    prev.map((conversation) =>
                        conversation.id === updated.id
                            ? { ...conversation, title: updated.title }
                            : conversation,
                    ),
                );
            } catch {
                toast.error('Erro ao renomear conversa.');
                dispatchToast('error', 'Erro ao renomear conversa.');
            }
        },
        [activeId, workspace.uuid],
    );

    const handleDelete = useCallback(async (): Promise<void> => {
        if (!activeId) {
            return;
        }

        try {
            const response = await fetch(
                route('fin.conversations.destroy', {
                    workspace: workspace.uuid,
                    conversation: activeId,
                }),
                {
                    method: 'DELETE',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': getCsrfToken(),
                        Accept: 'application/json',
                    },
                },
            );

            if (!response.ok) {
                return;
            }

            setConversations((prev) =>
                prev.filter((conversation) => conversation.id !== activeId),
            );
            setActiveId(null);
            setMessages([]);
            toast.success('Conversa excluída.');
            dispatchToast('success', 'Conversa excluída.');
        } catch {
            toast.error('Erro ao excluir conversa.');
            dispatchToast('error', 'Erro ao excluir conversa.');
        }
    }, [activeId, workspace.uuid]);

    const handlePollingCompleted = useCallback(async (): Promise<void> => {
        setIsProcessing(false);
        setPendingRunId(null);

        if (activeId) {
            await loadConversation(activeId);
            await syncTitleWithRetry();
        }
    }, [activeId, loadConversation, syncTitleWithRetry]);

    const handlePollingFailed = useCallback(async (): Promise<void> => {
        setIsProcessing(false);
        setPendingRunId(null);
        toast.error('Fin não conseguiu responder.');
        dispatchToast('error', 'Fin não conseguiu responder.');

        if (activeId) {
            await loadConversation(activeId);
            await syncTitleWithRetry();
        }
    }, [activeId, loadConversation, syncTitleWithRetry]);

    useFinPolling(
        workspace.uuid,
        pendingRunId,
        handlePollingCompleted,
        handlePollingFailed,
    );

    const activeConversation =
        conversations.find((conversation) => conversation.id === activeId) ??
        null;

    return (
        <AuthenticatedLayout>
            <div className="flex h-[calc(100vh-7rem)] overflow-hidden rounded-lg border">
                <ConversationSidebar
                    className="w-1/3 min-w-[260px] border-r"
                    conversations={conversations}
                    activeId={activeId}
                    onSelect={handleSelect}
                    onCreate={handleCreate}
                />
                <ChatArea
                    className="flex-1"
                    conversation={activeConversation}
                    messages={messages}
                    isProcessing={isProcessing}
                    loadingMessages={loadingMessages}
                    onSend={handleSend}
                    onRename={handleRename}
                    onDelete={handleDelete}
                />
            </div>
        </AuthenticatedLayout>
    );
}
