import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import RoleSelect from '@/Components/RoleSelect';
import { useState, type FormEvent } from 'react';

interface InviteDialogProps {
    workspaceUuid: string;
}

export default function InviteDialog({ workspaceUuid }: InviteDialogProps) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        role: 'editor',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post(`/w/${workspaceUuid}/invites`, {
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>Convidar membro</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Convidar membro</DialogTitle>
                    <DialogDescription>
                        Envie um convite por email. O usuário precisa ter uma conta no Fin.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="invite-email">Email</Label>
                        <Input
                            id="invite-email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            placeholder="convidado@email.com"
                        />
                        {errors.email && (
                            <p className="text-sm text-destructive">{errors.email}</p>
                        )}
                    </div>

                    <RoleSelect
                        value={data.role}
                        onChange={(role) => setData('role', role)}
                    />

                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={processing}>
                            Enviar convite
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
