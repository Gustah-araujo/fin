import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';

interface Props {
    account: { uuid: string; name: string } | null;
    processing: boolean;
    onOpenChange: (open: boolean) => void;
    onConfirm: () => void;
}

export default function DeleteAccountDialog({
    account,
    processing,
    onOpenChange,
    onConfirm,
}: Props) {
    return (
        <AlertDialog open={!!account} onOpenChange={onOpenChange}>
            <AlertDialogContent data-testid="delete-account-dialog">
                <AlertDialogHeader>
                    <AlertDialogTitle>Excluir conta</AlertDialogTitle>
                    <AlertDialogDescription>
                        {`Tem certeza que deseja excluir a conta "${account?.name}"?`}
                        Esta ação não pode ser desfeita e os lançamentos
                        vinculados a ela ficarão sem conta associada.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel
                        data-testid="delete-account-cancel"
                        disabled={processing}
                    >
                        Cancelar
                    </AlertDialogCancel>
                    <AlertDialogAction
                        data-testid="delete-account-confirm"
                        variant="destructive"
                        disabled={processing}
                        onClick={onConfirm}
                    >
                        Excluir conta
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
