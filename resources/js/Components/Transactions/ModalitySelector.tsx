import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export type ExpenseModality = 'single' | 'recurrence' | 'installment';

interface ModalitySelectorProps {
    value: ExpenseModality;
    onChange: (value: ExpenseModality) => void;
}

const modalities: {
    value: ExpenseModality;
    label: string;
    description: string;
}[] = [
    {
        value: 'single',
        label: 'Avulsa',
        description: 'Um único lançamento',
    },
    {
        value: 'recurrence',
        label: 'Recorrência',
        description: 'Repete automaticamente até a data final',
    },
    {
        value: 'installment',
        label: 'Parcelamento',
        description: 'Divide em parcelas fixas',
    },
];

export function ModalitySelector({ value, onChange }: ModalitySelectorProps) {
    return (
        <RadioGroup
            value={value}
            onValueChange={(val) => onChange(val as ExpenseModality)}
            className="grid gap-2 sm:grid-cols-3"
        >
            {modalities.map((modality) => (
                <Label
                    key={modality.value}
                    htmlFor={`modality-${modality.value}`}
                    className={cn(
                        'flex cursor-pointer items-start gap-2 rounded-md border p-3 font-normal transition-colors',
                        value === modality.value
                            ? 'border-primary bg-primary/5'
                            : 'border-input bg-background hover:bg-accent',
                    )}
                >
                    <RadioGroupItem
                        value={modality.value}
                        id={`modality-${modality.value}`}
                        className="mt-0.5"
                    />
                    <span className="space-y-0.5">
                        <span className="block text-sm font-medium">
                            {modality.label}
                        </span>
                        <span className="block text-xs font-normal text-muted-foreground">
                            {modality.description}
                        </span>
                    </span>
                </Label>
            ))}
        </RadioGroup>
    );
}
