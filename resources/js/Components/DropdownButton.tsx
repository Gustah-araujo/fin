import type { LucideIcon } from 'lucide-react';
import { ChevronDown } from 'lucide-react';
import type { VariantProps } from 'class-variance-authority';
import { Button, buttonVariants } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

type ButtonVariant = VariantProps<typeof buttonVariants>['variant'];

export interface DropdownItem {
    label: string;
    onSelect: () => void;
    destructive?: boolean;
    disabled?: boolean;
    testId?: string;
}

interface Props {
    label?: string;
    icon?: LucideIcon;
    variant?: ButtonVariant;
    items: DropdownItem[];
    triggerTestId?: string;
}

export type { ButtonVariant };

export default function DropdownButton({
    label,
    icon: Icon,
    variant = 'outline',
    items,
    triggerTestId,
}: Props) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant={variant} data-testid={triggerTestId}>
                    {Icon ? <Icon className="h-4 w-4" /> : null}
                    {label ?? 'Ações'}
                    <ChevronDown className="h-4 w-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {items.map((item) => (
                    <DropdownMenuItem
                        key={item.label}
                        variant={item.destructive ? 'destructive' : 'default'}
                        disabled={item.disabled}
                        onSelect={item.onSelect}
                        data-testid={item.testId}
                    >
                        {item.label}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
