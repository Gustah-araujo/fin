import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import type { InertiaForm } from '@inertiajs/react';

interface AccountOption {
    id: string;
    name: string;
    type: string;
    balance: number;
}

interface AccountFieldsProps {
    accounts: AccountOption[];
    form: InertiaForm<{
        account_id: string;
    }>;
}

function formatCurrency(value: number): string {
    return value.toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });
}

export function AccountFields({ accounts, form }: AccountFieldsProps) {
    return (
        <div className="space-y-2">
            <Label htmlFor="account_id">Conta</Label>
            <Select
                value={form.data.account_id}
                onValueChange={(value) => form.setData('account_id', value)}
            >
                <SelectTrigger id="account_id">
                    <SelectValue placeholder="Selecione a conta" />
                </SelectTrigger>
                <SelectContent>
                    {accounts.map((account) => (
                        <SelectItem key={account.id} value={account.id}>
                            {account.name} ({account.type}) — Saldo:{' '}
                            {formatCurrency(account.balance)}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            {form.errors.account_id && (
                <p className="text-sm text-destructive">
                    {form.errors.account_id}
                </p>
            )}
        </div>
    );
}
