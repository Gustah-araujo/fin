# Planejamento Futuro Tasks

**Spec**: `.specs/features/planejamento-futuro/spec.md`
**Design**: `.specs/features/planejamento-futuro/design.md`
**Status**: Done

---

## Execution Plan

### Phase 1: Backend Service (Sequential)

Core projection logic — foundation for everything.

```
T1 → T2
```

### Phase 2: Controller + Resource (Sequential)

HTTP layer on top of service.

```
T2 → T3 → T4
```

### Phase 3: Routes + Sidebar (Sequential)

Wiring.

```
T4 → T5
```

### Phase 4: Frontend Pages (Parallel OK)

UI components — independent after types exist.

```
T5 complete, then:
  ├── T6 [P]
  └── T7 [P]
```

### Phase 5: E2E + Quality Gate (Sequential)

Integration verification.

```
T6, T7 complete, then:
  T8 → T9
```

---

## Task Breakdown

### T1: PlanningService with feature tests

**What:** Create `PlanningService` with `getProjection()` and `getMonthDetail()` methods + comprehensive feature tests
**Where:** `app/Services/PlanningService.php`, `tests/Feature/PlanningServiceTest.php`
**Depends on:** None
**Reuses:** `RecurrenceService` (project recurrences in memory pattern), `Transaction` model query scopes, `Recurrence` model (D-38), `RecurrenceStatus` enum (D-49)
**Requirement:** PLAN-01, PLAN-03, PLAN-04

**Tools:**
- MCP: NONE
- Skill: NONE

**Done when:**
- `getProjection(Workspace, Carbon $start, Carbon $end)` returns array of months with expenses/incomes/balance
- `getMonthDetail(Workspace, Carbon $month)` returns grouped transactions (expenses/incomes) sorted by date
- Recurrences projected in memory (not persisted) — mirrors `RecurrenceService` pattern
- Paused recurrences excluded; `until_date` respected (edge cases from spec)
- Collection `groupBy` used (no SQL raw) — per D-61
- Tests cover: avulsas only, installments, recurrences, paused recurrences, until_date past, empty month, period > 24 months edge
- Gate check passes: `phpunit --filter=PlanningServiceTest`
- Test count: ≥12 tests pass

**Verify:**
```bash
docker compose exec app php artisan test --filter=PlanningServiceTest
```
Expected: ≥12 tests, all passing

---

### T2: PlanningControllerTest (TDD red)

**What:** Write controller feature tests FIRST (TDD red phase) — index returns props, monthDetail returns JSON, authorization enforced, validation works
**Where:** `tests/Feature/PlanningControllerTest.php`
**Depends on:** T1
**Reuses:** `IncomeController` test pattern (actingAs, authorize, inertia assertions)
**Requirement:** PLAN-01, PLAN-02, PLAN-03, PLAN-05

**Tools:**
- MCP: NONE
- Skill: NONE

**Done when:**
- Tests for `index` returning 200 + correct props structure
- Tests for `monthDetail` returning JSON with expenses/incomes arrays
- Tests for authorization rejection (non-member gets 403)
- Tests for validation: date_end < date_start → 422, period > 24 months → 422
- Tests for preset filtering (query params parsed correctly)
- Gate check shows tests FAIL (red phase — expected, controller doesn't exist yet)
- Test count: ≥8 tests exist (all red)

**Verify:**
```bash
docker compose exec app php artisan test --filter=PlanningControllerTest
```
Expected: ≥8 tests, all FAIL (controller not yet created)

---

### T3: PlanningController + PlanningResource

**What:** Implement controller and resource to make T2 tests green
**Where:** `app/Http/Controllers/PlanningController.php`, `app/Http/Resources/PlanningResource.php`
**Depends on:** T2
**Reuses:** `IncomeController` structure (authorize, inertia, props), `TransactionResource` pattern, `JsonResource`
**Requirement:** PLAN-01, PLAN-02, PLAN-03, PLAN-04, PLAN-05

**Tools:**
- MCP: NONE
- Skill: NONE

**Done when:**
- `index(Workspace, Request)` validates date range, calls PlanningService, returns Inertia with projection + totals + filter
- `monthDetail(Workspace, Request)` returns JSON response (AJAX endpoint per D-59)
- `PlanningResource` formats: month, month_label, expenses, incomes, balance
- Controller uses Policy authorization (`viewAny` on Transaction with workspace)
- Request validation: date_start required date, date_end required date after date_start, max 24 months diff
- Filter param `type` accepts: all|expenses|incomes
- Gate check passes: `phpunit --filter=PlanningControllerTest`
- Test count: ≥8 tests pass (T2 tests now green)

**Verify:**
```bash
docker compose exec app php artisan test --filter=Planning
```
Expected: ≥20 tests passing (T1 + T2 combined)

---

### T4: Routes + Sidebar link

**What:** Register planning routes and add sidebar navigation link
**Where:** `routes/web.php`, `resources/js/Components/AppSidebar.tsx`
**Depends on:** T3
**Reuses:** Existing route patterns (`/w/{workspace}/...`), sidebar item structure
**Requirement:** PLAN-01, PLAN-02

**Tools:**
- MCP: NONE
- Skill: NONE

**Done when:**
- `planning.index` route: `GET /w/{workspace}/planning` → `PlanningController@index`
- `planning.month-detail` route: `GET /w/{workspace}/planning/month-detail` → `PlanningController@monthDetail`
- Both routes inside `auth` + `verified` + `prefix('w/{workspace}')` middleware group
- Sidebar has "Planejamento" link with appropriate icon (e.g., `Calendar` or `TrendingUp`)
- Link uses `route('planning.index', { workspace })` pattern
- `route:list` shows both planning routes
- Gate check passes: `php artisan route:list | grep planning`

**Verify:**
```bash
docker exec app php artisan route:list --name=planning
```
Expected: 2 routes listed with correct controller bindings

---

### T5: TypeScript types

**What:** Define planning TypeScript interfaces
**Where:** `resources/js/types/planning.ts`
**Depends on:** T4
**Reuses:** Existing type patterns (see `resources/js/types/`)
**Requirement:** PLAN-01, PLAN-03, PLAN-04

**Tools:**
- MCP: NONE
- Skill: NONE

**Done when:**
- `MonthProjection` interface: month, month_label, expenses, incomes, balance
- `Totals` interface: expenses, incomes, balance
- `MonthDetail` interface: month, expenses: TransactionDetail[], incomes: TransactionDetail[]
- `TransactionDetail` interface: id, description, value, category, date, type
- `PlanningFilter` type: 'all' | 'expenses' | 'incomes'
- All types exported
- No TypeScript errors
- Gate check passes: `npm run build` (no new TS errors)

**Verify:**
```bash
npx tsc --noEmit --skipLibCheck 2>&1 | grep -i planning || echo "OK"
```
Expected: "OK" (no planning-related errors)

---

### T6: Planning/Index.tsx page [P]

**What:** Main planning page with period selector, totals cards, and projection table
**Where:** `resources/js/Pages/Planning/Index.tsx`
**Depends on:** T5
**Reuses:** `Incomes/Index.tsx` structure, shadcn Card/Table/Select, `formatCurrency`, `useWorkspace`, AuthenticatedLayout
**Requirement:** PLAN-01, PLAN-02, PLAN-04, PLAN-05

**Tools:**
- MCP: NONE
- Skill: NONE

**Done when:**
- Page receives typed props: projection, date_start, date_end, totals, filter
- Period selector with presets: "Próximo mês", "Próximos 3 meses", "Próximos 6 meses", "Próximos 9 meses", "Próximos 12 meses"
- "Personalizado" option reveals two date pickers (start/end)
- Changing preset triggers Inertia reload with query params (preserves URL state)
- 3 cards at top: "Gastos Comprometidos", "Rendas Programadas", "Saldo Projetado"
- Negative balance displayed in red (destructive color)
- Table with columns: Mês, Gastos, Rendas, Saldo
- Row click opens MonthDetailModal
- Filter toggle: "Todos" | "Apenas Gastos" | "Apenas Rendas" — hides column + recalculates
- Empty state message when no transactions
- Gate check passes: `npm run quality` (ESLint + Prettier clean on new file)

**Verify:**
```bash
cd /home/gustavo/Projects/fin && npm run lint -- --max-warnings 0 resources/js/Pages/Planning/Index.tsx
```
Expected: No errors

---

### T7: MonthDetailModal component [P]

**What:** Modal showing detailed transactions for a specific month (AJAX-loaded)
**Where:** `resources/js/Components/Planning/MonthDetailModal.tsx`
**Depends on:** T5
**Reuses:** shadcn Dialog, axios (per D-55), `formatCurrency`, `useWorkspace`
**Requirement:** PLAN-03

**Tools:**
- MCP: NONE
- Skill: NONE

**Done when:**
- Props: month (string 'Y-m'), isOpen (boolean), onClose (callback)
- On open, fetches `GET /w/{workspace}/planning/month-detail?month=...` via axios
- Shows loading state while fetching
- Renders two sections: "Gastos" and "Rendas" with subtotals
- Each transaction row: description, value (formatted), category, date (formatted)
- Transactions sorted by date ascending
- Close on backdrop click or "Fechar" button
- Error handling: toast on fetch failure
- Gate check passes: `npm run quality` (ESLint + Prettier clean on new file)

**Verify:**
```bash
cd /home/gustavo/Projects/fin && npm run lint -- --max-warnings 0 resources/js/Components/Planning/MonthDetailModal.tsx
```
Expected: No errors

---

### T8: Cypress E2E test

**What:** End-to-end test covering critical planning user journey
**Where:** `cypress/e2e/planning.cy.ts`
**Depends on:** T6, T7
**Reuses:** Existing Cypress patterns, `cy.visit`, `cy.intercept` for AJAX
**Requirement:** PLAN-01, PLAN-02, PLAN-03, PLAN-04

**Tools:**
- MCP: NONE
- Skill: NONE

**Done when:**
- Test: Access `/planning`, see cards + table rendered
- Test: Select "Próximos 3 months" preset, see table update
- Test: Click month row, modal opens with transaction list
- Test: Select "Personalizado" with custom dates, see table adjust
- Test: Filter "Apenas Gastos" hides Rendas column
- Test: Empty workspace shows "Nenhuma transação" message
- Gate check passes: `cypress run --spec cypress/e2e/planning.cy.ts`
- All scenarios pass

**Verify:**
```bash
npx cypress run --spec cypress/e2e/planning.cy.ts
```
Expected: All scenarios pass

---

### T9: Quality gate + final verification

**What:** Run all quality gates and verify traceability
**Where:** N/A (verification only)
**Depends on:** T8
**Reuses:** Quality commands from D-51
**Requirement:** PLAN-01, PLAN-02, PLAN-03, PLAN-04, PLAN-05

**Tools:**
- MCP: NONE
- Skill: NONE

**Done when:**
- `composer quality` passes (Pint format check + PHPMD)
- `npm run quality` passes (ESLint complexity + Prettier)
- `phpunit --filter=Planning` passes (≥20 tests)
- `npm run build` passes
- `cypress run --spec cypress/e2e/planning.cy.ts` passes
- spec.md traceability updated: all PLAN-01 to PLAN-05 mapped to tasks
- ROADMAP.md updated: PLAN-01 status → 🟢

**Verify:**
```bash
cd /home/gustavo/Projects/fin && composer quality && npm run quality
docker compose exec app php artisan test --filter=Planning
npm run build
npx cypress run --spec cypress/e2e/planning.cy.ts
```
Expected: All gates green

---

## Parallel Execution Map

```
Phase 1 (Sequential):
  T1 ──→ T2

Phase 2 (Sequential):
  T2 ──→ T3 ──→ T4

Phase 3 (Sequential):
  T4 ──→ T5

Phase 4 (Parallel):
  T5 complete, then:
    ├── T6 [P] (Planning/Index.tsx)
    └── T7 [P] (MonthDetailModal.tsx)

Phase 5 (Sequential):
  T6, T7 complete, then:
    T8 ──→ T9
```

---

## Task Granularity Check

| Task | Scope | Status |
|------|-------|--------|
| T1: PlanningService + tests | 1 service + 1 test file | ⚠️ OK — cohesive (service + its tests) |
| T2: Controller tests (red) | 1 test file | ✅ Granular |
| T3: Controller + Resource | 2 related files | ⚠️ OK — cohesive (HTTP layer) |
| T4: Routes + Sidebar | 2 small changes | ⚠️ OK — wiring task |
| T5: TypeScript types | 1 file | ✅ Granular |
| T6: Index page | 1 component | ✅ Granular |
| T7: Modal component | 1 component | ✅ Granular |
| T8: Cypress E2E | 1 test file | ✅ Granular |
| T9: Quality gate | Verification only | ✅ Granular |

---

## Diagram-Definition Cross-Check

| Task | Depends On (task body) | Diagram Shows | Status |
|------|------------------------|---------------|--------|
| T1 | None | No incoming arrows | ✅ Match |
| T2 | T1 | T1 ──→ T2 | ✅ Match |
| T3 | T2 | T2 ──→ T3 | ✅ Match |
| T4 | T3 | T3 ──→ T4 | ✅ Match |
| T5 | T4 | T4 ──→ T5 | ✅ Match |
| T6 [P] | T5 | T5 ──→ T6 | ✅ Match |
| T7 [P] | T5 | T5 ──→ T7 | ✅ Match |
| T8 | T6, T7 | T6,T7 ──→ T8 | ✅ Match |
| T9 | T8 | T8 ──→ T9 | ✅ Match |

**Parallel check:** T6 and T7 both depend on T5 only — no mutual dependencies → `[P]` valid ✅

---

## Test Co-location Validation

| Task | Code Layer | Project Convention | Task Says | Status |
|------|------------|-------------------|-----------|--------|
| T1: PlanningService | Service | Feature test (D-10) | Feature tests included | ✅ OK |
| T2: ControllerTest | Test | Feature test (D-10) | Tests written (TDD red) | ✅ OK |
| T3: Controller + Resource | Controller/Resource | Feature test (D-10) | Tests from T2 green | ✅ OK |
| T4: Routes + Sidebar | Routes/Component | No test required | No tests | ✅ OK |
| T5: Types | Type definitions | No test required | No tests | ✅ OK |
| T6: Index page | React page | Cypress E2E (D-21) | E2E in T8 (deferred) | ⚠️ DEFERRED — acceptable: E2E covers full journey including page |
| T7: Modal component | React component | Cypress E2E (D-21) | E2E in T8 (deferred) | ⚠️ DEFERRED — acceptable: E2E covers modal interaction |
| T8: Cypress | E2E test | Cypress E2E (D-21) | E2E tests included | ✅ OK |
| T9: Quality gate | Verification | N/A | Verification only | ✅ OK |

**Note on T6/T7 test deferral:** Per project convention (D-10, D-21), feature-level PHP tests and Cypress E2E are the standard. React components are verified via Cypress (T8), which covers both page and modal integration. This matches existing pattern (e.g., Incomes/Index.tsx has no isolated React test — verified via Cypress).

---

## Requirement Traceability

| Requirement | Task(s) | Status |
|-------------|---------|--------|
| PLAN-01: Ver Projeção Mês-a-Mês | T1, T2, T3, T4, T6 | Pending |
| PLAN-02: Selecionar Período | T2, T3, T4, T6 | Pending |
| PLAN-03: Ver Detalhamento de Mês | T1, T2, T3, T7 | Pending |
| PLAN-04: Ver Totais Agregados | T1, T3, T6 | Pending |
| PLAN-05: Filtrar por Tipo | T2, T3, T6 | Pending |
| PLAN-06: Orçamento por Categoria | DEFERRED (P3) | Pending |

**Coverage:** 5 mapped to tasks, 1 deferred, 0 unmapped ✅
