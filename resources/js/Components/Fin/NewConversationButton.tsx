import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface Props {
    onCreate: () => void;
}

export default function NewConversationButton({ onCreate }: Props) {
    return (
        <Button
            data-testid="fin-new-conversation"
            variant="outline"
            onClick={onCreate}
            className="w-full justify-start"
        >
            <Plus className="h-4 w-4" />
            Nova conversa
        </Button>
    );
}
