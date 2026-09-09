# UX/UI Small Adjustments Package

**Issue:** [UX/UI] - Pacote de ajustes pequenos de UX e UI
**Date:** 2026-09-09

---

## Adjustment 1: Slim Sidebar Scrollbar

**Current state:** The sidebar `<nav>` uses the browser's native scrollbar with no custom styling (`overflow-y-auto` only).

**Target:** Replace with a slim, styled scrollbar that matches the dark sidebar theme.

**Files to modify:**
- `resources/css/app.css` — add scrollbar styles scoped to the sidebar nav

**Approach:**
- Use `scrollbar-width: thin` (Firefox) and `::-webkit-scrollbar` (WebKit)
- Track color: `var(--sidebar-border)` or transparent — subtle, blends with dark bg
- Thumb color: `var(--sidebar-foreground)` at low opacity (~40%) with hover state
- Width: ~6px for a slim appearance

---

## Adjustment 2: Range Filter Conditional Rendering

**Current state:** Both number and date range filters show both inputs (start + end) at all times. Number inputs are side-by-side; date inputs are stacked vertically.

**Target:**
- Only show the start input initially
- Show the end input only when the start input has a value
- Always stack inputs vertically (start on top)

**Files to modify:**
- `resources/js/Components/DataTable/DataTableFilterInput.tsx`

**Approach:**

For `number` type:
- Change from `flex-row` to `flex-col` layout
- Read the `_min` value; if empty, render only the "Mín" input
- When `_min` has a value, render "Máx" input below it

For `date` type:
- Keep `flex-col` layout
- Read the `_from` value; if empty, render only the "De" input
- When `_from` has a value, render "Até" input below it

---

## Adjustment 3: Filter Row Spacing

**Current state:** Filter inputs sit directly against the cell borders — no internal padding on filter `TableHead` cells.

**Target:** Add horizontal padding to filter cells so inputs have breathing room from table edges.

**Files to modify:**
- `resources/js/Components/DataTable/DataTableFilterRow.tsx`

**Approach:**
- Add `px-3` (or `px-4`) padding class to the `<TableHead>` cells in the filter row
- Keep the existing `text-right` conditional class for right-aligned columns

---

## Adjustment 4: Center Column Headers

**Current state:** Column headers are left-aligned by default (both sortable `<Button>` and non-sortable `<span>`).

**Target:** Always center column header text.

**Files to modify:**
- `resources/js/Components/DataTable/DataTableColumnHeader.tsx`

**Approach:**
- Non-sortable: Add `text-center` class to the `<span>`
- Sortable: Add `justify-center text-center` classes to the `<Button>` to center both text and sort icon

---

## Implementation Order

1. Adjustment 4 (simplest, isolated change)
2. Adjustment 3 (simple padding addition)
3. Adjustment 2 (logic change, most complex)
4. Adjustment 1 (CSS-only, independent)

## Quality Gates

- `npm run lint` passes
- `npm run format` passes
- `npm run build` succeeds (TypeScript compiles)
