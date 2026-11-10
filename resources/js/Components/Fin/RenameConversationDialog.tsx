import { useEffect, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';

interface Props {
    open: boolean;
    title: string;
    onOpenChange: (open: boolean) => void;
    onSubmit: (title: string) => void;
}

export default function RenameConversationDialog({
    open,
    title,
    onOpenChange,
    onSubmit,
}: Props) {
    const [value, setValue] = useState(title);

    useEffect(() => {
        if (open) {
            setValue(title);
        }
    }, [open, title]);

    const handleSubmit = (event: React.FormEvent): void => {
        event.preventDefault();

        const trimmed = value.trim();
        if (!trimmed) {
            return;
        }

        onSubmit(trimmed);
        onOpenChange(false);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Renomear conversa</DialogTitle>
                    <DialogDescription>
                        Defina um novo título para esta conversa.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit}>
                    <Input
                        data-testid="fin-rename-input"
                        value={value}
                        onChange={(event) => setValue(event.target.value)}
                        autoFocus
                    />
                    <DialogFooter className="mt-4">
                        <Button type="submit">Salvar</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
