import { Landmark, CreditCard } from 'lucide-react';
import { cn } from '@/lib/utils';

type PaymentMethod = 'account' | 'card';

interface PaymentMethodSelectorProps {
    value: PaymentMethod;
    onChange: (value: PaymentMethod) => void;
    disabled?: boolean;
}

const methods: {
    value: PaymentMethod;
    label: string;
    icon: React.ComponentType<{ className?: string }>;
}[] = [
    { value: 'account', label: 'Conta', icon: Landmark },
    { value: 'card', label: 'Cartão de crédito', icon: CreditCard },
];

export function PaymentMethodSelector({
    value,
    onChange,
    disabled = false,
}: PaymentMethodSelectorProps) {
    return (
        <div className="grid grid-cols-2 gap-2">
            {methods.map((method) => {
                const Icon = method.icon;
                const isSelected = value === method.value;

                return (
                    <button
                        key={method.value}
                        type="button"
                        disabled={disabled}
                        onClick={() => onChange(method.value)}
                        className={cn(
                            'flex items-center justify-center gap-2 rounded-md border px-4 py-3 text-sm font-medium transition-colors',
                            isSelected
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-input bg-background text-foreground hover:bg-accent hover:text-accent-foreground',
                            disabled && 'cursor-not-allowed opacity-50',
                        )}
                    >
                        <Icon className="size-4" />
                        {method.label}
                    </button>
                );
            })}
        </div>
    );
}
