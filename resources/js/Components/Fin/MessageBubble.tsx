import { cn } from '@/lib/utils';
import { attachmentIcon } from '@/lib/fin-attachments';
import MarkdownContent from '@/Components/Fin/MarkdownContent';
import type { FinAttachment, FinMessage } from '@/types/fin';

interface Props {
    message: FinMessage;
}

function AttachmentChip({ attachment }: { attachment: FinAttachment }) {
    const Icon = attachmentIcon(attachment.name);

    return (
        <span className="flex max-w-56 items-center gap-1.5 rounded-md border border-primary-foreground/20 bg-primary-foreground/10 px-2 py-1 text-xs">
            <Icon className="h-3.5 w-3.5 shrink-0" />
            <span className="truncate font-medium">{attachment.name}</span>
        </span>
    );
}

export default function MessageBubble({ message }: Props) {
    const isUser = message.role === 'user';
    const attachments = isUser ? (message.attachments ?? []) : [];

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
            {attachments.length > 0 && (
                <div
                    data-testid="fin-message-attachments"
                    className="mb-1.5 flex flex-wrap gap-1.5"
                >
                    {attachments.map((attachment, index) => (
                        <AttachmentChip
                            key={`${attachment.name}-${index}`}
                            attachment={attachment}
                        />
                    ))}
                </div>
            )}
            {isUser ? (
                message.content
            ) : (
                <MarkdownContent content={message.content} />
            )}
        </div>
    );
}
