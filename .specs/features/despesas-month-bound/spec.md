# Despesas Month-Bound — Specification

**Story ID:** DEBT-02
**Phase:** P1 — MVP Enhancement
**Parent Spec:** `.specs/features/workspace-financeiro/spec.md`
**Created:** 2026-09-11

## Problem Statement

Currently, the expenses listing page shows all expense records in a flat datatable sorted by date descending, with no temporal context. For a financial system, time-period context is fundamental — users need to see "what happened in September" without manually configuring date-range filters every time. This feature makes the expenses page "month-bound": a month selector at the top filters the datatable to the selected period, with intuitive previous/next navigation.

## Goals

- [ ] Add a Month Picker component above the datatable with previous/next month navigation
- [ ] Datatable reacts to month changes, fetching only expenses for the selected period
- [ ] Default sort remains chronological (date descending)
- [ ] Action buttons ("Importar Despesas", "Nova Despesa") preserved, integrated into the new layout
- [ ] Month state is URL-addressable (query param) so refresh preserves the selected month

## Key Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Scope | Debit expenses only (`type = expense`, `account_id != null`) | Credit card expenses have their own flow (CCXP-01) |
| Month representation | `YYYY-MM` string in URL query param `month` | Simple, parseable, locale-independent |
| Default month | Current month on first visit | Most common use case |
| Backend filtering | Add `month` param to datatable query → `whereYear`/`whereMonth` on `date` column | Reuses existing DatatableService infrastructure |
| Frontend state | React state in Index page, synced to URL via `router.visit` with `preserveState` | Inertia-pure pattern; no extra dependencies |
| Sort default | Keep `date desc` (newest first within the month) | Already the current default; chronological order preserved |

## Out of Scope

| Feature | Reason |
|---------|--------|
| Month picker on Incomes page | Separate story (INCM-02) if requested |
| Date range picker replacement | The existing date-range filter remains available alongside the month picker |
| Recurring expense projection | FUTX-01 (future expenses) is a separate feature |
| Export by month | IMPT-01 scope |

---

## User Stories

### DEBT-02.1 — View Expenses for Current Month

**WHEN** a user opens the expenses page, **THEN** the system SHALL display expenses for the current month only, with the month selector showing the current month/year.

**Acceptance Criteria:**
- On initial load (no `month` query param), the page defaults to the current month
- The datatable shows only transactions where `date` falls within the selected month
- The month selector displays the month/year in Brazilian format (e.g., "09/2026")

### DEBT-02.2 — Navigate Between Months

**WHEN** a user clicks the "previous" or "next" button on the month selector, **THEN** the system SHALL update the datatable to show expenses for the adjacent month.

**Acceptance Criteria:**
- Previous button (`<`) navigates to the prior month
- Next button (`>`) navigates to the next month
- URL query param `month` updates to reflect the selected period
- Datatable resets to page 1 and fetches new data
- Buttons are always enabled (no boundary restrictions — users can navigate to any month)

### DEBT-02.3 — Month State Persists on Refresh

**WHEN** a user refreshes the page, **THEN** the system SHALL preserve the selected month.

**Acceptance Criteria:**
- If URL has `?month=2026-09`, the page loads with September 2026 selected
- If URL has no `month` param, defaults to current month

### DEBT-02.4 — Action Buttons Preserved

**WHEN** the month-bound layout is active, **THEN** the "Importar Despesas" and "Nova Despesa" buttons SHALL remain visible and functional.

**Acceptance Criteria:**
- Both buttons render in the header area
- "Importar Despesas" → `transactions.import.create`
- "Nova Despesa" → `transactions.create`
- Layout: month selector on the left, action buttons on the right

---

## Wireframe

```
┌─────────────────────────────────────────────────────────────────────────┐
│                                                                         │
│  [ < ]  09/2026  [ > ]               [ Importar Despesas ] [ Nova Despesa ]
│                                                                         │
│  ┌───────────────────────────────────────────────────────────────────┐  │
│  │ Data ▼     │ Descrição           │ Categoria   │ Valor    │ Ações │  │
│  ├───────────────────────────────────────────────────────────────────┤  │
│  │ 10/09/2026 │ Supermercado Mensal │ Alimentação │ R$ 450,00│ [...] │  │
│  │ 05/09/2026 │ Conta de Luz        │ Moradia     │ R$ 120,00│ [...] │  │
│  │ 02/09/2026 │ Assinatura Cloud    │ Tecnologia  │ R$  80,00│ [...] │  │
│  └───────────────────────────────────────────────────────────────────┘  │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Technical Design

### Frontend

#### New Component: `MonthPicker`

**Location:** `resources/js/Components/MonthPicker/MonthPicker.tsx`

A reusable component that renders:
- A "previous" button (ChevronLeft icon)
- The current month label in Brazilian format (e.g., "09/2026")
- A "next" button (ChevronRight icon)

**Props:**
```typescript
interface MonthPickerProps {
    month: string; // YYYY-MM format
    onChange: (month: string) => void;
}
```

**Behavior:**
- `formatMonthLabel('2026-09')` → "09/2026" (Brazilian MM/YYYY format)
- Previous: subtract 1 month (handle January → December of prior year)
- Next: add 1 month (handle December → January of next year)

**Styling:** Follows the design system — uses shadcn `Button` (variant="outline", size="icon") for navigation, `text-sm font-medium` for the label.

#### Modified Page: `Transactions/Index.tsx`

Changes:
1. Parse `month` from URL query params on initial render (via `usePage().url` → `URLSearchParams`)
2. Maintain `month` state (default: current month `YYYY-MM`)
3. Render `MonthPicker` above the datatable
4. Pass `month` as `initialFilters` to `DataTable` (or as a query param to the datatable endpoint)
5. On month change: update state, reset datatable filters, trigger reload
6. Restructure header: month selector left, action buttons right

#### Month Utility Functions

**Location:** `resources/js/lib/month.ts`

```typescript
function getCurrentMonth(): string;           // → "2026-09"
function formatMonthLabel(month: string): string; // → "09/2026"
function navigateMonth(month: string, delta: number): string; // delta = ±1
```

### Backend

#### Modified Controller: `TransactionController::datatable()`

Add month filtering to the query:

```php
// After existing type filter
if ($request->has('month') && preg_match('/^\d{4}-\d{2}$/', $request->input('month'))) {
    [$year, $month] = explode('-', $request->input('month'));
    $query->whereYear('date', (int) $year)->whereMonth('date', (int) $month);
}
```

This is applied **before** the DatatableService processes filters/sort/pagination, so the month scope acts as a base filter that the per-column filters further refine.

#### Alternative: Add as a Datatable Filter

Could also be implemented as a new `Filter::month('date')` type, but since it's a structural page-level filter (not a per-column filter the user toggles), applying it directly in the controller is simpler and more explicit.

### Data Flow

```
User clicks ">" on MonthPicker
  → navigateMonth(currentMonth, +1) → "2026-10"
  → setMonth("2026-10")
  → router.visit(route('transactions.index', { workspace, month: "2026-10" }), { preserveState: true, preserveScroll: false })
  → Controller receives month param
  → Query scoped to October 2026
  → DatatableService paginates filtered results
  → Frontend re-renders with new data
```

---

## Test Strategy

### PHPUnit Feature Tests

**File:** `tests/Feature/Transactions/TransactionMonthBoundTest.php`

| Test | What it verifies |
|------|-----------------|
| `test_expenses_page_defaults_to_current_month` | Without `month` param, only current-month expenses appear |
| `test_expenses_page_filters_by_month_param` | With `?month=2026-08`, only August expenses appear |
| `test_expenses_page_excludes_other_months` | Expenses from non-selected months are excluded |
| `test_expenses_page_accepts_valid_month_format` | `YYYY-MM` format is accepted |
| `test_expenses_page_ignores_invalid_month_format` | Invalid format falls back to current month |
| `test_month_filter_respects_workspace_scope` | Only expenses from the active workspace are returned |
| `test_month_filter_with_year_boundary` | January navigation works correctly (year rollover) |
| `test_month_filter_preserves_other_datatable_filters` | Month filter composes with description/category/status filters |

### PHPUnit Smoke Test

Add to existing or new smoke test file:

| Test | What it verifies |
|------|-----------------|
| `test_transactions_index_returns_ok_with_month_param` | GET `/w/{workspace}/transactions?month=2026-09` returns 200 + correct Inertia component |

### Cypress E2E Tests

**File:** `cypress/e2e/transactions-month-bound.cy.js`

| Test | What it verifies |
|------|-----------------|
| `renders without crashing` | Smoke: page loads with month picker visible |
| `shows current month by default` | Month label shows current month/year |
| `navigates to next month` | Clicking ">" updates the month label and datatable |
| `navigates to previous month` | Clicking "<" updates the month label and datatable |
| `preserves month on refresh` | URL `?month=` param is preserved after reload |
| `action buttons are visible` | "Importar Despesas" and "Nova Despesa" buttons render |
| `datatable shows only selected month data` | After navigation, visible dates belong to the selected month |

---

## Definition of Done

1. **Technical Standards:** Code follows the project stack (Laravel, React with Inertia.js, TailwindCSS v4)
2. **Data Isolation:** All queries strictly respect the active workspace scope
3. **Visual Feedback:** Mutations use Toast (Sonner/shadcn) for success/error
4. **Code Quality:** Passes all static checks (`composer quality`, `npm run quality`)
5. **Test Coverage:** All PHPUnit feature tests + smoke tests pass; Cypress E2E tests pass
6. **UI in pt-BR:** Month labels, buttons, and messages in Brazilian Portuguese

---

## Traceability

| Requirement | Implementation | Test |
|-------------|---------------|------|
| DEBT-02.1 | MonthPicker + controller month filter | `test_expenses_page_defaults_to_current_month` |
| DEBT-02.2 | MonthPicker navigation + URL sync | `test_navigates_to_next_month`, `test_navigates_to_previous_month` |
| DEBT-02.3 | URL query param persistence | `test_preserves_month_on_refresh` |
| DEBT-02.4 | Header layout restructure | `test_action_buttons_are_visible` |
