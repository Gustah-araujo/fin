# Despesas Totalizadores — Implementation Tasks

**Spec:** `.specs/features/despesas-totalizadores/spec.md`
**Story ID:** DEBT-03

TDD-first: tests are written before the implementation they cover.

---

## Task 1: Backend Feature Tests (RED)

**Goal:** Specify the `totals` behavior with failing tests.

**File:** `tests/Feature/Transactions/TransactionTotalsTest.php` (new)

Setup pattern (matches `TransactionFilteringTest`): admin user + workspace + account + expense category; `Transaction::factory()->paid()/->unpaid()` with explicit `value` and `date`.

**Tests:**
1. `test_index_returns_totals_structure` — `assertInertia` has `totals.total` / `totals.paid` / `totals.pending`
2. `test_totals_sum_all_expenses_in_month` — 100 + 50 + 25 → `total = 175`
3. `test_totals_split_paid_and_pending` — paid 100, unpaid 50, unpaid 25 → `total 175, paid 100, pending 75`
4. `test_totals_respect_month_param` — Aug 100 / Sep 50, `?month=2026-09` → `total = 50`
5. `test_totals_without_month_param_include_all` — no `month` → sum across all months
6. `test_totals_exclude_income` — income 1000 + expense 100 → `total = 100`
7. `test_totals_respect_workspace_scope` — other-workspace expense excluded
8. `test_totals_exclude_soft_deleted` — soft-deleted expense excluded

**Assertion style:**
```php
$response->assertInertia(fn (Assert $page) => $page
    ->where('totals.total', 175.0)
    ->where('totals.paid', 100.0)
    ->where('totals.pending', 75.0)
);
```

**Gate:** `php artisan test --filter=TransactionTotalsTest` → fails (red) before Task 2.

---

## Task 2: Backend — Service + Controller (GREEN)

**Goal:** Implement totals aggregation.

1. **`app/Services/TransactionService.php`** — add `monthlyTotals(Workspace $workspace, ?string $month): array` (see spec). Reuses `$workspace->transactions()`, `TransactionType::Expense`, the `YYYY-MM` regex, `whereNotNull('paid_at')` / `whereNull('paid_at')`.
2. **`app/Http/Controllers/TransactionController.php`** — add `Request $request` + `TransactionService $transactionService` to `index()`, add `'totals' => $transactionService->monthlyTotals($workspace, $request->query('month'))` to the inertia payload.

**Gate:** `php artisan test --filter=TransactionTotalsTest` → green. Existing `TransactionSmokeTest` still passes.

---

## Task 3: Frontend — Totalizer Cards

**Goal:** Render the three cards in the expenses page.

**File:** `resources/js/Pages/Transactions/Index.tsx`

1. Add `interface Totals { total: number; paid: number; pending: number }` and `totals: Totals` to `Props`; destructure `totals`.
2. Extend imports: add `CardHeader`, `CardTitle` to the `@/components/ui/card` import; add `Banknote`, `CheckCircle2`, `Circle` from `lucide-react`.
3. Insert the cards grid between the header `<div>` and the datatable `<Card>` (see spec markup), with `data-testid="total-expenses" | "total-paid" | "total-pending"`.

**Gate:** `npm run quality` passes; page renders with cards via `npm run dev`.

---

## Task 4: Cypress E2E Tests

**Goal:** Validate the totals UI end-to-end.

**File:** `cypress/e2e/transactions/totals.cy.js` (new)

**Setup (`before`):** `cy.loginViaSession('totals-session')`, create workspace + account (mirror `crud.cy.js` setup).

**Tests:**
1. `renders totals cards` — smoke: `cy.contains('Total')`, `cy.contains('Total Pago')`, `cy.contains('Total Pendente')` visible.
2. `shows correct totals` — create 3 expenses via the create form (values 100, 50, 25), pay the 50 one via the row "Pagar" button, then assert:
   - `cy.get('[data-testid="total-expenses"]').should('contain', '175,00')`
   - `cy.get('[data-testid="total-paid"]').should('contain', '50,00')`
   - `cy.get('[data-testid="total-pending"]').should('contain', '125,00')`

**Gate:** `npx cypress run --spec cypress/e2e/transactions/totals.cy.js` passes.

---

## Task 5: Quality Gate

```bash
composer format && composer quality   # Pint + PHPMD
npm run format && npm run quality     # ESLint + Prettier
php artisan test                      # full suite
```

**Gate:** zero errors/warnings; full suite green.

---

## Dependency Order

```
Task 1 (feature tests, red)
  → Task 2 (service + controller, green)
      → Task 3 (frontend cards)
          → Task 4 (Cypress E2E)
              → Task 5 (quality gate)
```
