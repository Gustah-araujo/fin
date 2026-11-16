import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface ExpenseGroupItem {
    uuid: string;
    name: string;
    color: string;
}

interface ExpenseGroupFieldProps {
    value: string;
    onChange: (value: string) => void;
    groups: ExpenseGroupItem[];
    hint?: string | null;
    error?: string;
}

export function ExpenseGroupField({
    value,
    onChange,
    groups,
    hint,
    error,
}: ExpenseGroupFieldProps) {
    return (
        <div className="space-y-2">
            <Label htmlFor="expense_group_id">Grupo de Despesa</Label>
            <Select value={value} onValueChange={onChange}>
                <SelectTrigger id="expense_group_id">
                    <SelectValue placeholder="Herdar da categoria" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="">Herdar da categoria</SelectItem>
                    {groups.map((group) => (
                        <SelectItem key={group.uuid} value={group.uuid}>
                            {group.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}
