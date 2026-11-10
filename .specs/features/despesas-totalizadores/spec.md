# Despesas Totalizadores — Specification

**Story ID:** DEBT-03
**Phase:** P1 — MVP Enhancement
**Parent Spec:** `.specs/features/workspace-financeiro/spec.md`
**Created:** 2026-11-08

## Problem Statement

The expenses listing page (`Transactions/Index.tsx`, title "Despesas") shows a month-bound datatable but gives the user no immediate summary. To make the month view actionable at a glance, we add three totalizer cards between the title/month-selector row and the table: the total of the month, the total already paid, and the total still pending.

## Goals

- [ ] Render 3 totalizer cards between the header (title + MonthPicker + "Nova Despesa") and the datatable
- [ ] **Total** — sum of all expenses in the selected month (money icon)
- [ ] **Total Pago** — sum of paid expenses in the month (green check icon)
- [ ] **Total Pendente (A Pagar)** — sum of pending expenses in the month (orange hollow-circle icon)
- [ ] Totals react to the selected month (same `?month=` mechanism as the datatable)

## Key Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Status semantics | `paid_at IS NOT NULL` → Pago; `paid_at IS NULL` → Pendente | Mirrors the existing `status` datatable filter and `AccountService::recalculateBalance` |
| Where totals are computed | `TransactionController@index()` returns a `totals` Inertia prop | Cards are month-scoped only and must NOT react to datatable column filters (description/category/status). The Planning page already uses this exact pattern |
| Month scoping | Read `?month=` in `index()` (new `Request` param), reuse `YYYY-MM` regex from `datatable()` | Same month semantics as the datatable; refresh-safe via existing URL sync |
| No month param | Default to the current month (`now()->format('Y-m')`) | Matches the frontend month selector default (`getCurrentMonth()`) so the cards always reflect the month being viewed; invalid month also falls back to current month |
| Business logic location | New `TransactionService::monthlyTotals()` method | Service layer owns aggregation; controller stays thin |
| Icons | `Banknote` (Total), `CheckCircle2` (Pago), `Circle` (Pendente) from `lucide-react` | Existing project icon library |
| Colors | Pago `text-emerald-600`, Pendente `text-amber-600`, Total neutral | Matches existing status column and Home/Planning card tokens |

### Known limitation (documented, not blocking)

Card expenses (`credit_card_id != null`) always have `paid_at = null`; their payment state lives on the credit-card bill (`bill.status`). Under the `paid_at` semantics, card expenses count as **Pendente** until the bill itself is reconciled — this is consistent with the existing `status` filter and status column behavior. Reconciling bill-level payment state into these cards is deferred (see `.specs/project/STATE.md`).

## Out of Scope

| Feature | Reason |
|---------|--------|
| Bill-status-aware totals for card expenses | Deferred; keeps semantics consistent with the datatable status filter |
| Totals reacting to datatable column filters | Cards are month-bound summaries, not filter summaries |
| Month picker / totals on Incomes page | Separate story (INCM-02) |

---

## User Stories

### DEBT-03.1 — Render Totalizer Cards

**WHEN** a user opens the expenses page, **THEN** the system SHALL render three totalizer cards between the title/month-selector row and the table.

**Acceptance Criteria:**
- Cards labeled "Total", "Total Pago", "Total Pendente"
- Total card shows a money icon; Total Pago a green check icon; Total Pendente an orange hollow-circle icon
- Layout is a responsive 3-column grid (`grid gap-4 sm:grid-cols-3`)
- Values use `formatCurrency` (pt-BR / BRL)

### DEBT-03.2 — Total Card

**WHEN** the month has expenses, **THEN** the "Total" card SHALL show the sum of **all** expenses in the selected month.

**Acceptance Criteria:**
- Sum includes both paid and pending expenses
- Sum excludes income transactions
- Sum excludes soft-deleted transactions
- Sum respects workspace scope and the selected month

### DEBT-03.3 — Total Pago Card

**WHEN** the month has paid expenses, **THEN** the "Total Pago" card SHALL show the sum of expenses with `paid_at` set.

**Acceptance Criteria:**
- Only expenses where `paid_at IS NOT NULL` are summed
- Rendered in green (`text-emerald-600`) with a check icon

### DEBT-03.4 — Total Pendente Card

**WHEN** the month has pending expenses, **THEN** the "Total Pendente (A Pagar)" card SHALL show the sum of expenses with `paid_at` unset.

**Acceptance Criteria:**
- Only expenses where `paid_at IS NULL` are summed
- Rendered in orange (`text-amber-600`) with a hollow-circle icon
- `total === paid + pending` for every month

---

## Technical Design

### Backend

#### Service method — `TransactionService::monthlyTotals()`

```php
public function monthlyTotals(Workspace $workspace, ?string $month): array
{
    $query = $workspace->transactions()
        ->where('type', TransactionType::Expense);

    if ($month !== null && preg_match('/^\d{4}-\d{2}$/', $month)) {
        [$year, $monthNum] = explode('-', $month);
        $query->whereYear('date', (int) $year)
            ->whereMonth('date', (int) $monthNum);
    }

    return [
        'total'   => round((float) (clone $query)->sum('value'), 2),
        'paid'    => round((float) (clone $query)->whereNotNull('paid_at')->sum('value'), 2),
        'pending' => round((float) (clone $query)->whereNull('paid_at')->sum('value'), 2),
    ];
}
```

Notes:
- `$workspace->transactions()` already excludes soft-deleted rows (SoftDeletes global scope), matching the datatable.
- `sum('value')` returns a string (decimal cast); cast to `float` and `round(…, 2)`.
- Uses `clone` to derive paid/pending from the same base scope.

#### Controller — `TransactionController@index()`

Add `Request $request` to the signature, resolve the service, and pass `totals`:

```php
public function index(Workspace $workspace, Request $request, TransactionService $transactionService): Response
{
    $this->authorize('viewAny', [Transaction::class, $workspace]);

    // ...existing state/props...

    return inertia('Transactions/Index', [
        // ...existing props...
        'totals' => $transactionService->monthlyTotals($workspace, $request->query('month')),
    ]);
}
```

### Frontend

#### Page — `resources/js/Pages/Transactions/Index.tsx`

1. Add `totals` to the `Props` interface and destructure it:
   ```ts
   interface Totals { total: number; paid: number; pending: number }
   ```
2. Import `CardHeader`, `CardTitle` (add to existing card import) and `Banknote`, `CheckCircle2`, `Circle` from `lucide-react`.
3. Render the cards grid between the header `<div>` and the `<Card>` wrapping the `DataTable`.
4. Add `data-testid` attributes for precise E2E assertions: `total-expenses`, `total-paid`, `total-pending`.

Card structure (three instances):

```tsx
<div className="grid gap-4 sm:grid-cols-3">
    <Card>
        <CardHeader className="pb-2">
            <CardTitle className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                <Banknote className="h-4 w-4" />
                Total
            </CardTitle>
        </CardHeader>
        <CardContent>
            <p className="text-2xl font-semibold" data-testid="total-expenses">
                {formatCurrency(totals.total)}
            </p>
        </CardContent>
    </Card>

    <Card>
        <CardHeader className="pb-2">
            <CardTitle className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                Total Pago
            </CardTitle>
        </CardHeader>
        <CardContent>
            <p className="text-2xl font-semibold text-emerald-600" data-testid="total-paid">
                {formatCurrency(totals.paid)}
            </p>
        </CardContent>
    </Card>

    <Card>
        <CardHeader className="pb-2">
            <CardTitle className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                <Circle className="h-4 w-4 text-amber-600" />
                Total Pendente
            </CardTitle>
        </CardHeader>
        <CardContent>
            <p className="text-2xl font-semibold text-amber-600" data-testid="total-pending">
                {formatCurrency(totals.pending)}
            </p>
        </CardContent>
    </Card>
</div>
```

Month reactivity requires no extra wiring: `handleMonthChange` already re-fetches `transactions.index`, which re-computes `totals`.

---

## Test Strategy

### PHPUnit Feature Tests

**File:** `tests/Feature/Transactions/TransactionTotalsTest.php`

| Test | What it verifies |
|------|-----------------|
| `test_index_returns_totals_structure` | `totals` prop present with `total`, `paid`, `pending` keys |
| `test_totals_sum_all_expenses_in_month` | Total = sum of all expenses in the month |
| `test_totals_split_paid_and_pending` | Paid vs pending split; `total === paid + pending` |
| `test_totals_respect_month_param` | `?month=` scopes totals to the selected month |
| `test_totals_without_month_param_defaults_to_current_month` | No month → totals default to current month |
| `test_totals_with_invalid_month_param_defaults_to_current_month` | Invalid month → totals default to current month |
| `test_totals_exclude_income` | Income transactions are not counted |
| `test_totals_respect_workspace_scope` | Other-workspace expenses excluded |
| `test_totals_exclude_soft_deleted` | Soft-deleted expenses excluded |

### Cypress E2E Tests

**File:** `cypress/e2e/transactions/totals.cy.js`

| Test | What it verifies |
|------|-----------------|
| `renders totals cards` | Smoke — 3 card titles visible without console errors |
| `shows correct totals` | Seeds 3 expenses (100, 50, 25), pays the 50 one, asserts `175,00` / `50,00` / `125,00` |

---

## Definition of Done

1. **Technical Standards:** Service-layer aggregation, Inertia-pure props, TypeScript strict, shadcn/ui only
2. **Data Isolation:** Workspace scope + soft-delete exclusion enforced
3. **UI in pt-BR:** Card labels in Brazilian Portuguese
4. **Code Quality:** `composer quality` + `npm run quality` pass
5. **Test Coverage:** All PHPUnit feature tests pass; Cypress E2E passes

---

## Traceability

| Requirement | Implementation | Test |
|-------------|---------------|------|
| DEBT-03.1 | Cards grid in `Transactions/Index.tsx` | `renders totals cards` |
| DEBT-03.2 | `monthlyTotals()` `total` | `test_totals_sum_all_expenses_in_month` |
| DEBT-03.3 | `monthlyTotals()` `paid` | `test_totals_split_paid_and_pending` |
| DEBT-03.4 | `monthlyTotals()` `pending` | `test_totals_split_paid_and_pending` |
