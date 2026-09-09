import { type LucideIcon } from 'lucide-react';
import { ICON_MAP } from '@/lib/icon-catalog';

interface DynamicIconProps {
    name: string | null;
    className?: string;
    size?: number;
    style?: React.CSSProperties;
}

export default function DynamicIcon({
    name,
    className,
    size = 16,
    style,
}: DynamicIconProps) {
    if (!name) return null;

    const IconComponent: LucideIcon | undefined = ICON_MAP[name];

    if (!IconComponent) return null;

    return <IconComponent className={className} size={size} style={style} />;
}
