# Despesas Month-Bound — Implementation Tasks

**Spec:** `.specs/features/despesas-month-bound/spec.md`
**Story ID:** DEBT-02

---

## Task 1: Month Utility Functions

**Goal:** Create reusable month manipulation utilities.

**File:** `resources/js/lib/month.ts`

- `getCurrentMonth(): string` — returns current month as `YYYY-MM`
- `formatMonthLabel(month: string): string` — formats `2026-09` → `"Setembro de 2026"` (pt-BR)
- `navigateMonth(month: string, delta: number): string` — adds/subtracts months, handles year boundaries
- `parseMonth(month: string): { year: number; month: number }` — parses `YYYY-MM` to parts

**Acceptance:**
- `formatMonthLabel('2026-01')` → `"Janeiro de 2026"`
- `navigateMonth('2026-01', -1)` → `"2025-12"`
- `navigateMonth('2026-12', 1)` → `"2027-01"`
- All functions are pure (no side effects)

---

## Task 2: MonthPicker Component

**Goal:** Build the visual month selector component.

**File:** `resources/js/Components/MonthPicker/MonthPicker.tsx`

**Props:**
```typescript
interface MonthPickerProps {
    month: string; // YYYY-MM
    onChange: (month: string) => void;
}
```

**Structure:**
- `<div className="flex items-center gap-2">`
  - `<Button variant="outline" size="icon">` with `<ChevronLeft />` → calls `onChange(navigateMonth(month, -1))`
  - `<span className="text-sm font-medium min-w-[160px] text-center">` showing `formatMonthLabel(month)`
  - `<Button variant="outline" size="icon">` with `<ChevronRight />` → calls `onChange(navigateMonth(month, 1))`

**Imports:** `ChevronLeft`, `ChevronRight` from `lucide-react`; `Button` from `@/components/ui/button`; utility functions from `@/lib/month`.

**Acceptance:**
- Renders with correct month label
- Previous/next buttons call `onChange` with correct adjacent month
- Follows design system spacing and typography

---

## Task 3: Backend — Month Filter in Datatable

**Goal:** Add month filtering to the expenses datatable query.

**File:** `app/Http/Controllers/TransactionController.php`

**Changes to `datatable()` method:**

After the existing `->where('type', TransactionType::Expense)` line, add:

```php
$month = $request->input('month');
if (is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month)) {
    [$year, $monthNum] = explode('-', $month);
    $query->whereYear('date', (int) $year)
          ->whereMonth('date', (int) $monthNum);
}
```

**Acceptance:**
- When `month=2026-09` is passed, only September 2026 expenses are returned
- When no `month` param is passed, all expenses are returned (no regression)
- Invalid `month` format is ignored (falls back to unfiltered)
- Workspace scope is still enforced

---

## Task 4: Frontend — Integrate MonthPicker into Transactions/Index

**Goal:** Wire the MonthPicker into the expenses listing page.

**File:** `resources/js/Pages/Transactions/Index.tsx`

**Changes:**

1. **Parse month from URL:**
   ```typescript
   const page = usePage();
   const urlParams = new URLSearchParams(page.url.split('?')[1] ?? '');
   const [month, setMonth] = useState(() => urlParams.get('month') ?? getCurrentMonth());
   ```

2. **Handle month change:**
   ```typescript
   function handleMonthChange(newMonth: string) {
       setMonth(newMonth);
       router.visit(
           route('transactions.index', { workspace: workspace.uuid, month: newMonth }),
           { preserveState: true, preserveScroll: false, replace: true }
       );
   }
   ```

3. **Restructure header layout:**
   ```tsx
   <div className="flex items-center justify-between">
       <div className="flex items-center gap-4">
           <h1 className="text-2xl font-semibold tracking-tight">Despesas</h1>
           <MonthPicker month={month} onChange={handleMonthChange} />
       </div>
       <div className="flex items-center gap-2">
           <Button variant="outline" asChild>...</Button>  {/* Importar Despesas */}
           <Button asChild>...</Button>  {/* Nova Despesa */}
       </div>
   </div>
   ```

4. **Pass month to datatable endpoint:**
   ```tsx
   <DataTable
       endpoint={route('transactions.datatable', { workspace: workspace.uuid, month })}
       ...
   />
   ```

**Acceptance:**
- MonthPicker renders in the header
- Clicking previous/next updates the URL and datatable
- Page refresh preserves the selected month
- Action buttons remain visible and functional
- Datatable shows only expenses for the selected month

---

## Task 5: PHPUnit Feature Tests

**Goal:** Test the month-bound filtering logic.

**File:** `tests/Feature/Transactions/TransactionMonthBoundTest.php`

**Setup:** Create workspace with user, create expenses across multiple months.

**Tests:**
1. `test_datatable_filters_by_month_param` — expenses from other months excluded
2. `test_datatable_returns_all_without_month_param` — no regression when no month passed
3. `test_datatable_ignores_invalid_month_format` — invalid format = no month filter
4. `test_month_filter_respects_workspace_scope` — cross-workspace expenses excluded
5. `test_month_filter_with_year_boundary` → January/December rollover works
6. `test_month_filter_composes_with_status_filter` — month + paid/unpaid both apply

**Acceptance:** All tests pass via `php artisan test --filter=TransactionMonthBoundTest`

---

## Task 6: PHPUnit Smoke Test

**Goal:** Add smoke test for the transactions index route.

**File:** `tests/Feature/Transactions/TransactionSmokeTest.php` (new file)

**Tests:**
1. `test_transactions_index_returns_ok` — GET without month param → 200 + Inertia component
2. `test_transactions_index_returns_ok_with_month_param` — GET with `?month=2026-09` → 200 + Inertia component

**Acceptance:** Both tests pass.

---

## Task 7: Cypress E2E Tests

**Goal:** Validate the complete user journey.

**File:** `cypress/e2e/transactions-month-bound.cy.js`

**Setup:** `cy.loginViaSession()`, create workspace, create expenses in 2+ months.

**Tests:**
1. `renders without crashing` — smoke: month picker visible
2. `shows current month by default` — label matches current month
3. `navigates to next month` — click ">", label updates, datatable reloads
4. `navigates to previous month` — click "<", label updates
5. `preserves month on refresh` — URL param persists
6. `action buttons are visible` — both buttons render

**Acceptance:** All tests pass via `cypress run`

---

## Task 8: Quality Gate

**Goal:** Ensure all code passes static analysis and formatting.

**Commands:**
```bash
composer quality    # Pint + PHPMD
composer format     # Auto-fix Pint issues
npm run quality     # ESLint + Prettier
npm run format      # Auto-fix ESLint/Prettier issues
```

**Acceptance:** Zero errors/warnings from all quality tools.

---

## Dependency Order

```
Task 1 (month utils) → Task 2 (MonthPicker component)
                    ↘ Task 3 (backend filter) — parallel with Task 2
Task 2 + Task 3 → Task 4 (integrate into Index page)
Task 3 → Task 5 (PHPUnit tests)
Task 3 → Task 6 (Smoke test)
Task 4 → Task 7 (Cypress E2E)
Task 5 + Task 6 + Task 7 → Task 8 (Quality gate)
```
