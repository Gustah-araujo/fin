# Plan — Migrate shadcn UI from `base-nova` (Base UI) to `new-york` (Radix) + Combobox Select

## Problem

The project claims shadcn/ui, but every `resources/js/components/ui/*.tsx` primitive is built on
`@base-ui/react-*`. Root cause: `components.json` uses `style: "base-nova"` — a shadcn style that
wraps Base UI primitives instead of the canonical Radix primitives (`new-york`).

The user additionally requires: **the Select input must always be a Combobox** (Popover + Command),
not a native/radix `<select>`. Filtering and async option search must be supported as **optional**
parameters, off by default.

## Scope of "wrong lib" (confirmed)

16 files import `@base-ui/react-*`:
`avatar, badge, breadcrumb, button, checkbox, collapsible, dialog, dropdown-menu, input, popover,
radio-group, select, separator, sheet, switch, tooltip`.

Plus `card.tsx` and `label.tsx` are base-nova styled (no base-ui primitive) and get replaced too.
`color-picker.tsx` is a custom app component (uses Button/Input/Popover) — not replaced, only verified.

## Consumer-safety finding (from explorer inventory)

No consumer outside `ui/` imports any base-nova-only export. Risky exports are all unused:

| base-nova-only export | consumers |
|---|---|
| `AvatarBadge`, `AvatarGroup`, `AvatarGroupCount` | none |
| `CardAction`, `Card size="sm"` | none |
| badge `ghost`/`link` variants | none |
| `PopoverTitle/Description/Header` | none |
| `DialogPortal/Overlay` | none |
| `DropdownMenuSub*`, `DropdownMenuCheckboxItem/RadioItem/Shortcut` | none |
| `SelectGroup/Label/ScrollUpButton/ScrollDownButton/Separator` | none |
| button `icon-xs/icon-sm/icon-lg` sizes | none |
| `SheetPortal/SheetOverlay` | not exported; none |
| `buttonVariants`, `badgeVariants`, `BreadcrumbEllipsis` | none |

**Conclusion:** swap all 18 files to new-york internals while keeping the same named-export block.
No import line in Pages/Components/Layouts/hooks changes. `asChild` is used widely (Button,
DialogTrigger, DropdownMenuTrigger, SheetTrigger, PopoverTrigger, TooltipTrigger) and is natively
supported by new-york Radix components.

---

## Target design

- `components.json`: `style` → `new-york`. Keep `baseColor: neutral` (matches current theme).
- `resources/css/app.css`: remove base-nova-only `@import "shadcn/tailwind.css";` (its `cn-menu-*`
  utilities are unused after migration). Keep `@import "tw-animate-css";` (powers `animate-in/out`).
  Keep all `:root`/`.dark` OKLCH tokens (fin-design-system owns them; do not touch).
- Dependencies:
  - Add: `@radix-ui/react-avatar`, `@radix-ui/react-checkbox`, `@radix-ui/react-collapsible`,
    `@radix-ui/react-dialog`, `@radix-ui/react-dropdown-menu`, `@radix-ui/react-label`,
    `@radix-ui/react-popover`, `@radix-ui/react-radio-group`, `@radix-ui/react-separator`,
    `@radix-ui/react-slot`, `@radix-ui/react-switch`, `@radix-ui/react-tooltip`, `cmdk`.
  - Remove: `@base-ui/react`.
  - No `@radix-ui/react-select` (Select is now a Combobox).

---

## Select-as-Combobox API (the key new file)

New `resources/js/components/ui/select.tsx` = Combobox built on `Popover` + `Command` (cmdk).
Keep the consumer-facing export surface identical so **zero page changes are required**:

```ts
export {
  Select,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
  SelectGroup,     // thin wrapper -> CommandGroup
  SelectLabel,     // -> CommandGroup heading
  SelectSeparator, // -> CommandSeparator
}
```

Dropped (no consumer): `SelectScrollUpButton`, `SelectScrollDownButton`.

### Root component `Select` props

```ts
interface SelectProps {
  value?: string                       // controlled
  defaultValue?: string                // uncontrolled
  onValueChange?: (value: string) => void
  open?: boolean
  defaultOpen?: boolean
  onOpenChange?: (open: boolean) => void
  disabled?: boolean
  // NEW optional params (default off):
  filter?: boolean                     // default false — render search input
  onSearch?: (query: string) => void   // async options search hook (optional)
  loading?: boolean                    // show "loading" state while fetching
  emptyMessage?: string                // default "Nenhum resultado."
  children: React.ReactNode
}
```

### Behavior matrix

| config | search input | filtering |
|---|---|---|
| `filter` omitted / `false` | none | none — all items shown |
| `filter={true}` | shown | local cmdk filter on item label |
| `filter={true}` + `onSearch` | shown | local filter OFF (`shouldFilter={false}`); parent swaps children from async fetch |

### Sub-components

- `SelectTrigger` → `PopoverTrigger asChild` → `Button variant="outline" role="combobox"` with
  chevron; forwards `id`, `className`, `disabled`, `aria-invalid`.
- `SelectValue` → reads selected label from context; renders `placeholder` when empty.
- `SelectContent` → `PopoverContent` (align/sideOffset) wrapping `Command`; renders `CommandInput`
  only when `filter`; wraps children in `CommandList`; shows `CommandEmpty` when no items.
- `SelectItem` → `CommandItem` with `value={label}` (for label-based filtering),
  `onSelect={() => select(actualValue)}`, check icon when `actualValue === selected`.
  Registers `{ value, label }` into context so `SelectValue` can resolve the label.
  Optional `searchValue?: string` prop overrides the filter string when children is not a plain
  string (e.g. rich label).

### Selection model

Context tracks `value` (controlled or internal state). Selecting an item calls `onValueChange`
then closes the Popover. Label resolution: context keeps a map `value -> label` built by
`SelectItem` registration (useEffect + useMemo), so `SelectValue` always shows the current label.

---

## Task breakdown (ordered, atomic)

**T1 — Switch style + reinstall primitives (shadcn CLI)**
1. Edit `components.json`: `"style": "base-nova"` → `"new-york"`.
2. `npx shadcn@latest add -y -o avatar badge breadcrumb button card checkbox collapsible dialog dropdown-menu input label popover radio-group separator sheet switch tooltip`
   (do NOT re-add `select` — it gets replaced in T3).
3. `npx shadcn@latest add -y command` (adds `command.tsx` + `cmdk`).

Gate: `grep -rn "@base-ui" resources/js/components/ui` returns nothing except `select.tsx`
(expected, still old until T3).

**T2 — Remove base-nova CSS import**
- In `resources/css/app.css`, delete `@import "shadcn/tailwind.css";` (line 3). Keep
  `tw-animate-css`. Verify no `cn-` utility classes remain in `resources/js` after T1.

Gate: `grep -rn "cn-menu\|cn-card\|cn-\(group\|input\)" resources/js` → no matches.

**T3 — Write combobox `select.tsx`**
- Implement the Select-as-Combobox API above. Replace the old base-ui file entirely.

**T4 — Uninstall Base UI**
- `npm uninstall @base-ui/react`.
Gate: `grep -rn "@base-ui/react" resources/js` → no matches; `package.json` no longer lists it.

**T5 — Verify color-picker + consumer smoke**
- Confirm `color-picker.tsx` (Popover/Button/Input + `asChild`) still type-checks against new-york.

**T6 — Quality gates**
- `npm run quality` (ESLint + Prettier).
- Typecheck (TS strict): `npx tsc --noEmit` (adjust command if the `typescript` npm alias in
  package.json requires it).
- `npm run build` (Vite production build).

**T7 — Manual smoke**
- `npm run dev`; open a form with Select (e.g. `Pages/Categories/Create.tsx` type select, and
  `Pages/Incomes/Create.tsx` account/category selects) and confirm the combobox opens, selects,
  and — with `filter`/`onSearch` — filters / async-searches.

---

## Risks / notes

- **Visual diff**: new-york components have slightly different padding/radii than base-nova. Theme
  tokens (OKLCH) are unchanged, so palette/typography stay intact. Accept minor spacing drift.
- **Dialog/sheet `size` variants**: base-nova `DialogContent`/`SheetContent` had `size` prop;
  consumers don't use it — new-york doesn't have it, safe.
- **`data-slot` attributes** are dropped in new-york; no consumer queries them.
- **`shadcn` package**: keep installed (CLI). Only its `tailwind.css` import is removed.

## Out of scope

- No page/consumer refactors (inventory proves unnecessary).
- No backend changes.
- Adding `filter`/`onSearch` to actual page selects — default remains off; only the capability is
  delivered. (Optional follow-up: enable `filter` on account/category selects in Incomes/Recurrences.)
