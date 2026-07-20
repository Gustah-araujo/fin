# Receitas — Tasks

**Story ID:** INCM-01  
**Phase:** P1 — MVP Core  
**Spec:** `.specs/features/receitas/spec.md`  
**Design:** `.specs/features/receitas/design.md`  

---

## Overview

TDD-first implementation. Tests are written BEFORE implementation code for each task.  
Total estimated tasks: 20.  
Parallelization: T1–T6 can run in parallel after brownfield context; T7–T9 depend on T1–T6; T10–T13 depend on T7–T9; T14–T16 depend on T10–T13; T17–T18 are written alongside each implementation task but executed as a batch at T19.

---

## Phase 1 — Backend Foundation

### T1: Create `recurrences` table and `recurrence_id` FK on transactions

**What:** Create two migrations.

**Where:**
- `database/migrations/2026_07_20_000001_create_recurrences_table.php`
- `database/migrations/2026_07_20_000002_add_recurrence_id_to_transactions.php`

**Depends on:** Brownfield mapping (Transaction/Account/Category/Tag schemas known).

**Reuses:** Existing migration patterns (FKs reference `id`, uuid for route binding, softDeletes, timestamps).

**Done when:**
- `recurrences` table has all columns from design.md section 1.1 including `status`.
- `transactions.recurrence_id` FK exists and is nullable.
- `php artisan migrate:fresh` succeeds.

**Tests:**
- Migration test: `assertDatabaseHasTable('recurrences')` and columns exist.
- Foreign key constraint test: inserting transaction with invalid `recurrence_id` fails.

**Gate:** `php artisan migrate:fresh` passes.

---

### T2: Create `RecurrenceFrequency` and `RecurrenceStatus` enums

**What:** Create two PHP 8.3 native enums.

**Where:**
- `app/Enums/RecurrenceFrequency.php`
- `app/Enums/RecurrenceStatus.php`

**Depends on:** T1 (schema references these enum values).

**Reuses:** Pattern from existing `TransactionType`, `BillStatus` enums.

**Done when:**
- Enums have cases `Weekly`/`Monthly` and `Active`/`Paused`.
- `label()` and `dayLabel()` methods return pt-BR strings.

**Tests:**
- Unit assertion (within a feature test file): enum values cast correctly, labels are in pt-BR.

**Gate:** `php artisan test --filter=RecurrenceEnum` passes.

---

### T3: Create `Recurrence` model

**What:** Eloquent model with all relations, casts, and traits.

**Where:**
- `app/Models/Recurrence.php`

**Depends on:** T1, T2.

**Reuses:** Pattern from `Transaction`/`Account` models.

**Done when:**
- Model uses `HasUuids`, `SoftDeletes`, `HasFactory`.
- `$fillable` includes all columns except `id`.
- Casts include enum classes.
- Relations: `workspace`, `account`, `category`, `creator`, `transactions`, `tags`.
- Route binding via `uuid`.

**Tests:**
- Factory can create a Recurrence.
- Relations return expected types.

**Gate:** `php artisan test --filter=RecurrenceModel` passes.

---

### T4: Update `Transaction` model for recurrence link

**What:** Add `recurrence_id` to `$fillable` and add `recurrence()` relation.

**Where:**
- `app/Models/Transaction.php`

**Depends on:** T1.

**Reuses:** Existing Transaction model.

**Done when:**
- `$fillable` includes `recurrence_id`.
- `recurrence()` `BelongsTo` relation exists.

**Tests:**
- Transaction factory can create transaction linked to recurrence.
- Existing expense tests still pass.

**Gate:** `php artisan test --filter=Transaction` passes.

---

### T5: Create `RecurrencePolicy` and register it

**What:** Policy for Recurrence model and AuthServiceProvider registration.

**Where:**
- `app/Policies/RecurrencePolicy.php`
- `app/Providers/AuthServiceProvider.php`

**Depends on:** T3.

**Reuses:** Pattern from `TransactionPolicy`.

**Done when:**
- All methods from design.md section 1.5 implemented.
- Policy registered in `AuthServiceProvider`.

**Tests:**
- Policy feature tests: viewer denied, editor/admin allowed, cross-workspace denied.

**Gate:** `php artisan test --filter=RecurrencePolicy` passes.

---

### T6: Create `RecurrenceResource` and update `TransactionResource`

**What:** API resources for backend→frontend responses.

**Where:**
- `app/Http/Resources/RecurrenceResource.php`
- `app/Http/Resources/TransactionResource.php`

**Depends on:** T3, T4.

**Reuses:** Existing `AccountResource`, `CategoryResource`, `TagResource`.

**Done when:**
- `RecurrenceResource` returns all fields from design.md.
- `TransactionResource` includes `recurrence_id`, `recurrence` (when loaded), `is_paid`.

**Tests:**
- Resource shape test: assert JSON keys/values.

**Gate:** `php artisan test --filter=Resource` passes.

---

## Phase 2 — Business Logic

### T7: Create `RecurrenceService`

**What:** Core service for recurrence lifecycle.

**Where:**
- `app/Services/RecurrenceService.php`

**Depends on:** T1, T2, T3, T4, T6.

**Reuses:** `TransactionService` for creating instances, `AccountService` for recalculation.

**Done when:**
- `create()` and `createWithFirstInstance()` implemented.
- `generateNextInstance()` implemented with optimistic lock and date arithmetic.
- `recomputeNextDate()` implemented.
- `updateRule()` implemented.
- `pause()` and `restore()` implemented.
- `syncTags()` implemented.
- `updateThisAndFuture()` and `deleteThisAndFuture()` implemented **synchronously** (background job deferred to T9).
- Every workspace-scoped query includes `workspace_id`.

**Tests:**
- Feature tests for all scenarios in spec.md INCM-03 and INCM-04.
- Test optimistic lock: concurrent generation does not duplicate.
- Test monthly day-31 fallback.
- Test past-due next_date generates one transaction and advances.
- Test scope edit/update and delete.

**Gate:** `php artisan test --filter=RecurrenceService` passes.

---

### T8: Refactor `TransactionService` for income support

**What:** Make `create()` type-aware and guard archived account on pay.

**Where:**
- `app/Services/TransactionService.php`

**Depends on:** T4.

**Reuses:** Existing pay/unpay/archive methods.

**Done when:**
- `create()` accepts `type` from `$data` instead of hardcoding `expense`.
- Existing expense creation behavior unchanged (existing tests green).
- `pay()` rejects if linked account is soft-deleted.
- `recalculateAfterUpdate()` handles account move correctly.

**Tests:**
- `TransactionServiceTest` or feature tests: create income, pay income, unpay income.
- All existing `Transactions` feature tests still pass.

**Gate:** `php artisan test --filter=Transaction` passes.

---

### T9: Create `ProcessRecurrencesJob` and `ApplyRecurrenceScopeChangeJob`

**What:**
- `ProcessRecurrencesJob`: daily scheduled job wrapping `RecurrenceService::generateNextInstance()`.
- `ApplyRecurrenceScopeChangeJob`: background job for `updateThisAndFuture` / `deleteThisAndFuture` to avoid synchronous bulk transactions.

**Where:**
- `app/Jobs/ProcessRecurrencesJob.php`
- `app/Jobs/ApplyRecurrenceScopeChangeJob.php`
- `routes/console.php`

**Depends on:** T7, T8.

**Reuses:** `CloseBillsJob` pattern.

**Done when:**
- `ProcessRecurrencesJob` queries active recurrences with `next_date <= today`, handles per-recurrence errors, logs.
- `ApplyRecurrenceScopeChangeJob` accepts operation type, transaction id, payload; applies idempotently; dispatches from `RecurrenceService` scope methods.
- `routes/console.php` schedules `ProcessRecurrencesJob` at `00:00`.

**Tests:**
- Feature test: dispatch job manually, verify transaction created.
- Feature test: idempotency (job run twice = one transaction).
- Feature test: background scope job updates future transactions without timeout.

**Gate:** `php artisan test --filter=Job` passes.

---

## Phase 3 — Controllers & Validation

### T10: Create FormRequests

**What:** Validation layer for income and recurrence operations.

**Where:**
- `app/Http/Requests/StoreIncomeRequest.php`
- `app/Http/Requests/UpdateIncomeRequest.php`
- `app/Http/Requests/UpdateRecurrenceRequest.php`

**Depends on:** T2, T3, T4.

**Reuses:** Pattern from `StoreTransactionRequest`/`UpdateTransactionRequest`.

**Done when:**
- All rules from design.md section 1.4 implemented.
- `withValidator()` enforces workspace scoping, category type guard, archived account rejection, frequency_day range.
- `StoreIncomeRequest` handles `is_recurring` toggle.
- `UpdateIncomeRequest` handles `scope` field.

**Tests:**
- Feature tests for all validation scenarios in spec.md INCM-01 AC #6–#13.
- Cross-workspace rejection tests.

**Gate:** `php artisan test --filter=IncomeValidation` passes.

---

### T11: Create `IncomeController`

**What:** Resource controller for income CRUD + pay/unpay.

**Where:**
- `app/Http/Controllers/IncomeController.php`

**Depends on:** T7, T8, T10.

**Reuses:** Pattern from `TransactionController`.

**Done when:**
- All methods from design.md section 1.3 implemented.
- `store()` branches between avulsa and recurring.
- `update()` and `destroy()` handle scope.
- `pay()`/`unpay()` reuse `TransactionService`.

**Tests:**
- Feature tests for INCM-01, INCM-02, INCM-04.
- Authorization tests.

**Gate:** `php artisan test --filter=IncomeController` passes.

---

### T12: Create `RecurrenceController`

**What:** Controller for recurrence management page.

**Where:**
- `app/Http/Controllers/RecurrenceController.php`

**Depends on:** T7, T10.

**Reuses:** Pattern from `CardExpenseController` for custom actions.

**Done when:**
- `index`, `edit`, `update`, `pause`, `restore`, `destroy`, `generateNow` implemented.
- Routes registered as in design.md.

**Tests:**
- Feature tests for INCM-05.
- Authorization tests.

**Gate:** `php artisan test --filter=RecurrenceController` passes.

---

### T13: Register routes

**What:** Add income and recurrence routes.

**Where:**
- `routes/web.php`
- `routes/console.php`

**Depends on:** T11, T12, T9.

**Done when:**
- Resource routes and custom POST routes for incomes and recurrences exist.
- `ProcessRecurrencesJob` scheduled.

**Tests:**
- `php artisan route:list` includes all new routes.
- Route model binding returns 404 for cross-workspace access.

**Gate:** `php artisan route:list` shows new routes; `php artisan test --filter=Authorization` passes.

---

## Phase 4 — Frontend

### T14: Create income pages

**What:** Inertia pages for income list, create, edit.

**Where:**
- `resources/js/Pages/Incomes/Index.tsx`
- `resources/js/Pages/Incomes/Create.tsx`
- `resources/js/Pages/Incomes/Edit.tsx`

**Depends on:** T11, T13.

**Reuses:** `AuthenticatedLayout`, shadcn/ui components, `useForm`, `formatCurrency`, existing Transactions pages pattern.

**Done when:**
- `Create.tsx` has unified form with recurrence toggle and panel.
- `Edit.tsx` shows scope selector when `recurrence_id` present.
- `Index.tsx` lists incomes with status badges, recurring badge, confirm/unconfirm, filters (P2 can be stubbed).

**Tests:**
- Cypress E2E (optional): create income, confirm receipt.
- Manual smoke: form renders, toggle works, validation errors display.

**Gate:** `npm run build` succeeds.

---

### T15: Create recurrence pages

**What:** Inertia pages for recurrence list and edit.

**Where:**
- `resources/js/Pages/Recurrences/Index.tsx`
- `resources/js/Pages/Recurrences/Edit.tsx`

**Depends on:** T12, T13.

**Reuses:** Same as T14.

**Done when:**
- `Index.tsx` lists active/paused/exhausted recurrences with actions.
- `Edit.tsx` allows editing all fields except `start_date` when transactions exist.

**Tests:**
- Manual smoke: pause, restore, edit frequency, generate now.

**Gate:** `npm run build` succeeds.

---

### T16: Update sidebar navigation

**What:** Fix "Receitas" link in AppSidebar.

**Where:**
- `resources/js/Components/AppSidebar.tsx`

**Depends on:** T13.

**Done when:**
- Receitas link uses `route('incomes.index', { workspace })`.

**Tests:**
- Manual smoke: click navigates to income list.

**Gate:** `npm run build` succeeds.

---

## Phase 5 — Test Suite & Integration

### T17: Write PHPUnit feature tests for incomes

**What:** Comprehensive feature tests mirroring existing Transactions tests.

**Where:**
- `tests/Feature/Incomes/IncomeCreationTest.php`
- `tests/Feature/Incomes/IncomeValidationTest.php`
- `tests/Feature/Incomes/IncomeConfirmationTest.php`
- `tests/Feature/Incomes/IncomeUpdateTest.php`
- `tests/Feature/Incomes/IncomeDeletionTest.php`
- `tests/Feature/Incomes/IncomeAuthorizationTest.php`
- `tests/Feature/Incomes/IncomeFilteringTest.php`

**Depends on:** T10, T11.

**Done when:**
- All INCM-01/INCM-02/INCM-04/INCM-06 acceptance criteria covered.
- Tests written BEFORE implementation (TDD).

**Gate:** `php artisan test --filter=Income` passes.

---

### T18: Write PHPUnit feature tests for recurrences

**What:** Comprehensive feature tests for recurrence lifecycle and job.

**Where:**
- `tests/Feature/Recurrences/RecurrenceCreationTest.php`
- `tests/Feature/Recurrences/RecurrenceGenerationTest.php`
- `tests/Feature/Recurrences/RecurrenceJobTest.php`
- `tests/Feature/Recurrences/RecurrenceManagementTest.php`
- `tests/Feature/Recurrences/RecurrenceAuthorizationTest.php`

**Depends on:** T7, T9, T12.

**Done when:**
- All INCM-03/INCM-05 acceptance criteria covered.
- Tests written BEFORE implementation.

**Gate:** `php artisan test --filter=Recurrence` passes.

---

### T19: Run full test suite and fix regressions

**What:** Execute all tests and ensure no regressions in existing features.

**Depends on:** T17, T18.

**Done when:**
- `php artisan test` passes (or all failures explained/accepted).
- Existing `Transactions`, `CardExpenses`, `Accounts`, `Categories` tests still pass.

**Gate:** `php artisan test` passes.

---

## Phase 6 — Verification & Handoff

### T20: Manual smoke and verification

**What:** Manual end-to-end validation.

**Depends on:** T14, T15, T16, T19.

**Done when:**
- Create avulsa income → visible in list.
- Create recurring income with start_date ≤ today → recurrence + transaction created.
- Create recurring income with start_date > today → only recurrence.
- Confirm receipt → balance updates.
- Edit scope "Esta e futuras" → future instances update.
- Delete scope "Esta e parar futuras" → past remains, future stops.
- Pause/restore recurrence → status changes, job respects status.
- Manual "Gerar agora" works.
- Cross-workspace access returns 404.
- Viewer denied mutating actions.

**Gate:** Smoke checklist signed off.

---

### T21: Cypress E2E tests

**What:** Write and run Cypress end-to-end tests for the critical user journeys.

**Where:**
- `cypress/e2e/incomes.cy.ts`
- `cypress/e2e/recurrences.cy.ts`
- `cypress/support/commands.ts` (add helpers if needed)

**Depends on:** T14, T15, T16.

**Reuses:** Existing Cypress setup in the project.

**Done when:**
- E2E tests cover the 5 journeys defined in design.md section 4.2.
- All tests use `data-testid` selectors.
- Tests run against `http://localhost:8090` (Fin app service).
- Cypress profile is started with `docker compose --profile testing up cypress`.

**Tests to Add:**

`cypress/e2e/incomes.cy.ts`:
- `user can create an avulsa income and see it in the list`
- `user can create a recurring income and see the first instance`
- `user can confirm and unconfirm receipt, updating account balance`
- `user can edit a recurrence-generated income with "Esta e futuras" scope`
- `user can delete a recurrence-generated income with "Esta e parar futuras" scope`

`cypress/e2e/recurrences.cy.ts`:
- `user can pause and reactivate a recurrence`
- `user can manually generate a recurrence instance`
- `viewer cannot see action buttons on recurrences page`

**Gate Commands:**

```bash
docker compose up -d app db
docker compose exec app php artisan migrate:fresh --seed
# or run Cypress container
docker compose --profile testing run --rm cypress
```

**Output:**

Report back:
- Status
- Files changed
- Gate check result (Cypress pass/fail count)
- Issues / SPEC_DEVIATION

---

## Updated Execution Order

1. **Parallel batch A:** T1, T2, T3, T4, T5, T6
2. **Parallel batch B:** T7, T8, T10
3. **T9** after T7, T8
4. **Parallel batch C:** T11, T12
5. **T13** after T11, T12
6. **Parallel batch D:** T14, T15, T16, T17, T18
7. **T19** after T17, T18
8. **T20** after T19
9. **T21** after T14, T15, T16

---

## Updated Verification Gates

- `php artisan test` passes
- `npm run build` succeeds
- **Cypress E2E suite passes (hard gate)**
- Manual smoke checklist signed off

**No feature is considered complete until both PHPUnit and Cypress gates pass.**

---

## Dependencies Graph

```
T1 ─┬─ T3 ─┬─ T5 ─┬─ T11 ─┬─ T14
T2 ─┘     ├─ T6 ─┘       ├─ T15
T4 ─┘     ├─ T7 ─┬─ T9 ─┬─ T12 ─┘
          ├─ T8 ─┘     ├─ T13 ─┬─ T16
          └─ T10 ─┘    └─ T17 ─┬─ T19
                              └─ T18 ─┘
                              └─ T20
```

---

## Execution Order

1. **Parallel batch A:** T1, T2, T3, T4, T5, T6
2. **Parallel batch B:** T7, T8, T10
3. **T9** after T7, T8
4. **Parallel batch C:** T11, T12
5. **T13** after T11, T12
6. **Parallel batch D:** T14, T15, T16, T17, T18
7. **T19** after T17, T18
8. **T20** after T19

---

## Risk Adjustments

- **T9 background job:** If queue infrastructure is not available, defer `ApplyRecurrenceScopeChangeJob` and implement scope methods synchronously with a limit/throttle on number of affected transactions. Document this fallback.
- **T14/T15 P2 filters:** If scope pressure, implement filters as stubbed UI without backend search; complete in follow-up.

---

End of tasks.
