import { useState } from 'react';
import { Send } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';

interface Props {
    disabled: boolean;
    onSend: (content: string) => void;
}

export default function ChatInput({ disabled, onSend }: Props) {
    const [value, setValue] = useState('');

    const handleSend = (): void => {
        const content = value.trim();
        if (!content || disabled) {
            return;
        }

        onSend(content);
        setValue('');
    };

    const handleKeyDown = (
        event: React.KeyboardEvent<HTMLTextAreaElement>,
    ): void => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            handleSend();
        }
    };

    return (
        <div className="flex items-end gap-2 border-t p-3">
            <Textarea
                data-testid="fin-input"
                value={value}
                onChange={(event) => setValue(event.target.value)}
                onKeyDown={handleKeyDown}
                placeholder="Pergunte ao Fin sobre suas finanças…"
                disabled={disabled}
                className="min-h-10 max-h-40 flex-1 resize-none"
            />
            <Button
                data-testid="fin-send"
                size="icon"
                onClick={handleSend}
                disabled={disabled || !value.trim()}
                aria-label="Enviar mensagem"
            >
                <Send className="h-4 w-4" />
            </Button>
        </div>
    );
}
