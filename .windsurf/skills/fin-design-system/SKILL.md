---
name: fin-design-system
description: Visual design system, layout patterns, and component conventions for the Fin financial assistant. Use when building or modifying any React component in this project. Triggers on creating layouts, pages, components, or styling work.
---

# Fin Design System

Visual patterns, layout architecture, and component conventions for the Fin project. Every new page or component must follow these rules.

## Design Direction

**Tone:** Refined financial — trustworthy, clean, with subtle warmth. Not cold/banking, not playful.
**Aesthetic:** Dark sidebar with indigo accent. Light content area. Instrument Sans throughout.
**Status:** Only light mode implemented. Dark mode variables are defined but not active.

## Color Tokens

All colors use OKLCH via CSS custom properties defined in `resources/css/app.css`.
Use existing shadcn variables — never hardcode color values.

### Semantic Variables

| Variable | Light Value (OKLCH) | Purpose |
|-----------|---------------------|---------|
| `--background` | `0.985 0.002 270` | Main page background — slightly warm off-white |
| `--foreground` | `0.21 0.01 270` | Primary text |
| `--primary` | `0.511 0.262 276.966` | Indigo — primary actions, focus rings |
| `--accent` | `0.945 0.03 276.966` | Subtle indigo tint for hover states |
| `--destructive` | `0.577 0.245 27.325` | Red — errors, negative values |
| `--sidebar` | `0.21 0.02 270` | Dark navy sidebar background |

### Financial Value Colors

Use these Tailwind classes for money values (not custom colors):

```
text-emerald-600  → positive amounts (income, gains)
text-rose-600     → negative amounts (expenses, losses)
text-amber-600    → warnings (pending payments, overdue)
```

### Never

- Hardcode hex/rgb/oklch values in components
- Use gray-on-colored-bg text — tint text to match the background hue
- Pure black (#000) or pure white (#fff)

## Typography

**Font:** Instrument Sans (already loaded via `bunny()` in vite config)
**Mono (numbers):** JetBrains Mono (font variable defined, font not yet loaded)

### Text Scale

| Class | Use |
|-------|-----|
| `text-3xl tracking-tight font-bold` | Page titles (top-level heading) |
| `text-2xl font-semibold tracking-tight` | Section headings |
| `text-lg font-semibold` | Card titles |
| `text-sm font-medium text-muted-foreground` | Subtitles, labels |
| `text-sm` | Body text |
| `text-xs text-muted-foreground` | Meta info, captions |
| `text-[10px] uppercase tracking-wider font-semibold` | Sidebar section labels |

### Monetary Values

Always use `font-semibold` for money values. Align values right in tables.
Prefix with `R$` (pt-BR locale).

```tsx
<p className="text-2xl font-semibold">R$ 1.250,00</p>
<p className="text-2xl font-semibold text-emerald-600">R$ 850,00</p>
<p className="text-2xl font-semibold text-rose-600">-R$ 420,00</p>
```

## Layout Architecture

### Component Hierarchy

```
AuthenticatedLayout                  # Top-level wrapper
├── TooltipProvider                  # Required by shadcn tooltips
├── AppSidebar                       # Fixed left sidebar (hidden on mobile)
│   ├── Logo/brand                   # "F" icon + "Fin" text
│   ├── Nav sections + items         # Grouped navigation links
│   └── Workspace info + toggle      # Bottom section
├── AppHeader                        # Sticky top bar
│   ├── Sheet (mobile sidebar)       # lg:hidden — slides sidebar from left
│   ├── Collapse toggle              # hidden lg:flex — toggles sidebar
│   ├── Breadcrumb                   # Auto-generated from page component name
│   └── UserMenu                     # Avatar dropdown (right-aligned)
└── <main>                           # Content area — p-6 padding
```

### Sidebar: Collapsed vs Expanded

- **Expanded:** 240px wide, shows labels
- **Collapsed:** 68px wide, icons only with tooltips on hover
- State managed in `AuthenticatedLayout`, passed down as props
- Toggle button: `ChevronLeft` when expanded, `ChevronRight` when collapsed
- Always expanded on mobile (inside Sheet)

### Sidebar Navigation

Nav items defined as TypeScript constants in `AppSidebar.tsx`:

```tsx
interface NavItem { label: string; href: string; icon: LucideIcon; }
interface NavSection { title: string; items: NavItem[]; }
```

Sections are grouped with a tiny uppercase label (hidden when collapsed).
Active item: `bg-sidebar-accent` + `text-sidebar-accent-foreground`.
Inactive items: `text-sidebar-foreground/60` → `text-sidebar-foreground` on hover.

### Breadcrumbs

Auto-generated from the page component name. The `usePage().component` value (e.g., `"Accounts/Index"`) is used; the last segment becomes the page title. No manual breadcrumb config needed.

### Page Wrapper Convention

Every authenticated page wraps its content in `<AuthenticatedLayout>`:

```tsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function MyPage() {
    return (
        <AuthenticatedLayout>
            {/* page content */}
        </AuthenticatedLayout>
    );
}
```

There is also a `GuestLayout` (not yet implemented) for login/register pages.

## Component Patterns

### shadcn/ui Rules

- Install via `npx shadcn@latest add <component>` — NEVER modify files in `Components/ui/`
- All shadcn components live in `Components/ui/` (lowercase `ui`)
- Compose primitives into domain components in `Components/[Domain]/`
- Custom components in `Components/` (PascalCase: `AppSidebar.tsx`)

### Cards

```tsx
<Card>
    <CardHeader className="pb-2">
        <CardTitle className="text-sm font-medium text-muted-foreground">
            Label
        </CardTitle>
    </CardHeader>
    <CardContent>
        <p className="text-2xl font-semibold">Value</p>
    </CardContent>
</Card>
```

**Key:** `pb-2` on CardHeader to tighten spacing with CardContent. This prevents the default padding gap from looking loose on metric cards.

### Dashboard Widget Grid

```tsx
<div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
    {/* metric cards */}
</div>
```

### Forms (Future)

Forms will use `useForm()` from Inertia with shadcn form primitives:

```tsx
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
```

Error display follows Inertia conventions: `form.errors.field` rendered as red text below the input.

### Buttons

| Type | Variant | When |
|------|---------|------|
| Primary action | `default` (indigo) | Submit, save, main CTA |
| Secondary | `outline` | Cancel, back |
| Destructive | `destructive` | Delete, remove |
| Icon-only | `ghost size="icon"` | Toolbar actions, toggles |
| Sidebar toggle | `ghost` | Collapse/expand |

## Spacing

- Page content: `p-6` (24px) — inside `<main>`
- Section gaps: `space-y-6` between major sections
- Card grid: `gap-4` for dashboard widgets, `gap-6` for detail layouts
- Header height: `h-14` (56px) — fixed
- Sidebar item: `py-2` vertical, `px-3` horizontal

## File Structure

```
resources/js/
├── Components/
│   ├── ui/                    # shadcn/ui primitives (NEVER edit)
│   ├── AppSidebar.tsx         # Main sidebar navigation
│   ├── AppHeader.tsx          # Top header bar
│   └── UserMenu.tsx           # Avatar dropdown
├── Layouts/
│   └── AuthenticatedLayout.tsx  # Sidebar + header + main wrapper
├── Pages/
│   └── Home.tsx               # Dashboard (example)
├── hooks/                     # Shared hooks (future)
└── lib/
    └── utils.ts               # shadcn cn() utility
```

## Anti-Patterns

- Never hardcode colors — use CSS variables or Tailwind semantic classes
- Never modify `Components/ui/*` files — they are shadcn-managed
- Never create inline layout (sidebar+header+main) — always wrap in `AuthenticatedLayout`
- Never use `@/components/` (lowercase) for our Components — use `@/Components/`
- Never nest cards inside cards without purpose
- Never make every button primary — use hierarchy
