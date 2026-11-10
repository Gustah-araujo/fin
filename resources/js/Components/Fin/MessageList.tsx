import { ScrollArea } from '@/components/ui/scroll-area';
import MessageBubble from '@/Components/Fin/MessageBubble';
import type { FinMessage } from '@/types/fin';

interface Props {
    messages: FinMessage[];
    isProcessing: boolean;
}

export default function MessageList({ messages, isProcessing }: Props) {
    return (
        <ScrollArea className="h-full">
            <div className="flex flex-col gap-4 p-4">
                {messages.length === 0 && !isProcessing && (
                    <div
                        data-testid="fin-empty"
                        className="flex items-center justify-center py-12 text-sm text-muted-foreground"
                    >
                        Envie uma mensagem para começar.
                    </div>
                )}

                {messages.map((message) => (
                    <MessageBubble key={message.id} message={message} />
                ))}

                {isProcessing && (
                    <div
                        data-testid="fin-typing"
                        className="flex items-center gap-2 self-start rounded-2xl bg-muted px-4 py-2 text-sm text-muted-foreground"
                    >
                        Fin está digitando
                        <span className="flex items-center gap-0.5">
                            <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-current" />
                            <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-current [animation-delay:150ms]" />
                            <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-current [animation-delay:300ms]" />
                        </span>
                    </div>
                )}
            </div>
        </ScrollArea>
    );
}
