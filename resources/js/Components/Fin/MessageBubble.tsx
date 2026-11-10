import { cn } from '@/lib/utils';
import type { FinMessage } from '@/types/fin';

interface Props {
    message: FinMessage;
}

export default function MessageBubble({ message }: Props) {
    const isUser = message.role === 'user';

    return (
        <div
            data-testid="message-bubble"
            data-role={message.role}
            className={cn(
                'max-w-[75%] rounded-2xl px-4 py-2 text-sm whitespace-pre-wrap break-words',
                isUser
                    ? 'self-end bg-primary text-primary-foreground'
                    : 'self-start bg-muted text-foreground',
            )}
        >
            {message.content}
        </div>
    );
}
