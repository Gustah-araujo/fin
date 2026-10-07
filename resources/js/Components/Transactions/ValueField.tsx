import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface ValueFieldProps {
    value: string;
    error?: string;
    onChange: (value: string) => void;
    hidden?: boolean;
}

export function ValueField({
    value,
    error,
    onChange,
    hidden = false,
}: ValueFieldProps) {
    if (hidden) {
        return null;
    }

    return (
        <div className="space-y-2">
            <Label htmlFor="value">Valor</Label>
            <Input
                id="value"
                type="number"
                step="0.01"
                min="0.01"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder="0,00"
            />
            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}
