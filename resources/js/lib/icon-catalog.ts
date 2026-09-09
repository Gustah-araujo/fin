import {
    type LucideIcon,
    Activity,
    Award,
    Baby,
    Banknote,
    BarChart,
    Battery,
    Book,
    Bookmark,
    Briefcase,
    Building2,
    Camera,
    Car,
    Cat,
    Cloud,
    Coffee,
    Coins,
    CreditCard,
    Crown,
    Dog,
    DollarSign,
    Droplet,
    Film,
    Flame,
    Flower2,
    Folder,
    Gamepad,
    Gem,
    Gift,
    GraduationCap,
    Headphones,
    Heart,
    Home,
    Key,
    Landmark,
    Laptop,
    Leaf,
    Lightbulb,
    LineChart,
    Lock,
    Medal,
    Moon,
    Music,
    PawPrint,
    Phone,
    PieChart,
    PiggyBank,
    Plane,
    Printer,
    Receipt,
    Shield,
    ShoppingBag,
    ShoppingCart,
    Smartphone,
    Star,
    Stethoscope,
    Sun,
    Target,
    TreePine,
    TrendingDown,
    TrendingUp,
    Trophy,
    Tv,
    Umbrella,
    User,
    Users,
    Utensils,
    Wallet,
    Wifi,
    Wind,
    Zap,
} from 'lucide-react';

export interface IconDefinition {
    key: string;
    name: string;
    component: LucideIcon;
}

export const ICON_CATALOG: IconDefinition[] = [
    // Despesas do dia a dia
    { key: 'shopping-cart', name: 'Shopping Cart', component: ShoppingCart },
    { key: 'shopping-bag', name: 'Shopping Bag', component: ShoppingBag },
    { key: 'home', name: 'Home', component: Home },
    { key: 'car', name: 'Car', component: Car },
    { key: 'utensils', name: 'Utensils', component: Utensils },
    { key: 'coffee', name: 'Coffee', component: Coffee },
    { key: 'heart', name: 'Heart', component: Heart },
    { key: 'briefcase', name: 'Briefcase', component: Briefcase },
    { key: 'music', name: 'Music', component: Music },
    { key: 'gamepad', name: 'Gamepad', component: Gamepad },
    { key: 'folder', name: 'Folder', component: Folder },
    { key: 'book', name: 'Book', component: Book },
    { key: 'film', name: 'Film', component: Film },
    { key: 'camera', name: 'Camera', component: Camera },
    { key: 'gift', name: 'Gift', component: Gift },

    // Educação, saúde e viagem
    { key: 'graduation-cap', name: 'Graduation Cap', component: GraduationCap },
    { key: 'stethoscope', name: 'Stethoscope', component: Stethoscope },
    { key: 'plane', name: 'Plane', component: Plane },
    { key: 'banknote', name: 'Banknote', component: Banknote },

    // Finanças e dinheiro
    { key: 'wallet', name: 'Wallet', component: Wallet },
    { key: 'credit-card', name: 'Credit Card', component: CreditCard },
    { key: 'piggy-bank', name: 'Piggy Bank', component: PiggyBank },
    { key: 'dollar-sign', name: 'Dollar Sign', component: DollarSign },
    { key: 'coins', name: 'Coins', component: Coins },
    { key: 'receipt', name: 'Receipt', component: Receipt },
    { key: 'landmark', name: 'Landmark', component: Landmark },
    { key: 'building', name: 'Building', component: Building2 },
    { key: 'wifi', name: 'Wi-Fi', component: Wifi },
    { key: 'zap', name: 'Zap', component: Zap },

    // Análises e metas
    { key: 'trending-up', name: 'Trending Up', component: TrendingUp },
    { key: 'trending-down', name: 'Trending Down', component: TrendingDown },
    { key: 'line-chart', name: 'Line Chart', component: LineChart },
    { key: 'pie-chart', name: 'Pie Chart', component: PieChart },
    { key: 'bar-chart', name: 'Bar Chart', component: BarChart },
    { key: 'activity', name: 'Activity', component: Activity },
    { key: 'target', name: 'Target', component: Target },
    { key: 'trophy', name: 'Trophy', component: Trophy },

    // Conquistas e status
    { key: 'star', name: 'Star', component: Star },
    { key: 'bookmark', name: 'Bookmark', component: Bookmark },
    { key: 'award', name: 'Award', component: Award },
    { key: 'medal', name: 'Medal', component: Medal },
    { key: 'crown', name: 'Crown', component: Crown },
    { key: 'gem', name: 'Gem', component: Gem },

    // Casa e proteção
    { key: 'lightbulb', name: 'Lightbulb', component: Lightbulb },
    { key: 'flame', name: 'Flame', component: Flame },
    { key: 'shield', name: 'Shield', component: Shield },
    { key: 'umbrella', name: 'Umbrella', component: Umbrella },
    { key: 'key', name: 'Key', component: Key },
    { key: 'lock', name: 'Lock', component: Lock },

    // Tecnologia
    { key: 'phone', name: 'Phone', component: Phone },
    { key: 'smartphone', name: 'Smartphone', component: Smartphone },
    { key: 'tv', name: 'TV', component: Tv },
    { key: 'laptop', name: 'Laptop', component: Laptop },
    { key: 'headphones', name: 'Headphones', component: Headphones },
    { key: 'printer', name: 'Printer', component: Printer },
    { key: 'battery', name: 'Battery', component: Battery },

    // Pessoas e família
    { key: 'users', name: 'Users', component: Users },
    { key: 'user', name: 'User', component: User },
    { key: 'baby', name: 'Baby', component: Baby },

    // Pets
    { key: 'paw-print', name: 'Paw Print', component: PawPrint },
    { key: 'cat', name: 'Cat', component: Cat },
    { key: 'dog', name: 'Dog', component: Dog },

    // Natureza e clima
    { key: 'sun', name: 'Sun', component: Sun },
    { key: 'moon', name: 'Moon', component: Moon },
    { key: 'cloud', name: 'Cloud', component: Cloud },
    { key: 'droplet', name: 'Droplet', component: Droplet },
    { key: 'wind', name: 'Wind', component: Wind },
    { key: 'leaf', name: 'Leaf', component: Leaf },
    { key: 'tree-pine', name: 'Tree', component: TreePine },
    { key: 'flower', name: 'Flower', component: Flower2 },
];

export const ICON_MAP: Record<string, LucideIcon> = ICON_CATALOG.reduce(
    (map, icon) => {
        map[icon.key] = icon.component;
        return map;
    },
    {} as Record<string, LucideIcon>,
);
