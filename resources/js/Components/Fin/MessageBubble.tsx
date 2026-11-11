import { cn } from '@/lib/utils';
import MarkdownContent from '@/Components/Fin/MarkdownContent';
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
                'max-w-[85%] rounded-2xl px-4 py-2 text-sm break-words',
                isUser
                    ? 'self-end bg-primary text-primary-foreground whitespace-pre-wrap'
                    : 'self-start bg-muted text-foreground',
            )}
        >
            {isUser ? (
                message.content
            ) : (
                <MarkdownContent content={message.content} />
            )}
        </div>
    );
}
