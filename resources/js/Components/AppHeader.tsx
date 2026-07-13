import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet';
import { Button } from '@/components/ui/button';
import { Menu } from 'lucide-react';
import UserMenu from '@/Components/UserMenu';
import { usePage } from '@inertiajs/react';

interface Props {
    collapsed: boolean;
    onToggle: () => void;
    sidebar?: React.ReactNode;
}

export default function AppHeader({ collapsed, onToggle, sidebar }: Props) {
    const { component } = usePage();

    const title = String(component).split('/').pop() || 'Home';

    return (
        <header className="sticky top-0 z-30 flex h-14 items-center gap-3 border-b bg-background px-4">
            <Sheet>
                <SheetTrigger asChild>
                    <Button variant="ghost" size="icon" className="lg:hidden">
                        <Menu className="h-5 w-5" />
                    </Button>
                </SheetTrigger>
                <SheetContent side="left" className="p-0 w-[240px]">
                    {sidebar}
                </SheetContent>
            </Sheet>

            <Button
                variant="ghost"
                size="icon"
                className="hidden lg:flex"
                onClick={onToggle}
            >
                <Menu className="h-5 w-5" />
            </Button>

            <Separator orientation="vertical" className="h-5" />

            <Breadcrumb>
                <BreadcrumbList>
                    <BreadcrumbItem>
                        <BreadcrumbLink href="/">Fin</BreadcrumbLink>
                    </BreadcrumbItem>
                    <BreadcrumbSeparator />
                    <BreadcrumbItem>
                        <BreadcrumbPage>{title}</BreadcrumbPage>
                    </BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>

            <div className="ml-auto flex items-center gap-2">
                <UserMenu />
            </div>
        </header>
    );
}
