# Receitas — Month-Bound View + Totalizadores

**Issue:** Aplicar o mesmo layout de vista scopada por mês da listagem de despesas, na listagem de receitas, com mesmo seletor de mês, e com cards totalizadores no topo.
**Story ID (proposed):** `INCM-07` — *the despesas specs referenced "INCM-02" as the follow-up story, but INCM-02 is already taken by "Confirmação de Recebimento" in `receitas/spec.md`. Use a fresh ID.*
**Date:** 2026-11-11
**Author:** Gustah-araujo

---

## Objective

Replicate the despesas (`Transactions/Index.tsx`) month-scoped layout onto the receitas listing (`Incomes/Index.tsx`):

1. Same `MonthPicker` selector in the header, driven by a `?month=YYYY-MM` query param.
2. Datatable scoped to the selected month (backend `whereYear`/`whereMonth` on `date`).
3. Three totalizer cards between the header and the table:
   - **Total de entradas do mês** (sum of all incomes in the month)
   - **Total já recebido** (`paid_at IS NOT NULL`)
   - **Total a receber** (`paid_at IS NULL`)

## Current State vs. Target (side-by-side)

| Aspect | Despesas (has it) | Receitas (to add) |
|---|---|---|
| Controller | `TransactionController` | `IncomeController` |
| Month scoping (backend) | `datatable()` reads `?month=` | ❌ none |
| Totals | `TransactionService::monthlyTotals()` → `totals` prop | ❌ none |
| MonthPicker (frontend) | `MonthPicker` in header + `?month=` sync | ❌ none |
| Summary cards | 3 cards (`total`/`paid`/`pending`) | ❌ none |
| Type filter | `where('type', Expense)` | `where('type', Income)` |

## Key Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Totals keys | Reuse `{ total, paid, pending }` shape from expenses | Keeps a single `Totals` interface shape; "paid" semantically means "received" for incomes (already the convention: income datatable `status` filter uses `paid`/`unpaid`, and confirmation reuses `TransactionService::pay()`/`unpay()`) |
| Totals service method | New `TransactionService::monthlyIncomeTotals(Workspace, ?string $month)` filtering `type = Income` | Mirrors `monthlyTotals()` (expense) without touching it; keeps expense tests intact. Extract a private `monthlyTotalsFor(Workspace, ?string $month, TransactionType $type)` helper to avoid duplication |
| Status semantics | `paid_at IS NOT NULL` → "recebido"; `IS NULL` → "a receber" | Mirrors the existing income `status` filter and `is_paid` resource field |
| Month scoping | `?month=YYYY-MM` query param (not a route param), regex `^\d{4}-\d{2}$` | Same as expenses; drives both `index()` totals and `datatable()` filtering |
| No/invalid month | Totals default to current month; datatable returns all rows | Matches expense behavior exactly |
| Frontend state | `month` React state seeded from URL param, default `getCurrentMonth()`; `router.visit` with `preserveState: true, replace: true` | Same as expenses |
| Datatable remount | `key={month}` + `month` in the `incomes.datatable` endpoint | Resets pagination/filters on month change, same as expenses |
| No `credit_card_bill_id` override | Skip the bill-override guard | Incomes have no `credit_card_bill_id` filter |
| Summary cards | Inline in `Incomes/Index.tsx` (mirror expenses) | Minimal risk; a shared `TotalsCards` extraction is a separate, optional refactor (see Out of Scope) |
| Icons / colors | `Banknote` (total), `CheckCircle2` + `text-emerald-600` (recebido), `Circle` + `text-amber-600` (a receber) | Matches despesas totalizadores tokens |

---

## Implementation Steps (ordered, atomic)

### Step 1 — Backend: service method for income totals

**Files:**
- `app/Services/TransactionService.php`

**Changes:**
- Extract the shared month-scoping + aggregation logic into a private `monthlyTotalsFor(Workspace $workspace, ?string $month, TransactionType $type): array` helper.
- Keep `monthlyTotals()` (expense) delegating to it with `TransactionType::Expense`.
- Add `monthlyIncomeTotals(Workspace $workspace, ?string $month): array` delegating with `TransactionType::Income`.
- Return shape: `['total' => round(...,2), 'paid' => round(...,2), 'pending' => round(...,2)]`.

**Verification:**
- Existing `TransactionTotalsTest` still passes (no regression).

### Step 2 — Backend: `IncomeController`

**Files:**
- `app/Http/Controllers/IncomeController.php`

**Changes:**
- `index()`: add `Request $request` and `TransactionService $transactionService` params; add `'totals' => $transactionService->monthlyIncomeTotals($workspace, $request->query('month'))`.
- `datatable()`: after building the `$query`, add the month-scoping block:
  ```php
  $month = $request->input('month');
  if (is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month)) {
      [$year, $monthNum] = explode('-', $month);
      $query->whereYear('date', (int) $year)
          ->whereMonth('date', (int) $monthNum);
  }
  ```

**Verification:**
- `php artisan test --filter=Income`

### Step 3 — Frontend: `Incomes/Index.tsx`

**Files:**
- `resources/js/Pages/Incomes/Index.tsx`

**Changes:**
- Imports: add `Card, CardContent, CardHeader, CardTitle` from `@/components/ui/card`; `Banknote, CheckCircle2, Circle` from `lucide-react`; `MonthPicker` from `@/Components/MonthPicker`; `getCurrentMonth` from `@/lib/month`.
- Add `interface Totals { total: number; paid: number; pending: number }` and add `totals: Totals` to `Props`.
- Add `month` state seeded from the URL param (same pattern as expenses):
  ```tsx
  const urlParams = new URLSearchParams(usePage().url.split('?')[1] ?? '');
  const [month, setMonth] = useState(() => urlParams.get('month') ?? getCurrentMonth());
  ```
- Add `handleMonthChange(newMonth)` using `router.visit(route('incomes.index', { workspace: workspace.uuid, month: newMonth }), { preserveState: true, preserveScroll: false, replace: true })`.
- Header: render `MonthPicker` inline with the "Receitas" title (gap-4), keeping the recurrence badge and "Nova Receita" button. Preserve the `?recurrence=` badge; update its "Mostrar todas" link to include `month` so the selected month is preserved when clearing the recurrence filter.
- Insert the three summary cards between the header and the `DataTable` (grid `gap-4 sm:grid-cols-3`), with:
  - Card 1: `Banknote`, label "Total", value `formatCurrency(totals.total)`, `data-testid="total-incomes"`.
  - Card 2: `CheckCircle2` + `text-emerald-600`, label "Total recebido", value `formatCurrency(totals.paid)`, `data-testid="total-received"`.
  - Card 3: `Circle` + `text-amber-600`, label "A receber", value `formatCurrency(totals.pending)`, `data-testid="total-to-receive"`.
- `DataTable`: add `key={month}` and include `month` in the `incomes.datatable` endpoint URL.

**Verification:**
- `npm run build` (TypeScript strict compiles).

### Step 4 — PHPUnit feature + smoke tests (TDD)

**Files (new):**
- `tests/Feature/Incomes/IncomeTotalsTest.php` — mirror `TransactionTotalsTest`, asserting `totals` structure, month sums, paid/pending split, month param respect, default current month, invalid month fallback, expense exclusion, workspace scope, soft-delete exclusion (using `Transaction::factory()->income()` / `->paid()` / `->unpaid()`).
- `tests/Feature/Incomes/IncomeMonthBoundTest.php` — mirror `TransactionMonthBoundTest` against `incomes.datatable`: valid month filtering, no-param returns all, invalid format ignored, workspace scope, year boundary, composition with `status` filter.
- `tests/Feature/Incomes/IncomeSmokeTest.php` — closes the existing smoke-test gap: assert `Incomes/Index` component via `assertInertia`, with and without `?month=`.

**Verification:**
- `php artisan test --filter=Income` and full `php artisan test`.

### Step 5 — Cypress E2E (TDD)

**Files (new):**
- `cypress/e2e/incomes/month-totals.cy.js` — smoke render + `MonthPicker` default month + prev/next navigation + totals cards render with correct sums + month scoping (navigate to prior month → `0,00`).

**Verification:**
- Manual/CI Cypress run (if available in environment).

---

## Quality Gates (run before marking complete)

- `composer quality` (Pint format check + PHPMD complexity)
- `npm run quality` (ESLint complexity + Prettier check)
- `php artisan test` (full suite green)
- `npm run build` (TypeScript strict compiles)

## Out of Scope / Notes

- **Shared `TotalsCards` component extraction** — the despesas cards are inline; extracting a reusable component would deduplicate both pages but adds scope/risk. Optional follow-up, not required by this issue.
- **Story-ID collision** — update `receitas/spec.md` traceability if a formal spec is written; do not reuse `INCM-02`.
- **Recurrence × month interplay** — preserved as-is: `?recurrence=` badge and `?month=` are independent query params; the "Mostrar todas" link now keeps `month`.
- UI text stays pt-BR; code, comments, and identifiers stay English.
