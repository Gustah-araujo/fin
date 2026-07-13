import { type LucideIcon, ShoppingCart, Home, Car, Utensils, Heart, Briefcase, Music, Gamepad, Folder, Banknote, GraduationCap, Plane, Gift, Wifi, Zap, Coffee, Book, Camera, ShoppingBag, Stethoscope, Film } from 'lucide-react';

const iconMap: Record<string, LucideIcon> = {
    'shopping-cart': ShoppingCart,
    'home': Home,
    'car': Car,
    'utensils': Utensils,
    'heart': Heart,
    'briefcase': Briefcase,
    'music': Music,
    'gamepad': Gamepad,
    'folder': Folder,
    'banknote': Banknote,
    'graduation-cap': GraduationCap,
    'plane': Plane,
    'gift': Gift,
    'wifi': Wifi,
    'zap': Zap,
    'coffee': Coffee,
    'book': Book,
    'camera': Camera,
    'shopping-bag': ShoppingBag,
    'stethoscope': Stethoscope,
    'film': Film,
};

interface DynamicIconProps {
    name: string | null;
    className?: string;
    size?: number;
}

export default function DynamicIcon({ name, className, size = 16 }: DynamicIconProps) {
    if (!name) return null;

    const IconComponent = iconMap[name];

    if (!IconComponent) return null;

    return <IconComponent className={className} size={size} />;
}
