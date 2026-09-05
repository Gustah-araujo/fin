# Recurring Expenses — Tasks

**Spec**: `.specs/features/recurring-expenses/spec.md`
**Design**: `.specs/features/recurring-expenses/design.md`
**Status**: Draft

---

## Execution Plan

### Phase 1: Service Layer (Sequential)

Type flows from data, not hardcoded. Foundation for everything.

```
T1 → T2
```

### Phase 2: Expense Creation Flow (Sequential within, parallel across test/impl pairs)

Request validation → Controller branch.

```
T3 → T4 → T5 → T6
```

### Phase 3: Recurrence Management (Sequential within)

Category guard → Category filter → Resource + Factory.

```
T7 → T8 → T9 → T10 → T11
```

### Phase 4: Frontend (Parallel OK)

Expense form panel + Recurrence list enhancements independent.

```
      ┌→ T12 ─┐
T11 ──┼───────┼──→ T15
      └→ T13 ─┘
           ↑
     T14 ──┘ (Planning tests, can run parallel with T12/T13)
```

### Phase 5: Integration & Quality Gate

E2E + full verification.

```
T15 → T16
```

---

## Task Breakdown

### T1: RecurrenceService reads type from data

**What**: Add 4 tests verifying RecurrenceService accepts `type` from `$data` and reads `$recurrence->type` for generated instances
**Where**: `tests/Feature/Recurrences/RecurrenceServiceTest.php`
**Depends on**: None
**Reuses**: Existing `RecurrenceServiceTest::baseData()` pattern, `RecurrenceFactory`
**Requirement**: REEX-01

**Tools**:
- MCP: NONE
- Skill: NONE

**Done when**:
- [ ] Test: `create()` with `$data['type'] = 'expense'` creates expense recurrence
- [ ] Test: `createWithFirstInstance()` with expense type creates recurrence + expense transaction
- [ ] Test: `generateNextInstance()` generates expense transaction from expense recurrence
- [ ] Test: `buildGeneratedTransaction()` reads type from `$recurrence->type` (not hardcoded Income)
- [ ] Tests fail: `php artisan test --filter=RecurrenceServiceTest` shows RED (hardcoded Income fails expense assertions)

**Tests**: feature
**Gate**: quick

**Verify**:
```bash
php artisan test --filter=RecurrenceServiceTest
```
Expected: 4 new tests fail (type assertions on expense data)

---

### T2: RecurrenceService remove hardcoded type

**What**: Replace 3 hardcoded `TransactionType::Income` with `$data['type'] ?? TransactionType::Income` in create/createWithFirstInstance, and `$recurrence->type->value` in buildGeneratedTransaction
**Where**: `app/Services/RecurrenceService.php` (lines 57, 99, 117, 486)
**Depends on**: T1
**Reuses**: Existing `RecurrenceService` methods
**Requirement**: REEX-01, D-65

**Tools**:
- MCP: NONE
- Skill: NONE

**Done when**:
- [ ] Line 57: `'type' => $data['type'] ?? TransactionType::Income`
- [ ] Line 99: `'type' => $data['type'] ?? TransactionType::Income`
- [ ] Line 117: `'type' => $data['type'] ?? TransactionType::Income`
- [ ] Line 486: `'type' => $recurrence->type->value`
- [ ] All T1 tests pass
- [ ] Existing income recurrence tests still pass (no regression)
- [ ] Gate check passes: `composer quality`
- [ ] Test count: 4 new + existing pass (no silent deletions)

**Tests**: feature
**Gate**: quick

**Verify**:
```bash
php artisan test --filter=RecurrenceServiceTest
composer quality
```
Expected: All RecurrenceServiceTest tests pass, quality gate green

**Commit**: `feat(recurrence): read type from data instead of hardcoding income`

---

### T3: StoreTransactionRequest recurrence validation rules

**What**: Add 3 tests verifying recurrence validation rules for expense form (is_recurring, frequency, frequency_day, until_date)
**Where**: `tests/Feature/Transactions/TransactionValidationTest.php` (or create `tests/Feature/Transactions/TransactionRecurrenceValidationTest.php`)
**Depends on**: None (can run parallel with T1)
**Reuses**: Existing `StoreIncomeRequest` pattern for `validateFrequencyDay`
**Requirement**: REEX-01

**Tools**:
- MCP: NONE
- Skill: NONE

**Done when**:
- [ ] Test: rejects is_recurring without frequency
- [ ] Test: rejects invalid frequency_day for weekly (7) and monthly (32)
- [ ] Test: accepts valid recurrence payload (is_recurring=true, frequency=monthly, frequency_day=15)
- [ ] Tests fail: RED (rules not yet implemented)

**Tests**: feature
**Gate**: quick

**Verify**:
```bash
php artisan test --filter=TransactionRecurrenceValidationTest
```
Expected: 3 new tests fail

---

### T4: StoreTransactionRequest add recurrence rules

**What**: Add `is_recurring`, `frequency`, `frequency_day`, `until_date` rules + `validateFrequencyDay()` method + error messages
**Where**: `app/Http/Requests/StoreTransactionRequest.php`
**Depends on**: T3
**Reuses**: `StoreIncomeRequest::validateFrequencyDay` pattern
**Requirement**: REEX-01, D-64

**Tools**:
- MCP: NONE
- Skill: NONE

**Done when**:
- [ ] Rules added: `is_recurring`, `frequency` (required_if), `frequency_day` (required_if), `until_date` (nullable, after_or_equal:date)
- [ ] `validateFrequencyDay()` method added (mirror income pattern)
- [ ] Error messages added for `required_if` and `after_or_equal`
- [ ] All T3 tests pass
- [ ] Existing transaction validation tests still pass
- [ ] Gate check passes: `composer quality`

**Tests**: feature
**Gate**: quick

**Verify**:
```bash
php artisan test --filter=TransactionRecurrenceValidationTest
php artisan test --filter=TransactionCreationTest
composer quality
```
Expected: New + existing tests pass

**Commit**: `feat(transactions): add recurrence validation rules to StoreTransactionRequest`

---

### T5: TransactionController recurrence branch tests

**What**: Add 3 tests verifying controller creates recurrence+transaction, future-dated recurrence only, and avulsed unchanged
**Where**: `tests/Feature/Transactions/TransactionCreationTest.php` (add methods)
**Depends on**: None (can run parallel with T3)
**Reuses**: Existing `TransactionCreationTest` setup
**Requirement**: REEX-01

**Tools**:
- MCP: NONE
- Skill: NONE

**Done when**:
- [ ] Test: POST is_recurring=true + start_date=today → creates Recurrence + Transaction
- [ ] Test: POST is_recurring=true + start_date>today → creates only Recurrence
- [ ] Test: POST is_recurring=false → creates single transaction (unchanged behavior)
- [ ] Tests fail: RED (controller doesn't have recurrence branch yet)

**Tests**: feature
**Gate**: quick

**Verify**:
```bash
php artisan test --filter="test_user_can_create_recurring_expense|test_user_can_create_future_dated|test_user_can_create_avulsa"
```
Expected: 3 new tests fail

---

### T6: TransactionController recurrence branch implementation

**What**: Add recurrence branch to `store()`: when `is_recurring`, set type=Expense, delegate to RecurrenceService, handle start_date logic
**Where**: `app/Http/Controllers/TransactionController.php`
**Depends on**: T5, T2, T4
**Reuses**: `RecurrenceService::create` / `createWithFirstInstance` from T2
**Requirement**: REEX-01, D-64

**Tools**:
- MCP: NONE
- Skill: NONE

**Done when**:
- [ ] `store()` injects `RecurrenceService`
- [ ] When `is_recurring`: sets `type=Expense`, `start_date=date`, delegates to `createWithFirstInstance` (today) or `create` (future)
- [ ] When not recurring: unchanged behavior
- [ ] All T5 tests pass
- [ ] Existing transaction creation tests still pass
- [ ] Gate check passes: `composer quality`

**Tests**: feature
**Gate**: quick

**Verify**:
```bash
php artisan test --filter=TransactionCreationTest
composer quality
```
Expected: All tests pass

**Commit**: `feat(transactions): add recurrence branch to TransactionController::store`

---

### T7: UpdateRecurrenceRequest category guard by type tests

**What**: Add 2 tests verifying category guard branches on recurrence type (reject income-only on expense, reject expense-only on income)
**Where**: `tests/Feature/Recurrences/RecurrenceUpdateValidationTest.php` (or add to existing)
**Depends on**: None
**Reuses**: Existing `UpdateRecurrenceRequest` test patterns
**Requirement**: REEX-04

**Tools**:
- MCP: NONE
- Skill: NONE

**Done when**:
- [ ] Test: expense recurrence rejects income-only category → "Esta categoria não aceita despesas."
- [ ] Test: income recurrence rejects expense-only category → "Esta categoria não aceita receitas."
- [ ] Tests fail: RED (guard currently only checks Expense→Income direction)

**Tests**: feature
**Gate**: quick

**Verify**:
```bash
php artisan test --filter=RecurrenceUpdateValidationTest
```
Expected: 2 new tests fail

---

### T8: UpdateRecurrenceRequest category guard by type implementation

**What**: Update `validateCategory()` to branch on `$recurrence->type`: Expense rejects Income, Income rejects Expense
**Where**: `app/Http/Requests/UpdateRecurrenceRequest.php` (lines 93-95)
**Depends on**: T7
**Reuses**: Existing `validateCategory` structure
**Requirement**: REEX-04

**Tools**:
- MCP: NONE
- Skill: NONE

**Done when**:
- [ ] Guard reads `$recurrence = $this->route('recurrence')`
- [ ] Expense recurrence + Income category → "Esta categoria não aceita despesas."
- [ ] Income recurrence + Expense category → "Esta categoria não aceita receitas."
- [ ] All T7 tests pass
- [ ] Existing recurrence update tests still pass
- [ ] Gate check passes: `composer quality`

**Tests**: feature
**Gate**: quick

**Verify**:
```bash
php artisan test --filter=RecurrenceUpdateValidationTest
composer quality
```
Expected: All tests pass

**Commit**: `feat(recurrences): category guard branches on recurrence type`

---

### T9: RecurrenceController category filter by type tests

**What**: Add 2 tests verifying `edit()` filters categories by recurrence type (Expense/Both for expense, Income/Both for income)
**Where**: `tests/Feature/Recurrences/RecurrenceManagementTest.php` (add methods)
**Depends on**: None
**Reuses**: Existing `RecurrenceManagementTest` setup
**Requirement**: REEX-04

**Tools**:
- MCP: NONE
- Skill: NONE

**Done when**:
- [ ] Test: edit expense recurrence → categories only Expense/Both
- [ ] Test: edit income recurrence → categories only Income/Both
- [ ] Tests fail: RED (filter currently hardcoded to Income/Both)

**Tests**: feature
**Gate**: quick

**Verify**:
```bash
php artisan test --filter="test_edit_filters_categories_for_expense|test_edit_filters_categories_for_income"
```
Expected: 2 new tests fail

---

### T10: RecurrenceController category filter by type implementation

**What**: Update `edit()` to filter categories based on `$recurrence->type`
**Where**: `app/Http/Controllers/RecurrenceController.php` (lines 79-83)
**Depends on**: T9
**Reuses**: Existing `edit()` structure
**Requirement**: REEX-04

**Tools**:
- MCP: NONE
- Skill: NONE

**Done when**:
- [ ] Filter branches: Expense → `[Expense, Both]`, Income → `[Income, Both]`
- [ ] All T9 tests pass
- [ ] Existing recurrence management tests still pass
- [ ] Gate check passes: `composer quality`

**Tests**: feature
**Gate**: quick

**Verify**:
```bash
php artisan test --filter=RecurrenceManagementTest
composer quality
```
Expected: All tests pass

**Commit**: `feat(recurrences): category filter by type in RecurrenceController::edit`

---

### T11: RecurrenceResource type field + RecurrenceFactory expense state

**What**: Add `type` to RecurrenceResource output + add `expense()` state to factory
**Where**: `app/Http/Resources/RecurrenceResource.php`, `database/factories/RecurrenceFactory.php`
**Depends on**: None
**Reuses**: Existing Resource and Factory patterns
**Requirement**: REEX-02

**Tools**:
- MCP: NONE
- Skill: NONE

**Done when**:
- [ ] `RecurrenceResource::toArray()` includes `'type' => $this->type->value`
- [ ] `RecurrenceFactory::expense()` state sets `type = 'expense'`
- [ ] Gate check passes: `composer quality`

**Tests**: feature
**Gate**: quick

**Verify**:
```bash
php artisan test --filter=RecurrenceResourceTest
composer quality
```
Expected: Resource test passes (if exists), quality gate green

**Commit**: `feat(recurrences): add type to RecurrenceResource and expense factory state`

---

### T12: Transactions/Create.tsx recurrence panel [P]

**What**: Add recurrence toggle (Switch) + conditional panel (frequency, frequency_day, until_date) to expense creation form
**Where**: `resources/js/Pages/Transactions/Create.tsx`
**Depends on**: T11
**Reuses**: `Incomes/Create.tsx` recurrence panel pattern, `WEEKDAYS` constant, shadcn `Switch`/`Checkbox`
**Requirement**: REEX-01, D-64

**Tools**:
- MCP: NONE
- Skill: fin-design-system

**Done when**:
- [ ] Imports `Switch`, `Checkbox` from shadcn
- [ ] `useForm` includes `is_recurring`, `frequency`, `frequency_day`, `until_date`, `has_until_date`
- [ ] Toggle reveals recurrence panel (frequency select, frequency_day input, until_date picker)
- [ ] Submit payload includes recurrence fields when toggle ON
- [ ] `WEEKDAYS` constant defined
- [ ] Form errors display correctly
- [ ] Gate check passes: `npm run quality`
- [ ] Build passes: `npm run build`

**Tests**: none
**Gate**: quick

**Verify**:
```bash
npm run quality
npm run build
```
Expected: Quality gate green, build succeeds

**Commit**: `feat(transactions): add recurrence panel to expense creation form`

---

### T13: Recurrences/Index.tsx type column + filter + routing [P]

**What**: Add type badge column, type filter, value color by type, "Ver instâncias" routing by type, update page copy
**Where**: `resources/js/Pages/Recurrences/Index.tsx`
**Depends on**: T11
**Reuses**: Existing `Recurrences/Index.tsx` DataTable structure, `TYPE_OPTIONS` constant pattern
**Requirement**: REEX-02, D-63

**Tools**:
- MCP: NONE
- Skill: fin-design-system

**Done when**:
- [ ] `type` added to `RecurrenceItem` interface
- [ ] Type column: badge "Receita" (emerald) / "Despesa" (red)
- [ ] Type filter: select with all/income/expense options
- [ ] Value column: color by type (emerald-600 income, red-600 expense)
- [ ] "Ver instâncias" routes to `transactions.index` for expense, `incomes.index` for income
- [ ] Page copy: "Regras de recorrências" / "Nova recorrência" / "Nenhuma recorrência cadastrada"
- [ ] Gate check passes: `npm run quality`
- [ ] Build passes: `npm run build`

**Tests**: none
**Gate**: quick

**Verify**:
```bash
npm run quality
npm run build
```
Expected: Quality gate green, build succeeds

**Commit**: `feat(recurrences): type column, filter, and routing by type in recurrence list`

---

### T14: PlanningService expense recurrence projection tests [P]

**What**: Add 2 tests verifying PlanningService includes expense recurrences in projections and balance calculation
**Where**: `tests/Feature/PlanningServiceTest.php`
**Depends on**: T2 (recurrence service handles expense type)
**Reuses**: Existing `PlanningServiceTest` setup, `RecurrenceFactory::expense()` from T11
**Requirement**: REEX-03

**Tools**:
- MCP: NONE
- Skill: NONE

**Done when**:
- [ ] Test: expense recurrence projects as expense in monthly table
- [ ] Test: mixed income+expense recurrences balance correctly (balance = incomes - expenses)
- [ ] Tests pass: PlanningService already generic, just needs verification

**Tests**: feature
**Gate**: quick

**Verify**:
```bash
php artisan test --filter=PlanningServiceTest
```
Expected: 2 new tests pass (PlanningService already reads `$recurrence->type`)

---

### T15: Cypress E2E tests

**What**: Add 3 E2E tests: create recurring expense, filter recurrence list by type, planning shows expense projection
**Where**: `cypress/e2e/recurring-expenses.cy.js`
**Depends on**: T12, T13, T14
**Reuses**: Existing Cypress patterns from `incomes.cy.js`
**Requirement**: REEX-01, REEX-02, REEX-03

**Tools**:
- MCP: NONE
- Skill: NONE

**Done when**:
- [ ] Test: toggle recurrence ON → fill frequency → submit → see in recurrence list
- [ ] Test: filter recurrence list by "Despesa" → see only expense recurrences
- [ ] Test: create expense recurrence → open planning → see in monthly expenses
- [ ] All E2E tests pass: `npx cypress run --spec cypress/e2e/recurring-expenses.cy.js`

**Tests**: e2e
**Gate**: full

**Verify**:
```bash
npx cypress run --spec cypress/e2e/recurring-expenses.cy.js
```
Expected: All 3 E2E tests pass

**Commit**: `test(e2e): recurring expenses creation, filter, and planning projection`

---

### T16: Quality gate — full verification

**What**: Run complete quality gate: composer quality, npm run quality, full PHPUnit suite, Cypress suite, build
**Where**: N/A (verification task)
**Depends on**: T12, T13, T14, T15
**Reuses**: D-51 quality gate commands
**Requirement**: All

**Tools**:
- MCP: NONE
- Skill: NONE

**Done when**:
- [ ] `composer quality` passes (Pint + PHPMD)
- [ ] `npm run quality` passes (ESLint + Prettier)
- [ ] `php artisan test` passes (full suite, no regressions)
- [ ] `npx cypress run` passes (full E2E suite)
- [ ] `npm run build` passes
- [ ] Test count: no silent deletions (compare with pre-feature count)

**Tests**: none
**Gate**: full

**Verify**:
```bash
composer quality
npm run quality
php artisan test
npx cypress run
npm run build
```
Expected: All gates green

---

## Parallel Execution Map

```
Phase 1 (Sequential):
  T1 ──→ T2

Phase 2 (Sequential):
  T3 ──→ T4 ──→ T5 ──→ T6
  (T3 can run parallel with T1 — independent test files)

Phase 3 (Sequential):
  T7 → T8 → T9 → T10 → T11
  (T7, T9 can run parallel with T1 — independent test files)

Phase 4 (Parallel):
  T11 complete, then:
    ├── T12 [P]  (Transactions/Create.tsx)
    ├── T13 [P]  (Recurrences/Index.tsx)
    └── T14 [P]  (PlanningService tests)

Phase 5 (Sequential):
  T15 ──→ T16
```

---

## Task Granularity Check

| Task | Scope | Status |
|------|-------|--------|
| T1: RecurrenceService expense tests | 1 test file, 4 methods | ✅ Granular |
| T2: RecurrenceService remove hardcodes | 1 service file, 4 lines | ✅ Granular |
| T3: StoreTransactionRequest tests | 1 test file, 3 methods | ✅ Granular |
| T4: StoreTransactionRequest rules | 1 request file, 1 method | ✅ Granular |
| T5: TransactionController tests | 1 test file, 3 methods | ✅ Granular |
| T6: TransactionController branch | 1 controller, 1 method | ✅ Granular |
| T7: UpdateRecurrenceRequest tests | 1 test file, 2 methods | ✅ Granular |
| T8: UpdateRecurrenceRequest guard | 1 request file, 1 method | ✅ Granular |
| T9: RecurrenceController tests | 1 test file, 2 methods | ✅ Granular |
| T10: RecurrenceController filter | 1 controller, 1 method | ✅ Granular |
| T11: Resource + Factory | 2 files, small additions | ✅ Granular |
| T12: Transactions/Create.tsx panel | 1 component, 1 feature | ✅ Granular |
| T13: Recurrences/Index.tsx enhancements | 1 component, 1 feature | ✅ Granular |
| T14: PlanningService tests | 1 test file, 2 methods | ✅ Granular |
| T15: Cypress E2E | 1 spec file, 3 tests | ✅ Granular |
| T16: Quality gate | Verification only | ✅ Granular |

---

## Diagram-Definition Cross-Check

| Task | Depends On (body) | Diagram Shows | Status |
|------|-------------------|---------------|--------|
| T1 | None | No incoming arrows | ✅ Match |
| T2 | T1 | T1 → T2 | ✅ Match |
| T3 | None | No incoming arrows | ✅ Match |
| T4 | T3 | T3 → T4 | ✅ Match |
| T5 | None | No incoming arrows | ✅ Match |
| T6 | T5, T2, T4 | T5 → T6, T2 → T6, T4 → T6 | ✅ Match |
| T7 | None | No incoming arrows | ✅ Match |
| T8 | T7 | T7 → T8 | ✅ Match |
| T9 | None | No incoming arrows | ✅ Match |
| T10 | T9 | T9 → T10 | ✅ Match |
| T11 | None | No incoming arrows | ✅ Match |
| T12 | T11 | T11 → T12 | ✅ Match |
| T13 | T11 | T11 → T13 | ✅ Match |
| T14 | T2 | T2 → T14 (implicit, PlanningService depends on RecurrenceService type) | ✅ Match |
| T15 | T12, T13, T14 | T12 → T15, T13 → T15, T14 → T15 | ✅ Match |
| T16 | T12, T13, T14, T15 | T15 → T16 | ✅ Match |

---

## Test Co-location Validation

| Task | Code Layer | Matrix Requires | Task Says | Status |
|------|------------|-----------------|-----------|--------|
| T1: RecurrenceService tests | Service | feature | feature | ✅ OK |
| T2: RecurrenceService impl | Service | feature | feature | ✅ OK |
| T3: StoreTransactionRequest tests | Request | feature | feature | ✅ OK |
| T4: StoreTransactionRequest impl | Request | feature | feature | ✅ OK |
| T5: TransactionController tests | Controller | feature | feature | ✅ OK |
| T6: TransactionController impl | Controller | feature | feature | ✅ OK |
| T7: UpdateRecurrenceRequest tests | Request | feature | feature | ✅ OK |
| T8: UpdateRecurrenceRequest impl | Request | feature | feature | ✅ OK |
| T9: RecurrenceController tests | Controller | feature | feature | ✅ OK |
| T10: RecurrenceController impl | Controller | feature | feature | ✅ OK |
| T11: Resource + Factory | Resource | feature | feature | ✅ OK |
| T12: Transactions/Create.tsx | Frontend | none | none | ✅ OK |
| T13: Recurrences/Index.tsx | Frontend | none | none | ✅ OK |
| T14: PlanningService tests | Service | feature | feature | ✅ OK |
| T15: Cypress E2E | E2E | e2e | e2e | ✅ OK |
| T16: Quality gate | Verification | none | none | ✅ OK |

---

## Requirement Traceability

| Req ID | Description | Tasks |
|--------|-------------|-------|
| REEX-01 | Create Recurring Expense | T1, T2, T3, T4, T5, T6, T12 |
| REEX-02 | Recurrence Management Shows Both Types | T11, T13 |
| REEX-03 | Recurring Expenses in Planning | T14 |
| REEX-04 | Category Guard for Expense Recurrences | T7, T8, T9, T10 |
