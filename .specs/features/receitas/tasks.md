# Receitas — Tasks

**Design:** `.specs/features/receitas/design.md`
**Spec:** `.specs/features/receitas/spec.md`
**Context:** `.specs/features/receitas/context.md`
**Status:** Draft

---

## TDD-First Ordering

Every behavioral task follows strict red-green-refactor:

1. **RED**: Write the PHPUnit test file — run it, confirm it FAILS for the right reason (class not found, route 404, etc.)
2. **GREEN**: Implement the minimum code (service, controller, routes, FormRequest, policy, resource) to make ALL tests in that file pass
3. **REFACTOR**: Clean up, re-run tests, confirm still green

Structural tasks (T1) are not TDD — verified by `migrate:fresh` + `optimize:clear`.

---

## Execution Plan

```
Phase 1: Structural Foundation (sequential)
  T1

Phase 2: Income Vertical Slice (sequential, TDD)
  T1 ──→ T2 ──→ T3 ──→ T4 ──→ T5

Phase 3: Recurring + Transfer + Auth (parallel, TDD — after T5)
                ┌→ T6  [P]  (Recurring service + controller)
  T5 ──────────┼→ T7  [P]  (Transfer service + controller)
                └→ T8  [P]  (Income authorization tests)

Phase 4: Recurring Job (sequential, TDD — after T6)
  T6 ──→ T9

Phase 5: Frontend (parallel, build-only — after backend)
                  ┌→ T10 [P]  (Incomes pages)
  T6,T7,T8 ──────┼→ T11 [P]  (Transfers pages)
                  └→ T12 [P]  (Recurring pages)
  T10,T11,T12 ──→ T13  (Sidebar nav + final build)

Phase 6: E2E (sequential — after all frontend)
  T13 ──→ T14

Phase 7: Final Gate (sequential)
  T14 ──→ T15
```

---

## Task Breakdown

### T1: Migration, Model Extensions, Resources, Factory, Test Base

**What:** Create structural foundation: migration adding recurring + transfer columns to `transactions`, extend `Transaction` model with fillable/casts/relations/helpers (isRecurringTemplate, isRecurringOccurrence, isTransfer), extend `TransactionResource` and `CategoryResource`, update `TransactionFactory`, promote `TransactionService::syncTags` to `protected`, add `CategoryService::ensureSystemCategory`, create `IncomeTestCase` base class.

**Where:**
- `database/migrations/2026_07_16_000001_add_recurring_and_transfer_columns_to_transactions.php` (new)
- `app/Models/Transaction.php` (modify — add fillable: is_recurring, recurring_parent_uuid, recurring_ends_at, recurring_year_month, transfer_group_id; casts: is_recurring→boolean, recurring_ends_at→date; relations: recurringParent, recurringOccurrences, transferSiblings; helpers: isTransfer(), isRecurringTemplate(), isRecurringOccurrence())
- `app/Http/Resources/TransactionResource.php` (modify — add is_recurring, is_recurring_template, recurring_parent_uuid, recurring_ends_at, is_transfer, transfer_group_id fields)
- `app/Http/Resources/CategoryResource.php` (modify — add `is_system` field)
- `app/Services/TransactionService.php` (modify — promote `syncTags` from private to `protected` for reuse by IncomeService)
- `app/Services/CategoryService.php` (modify — add `ensureSystemCategory(Workspace, string, TransactionType): int` method, find-or-create by name+workspace, set is_system=true)
- `database/factories/TransactionFactory.php` (modify — add new fields with safe defaults: is_recurring=false, transfer_group_id=null, recurring_*=null)
- `tests/Feature/Incomes/IncomeTestCase.php` (new — abstract base with helpers: createWorkspaceWithMember, createAccount, createIncomeCategory, createTag, attachMember)

**Depends on:** None
**Reuses:** Existing `Transaction` model patterns, `CategoryService` patterns, existing `is_system` migration on categories
**Requirement:** INCM-01 (schema), INCM-03 (schema), INCM-04 (schema), INCM-05 (schema)

**Done when:**
- [ ] Migration adds: `is_recurring` (boolean default false), `recurring_parent_uuid` (uuid nullable), `recurring_ends_at` (date nullable), `recurring_year_month` (char(7) nullable), `transfer_group_id` (uuid nullable) AFTER `installment_group_id`
- [ ] Migration creates unique index `uniq_recurring_occurrence` on (`recurring_parent_uuid`, `recurring_year_month`)
- [ ] Migration creates index on `transfer_group_id`
- [ ] Transaction model: new fields in $fillable, casts as specified, 3 relations + 3 helper methods
- [ ] TransactionResource: 6 new fields exposed
- [ ] CategoryResource: `is_system` field exposed
- [ ] TransactionService::syncTags is `protected` (not private)
- [ ] CategoryService::ensureSystemCategory creates `type=Both, is_system=true` category if missing, returns id; idempotent
- [ ] TransactionFactory: new fields defaulted in definition
- [ ] IncomeTestCase base class with helper methods
- [ ] Gate: `docker compose exec app php artisan migrate:fresh` succeeds
- [ ] Gate: `docker compose exec app php artisan optimize:clear` succeeds
- [ ] Gate: `docker compose exec app php artisan test` — all existing tests pass (no regression)

**Tests:** None (structural — tested implicitly by subsequent TDD tasks)
**Gate:** Quick (`php artisan migrate:fresh && php artisan test`)

---

### T2: Income CRUD — Create + Index [TDD]

**What:** Implement `IncomeService::create` (simple receita, `type=income, paid_at=null`), `IncomeController` (index, create, store), `StoreIncomeRequest` with workspace-scoped validation rejeitando categoria `type=Expense`. Routes registered. PHP unit tests for create + index pass.

**Where:**
- `app/Services/IncomeService.php` (new — `create(Workspace, User, array): Transaction` method, resolves account/category by uuid within workspace, syncs tags)
- `app/Http/Controllers/IncomeController.php` (new — `index`, `create`, `store` actions; index filters `type=income AND transfer_group_id IS NULL` with pagination 25 + search/category/account/date/status/flag filters; create returns Inertia with categories `whereIn type=Income,Both`)
- `app/Http/Requests/StoreIncomeRequest.php` (new — mirror StoreTransactionRequest, custom validator rejeita category `type=Expense`, valida `installments_total` int 1..60 opcional, `is_recurring` bool opcional, `recurring_ends_at` date opcional maior que `date` se recurring, mutuamente exclusivo recurring AND installment>1)
- `routes/web.php` (modify — add `Route::resource('incomes', IncomeController::class)` inside `w/{workspace}` prefix)
- `tests/Feature/Incomes/IncomeCreationTest.php` (new — TDD: test_user_can_create_income; test_unpaid_income_starts_null_paid_at; test_income_uses_income_category_or_both; test_income_with_expense_category_rejected; test_viewer_cannot_create_income; test_cross_workspace_income_returns_404; test_index_lists_incomes_excluding_transfer_legs; test_index_filters_by_status_and_search; test_index_paginates_25)

**Depends on:** T1
**Reuses:** `TransactionService::syncTags` (now protected), `TransactionController` patterns, `StoreTransactionRequest` validation patterns, `IncomeTestCase` base
**Requirement:** INCM-01

**Done when:**
- [ ] RED: Run `docker compose exec app php artisan test --filter=IncomeCreationTest` — all tests FAIL (class IncomeService not found, route 404)
- [ ] GREEN: Implement service + controller + FormRequest + routes — all `IncomeCreationTest` tests pass
- [ ] IncomeService::create sets `type='income'`, `paid_at=null`, resolves uuids within workspace, syncs tags
- [ ] IncomeController::index excludes transfer legs (`WHERE transfer_group_id IS NULL`)
- [ ] IncomeController::create props: categories filtered `whereIn type=[Income, Both]`
- [ ] StoreIncomeRequest custom validator rejects `category.type=Expense` with "Categoria de despesa não pode ser usada em receita"
- [ ] Gate: `docker compose exec app php artisan test --filter=IncomeCreationTest` passes
- [ ] Test count: ~9 tests pass (no silent deletions)
- [ ] Gate: `docker compose exec app php artisan test --filter=Income` passes — no regression in other income tests (none yet)

**Tests:** Feature (PHPUnit)
**Gate:** Quick (`php artisan test --filter=IncomeCreationTest`)

**Commit:** `feat(income): create income CRUD create + index`

---

### T3: Income CRUD — Update + Delete [TDD]

**What:** Implement `IncomeService::update`, `IncomeService::deleteSingle`, `IncomeController::edit`, `update`, `destroy`, `UpdateIncomeRequest`. Saldo recalculado em contas old/new quando receita received é editada/movida. Delete de receita received reverte saldo.

**Where:**
- `app/Services/IncomeService.php` (modify — add `update(Transaction, array): Transaction` mirroring TransactionService::update with `recalculateAfterUpdate` pattern; add `deleteSingle(Transaction): void` with DB::transaction + recalc if paid + soft-delete)
- `app/Http/Controllers/IncomeController.php` (modify — add `edit`, `update`, `destroy` actions)
- `app/Http/Requests/UpdateIncomeRequest.php` (new — mirrors UpdateTransactionRequest, same category type validation, tags sync)
- `routes/web.php` (no change — resource covers edit/update/destroy)
- `tests/Feature/Incomes/IncomeUpdateTest.php` (new — edit unpaid changes fields; editing paid value recalcs saldo; editing paid account moves recalcs both; delete unpaid no saldo change; delete paid reverts saldo; cross-workspace 404)
- `tests/Feature/Incomes/IncomeDeletionTest.php` (new — soft-delete hides from list; received deleted appears nowhere; tags detached on delete)

**Depends on:** T2
**Reuses:** `TransactionService::update` + `recalculateAfterUpdate` patterns, `AccountService::recalculateBalance`
**Requirement:** INCM-01

**Done when:**
- [ ] RED: Run `--filter=IncomeUpdateTest` and `--filter=IncomeDeletionTest` — all tests FAIL (route 404 for edit/destroy)
- [ ] GREEN: Implement service methods + controller actions + FormRequest — all tests pass
- [ ] IncomeService::update: edits description/value/date/account/category/tags; tracks wasPaid, oldValue, oldAccountId; calls recalculateBalance on old/new account if paid and value or account changed
- [ ] IncomeService::deleteSingle: DB::transaction — soft-delete + recalc if paid_at was set
- [ ] UpdateIncomeRequest: validates same as Store + same category type rule
- [ ] Gate: `docker compose exec app php artisan test --filter=Income` passes
- [ ] Test count: ~10 tests pass across both new files (no silent deletions)

**Tests:** Feature (PHPUnit)
**Gate:** Domain (`php artisan test --filter=Income`)

**Commit:** `feat(income): update + delete income with balance recalc`

---

### T4: Income Receive + Unreceive [TDD]

**What:** Implement `IncomeService::receive`, `IncomeService::unreceive`, `IncomeController::receive`, `unreceive` actions. Marcar recebido soma saldo; desmarcar reverte. DB::transaction envolve recalc.

**Where:**
- `app/Services/IncomeService.php` (modify — add `receive(Transaction): void` mirroring TransactionService::pay with `paid_at=now()` + recalculateBalance; add `unreceive(Transaction): void` mirroring unpay)
- `app/Http/Controllers/IncomeController.php` (modify — add `receive`, `unreceive` actions, both POST endpoints with route-scoped 404 check + authorize)
- `routes/web.php` (modify — add `Route::post('incomes/{income}/receive', [IncomeController::class, 'receive'])->name('incomes.receive')` and `unreceive` analog)
- `tests/Feature/Incomes/IncomeReceiveTest.php` (new — receive unpaid sets paid_at + sum to balance; un-receive restores; receive already received is no-op or idempotent; unreceive not-received is no-op; viewer cannot receive 403; balance integrity after chained operations)

**Depends on:** T2
**Reuses:** `TransactionService::pay`/`unpay` patterns, `AccountService::recalculateBalance`
**Requirement:** INCM-02

**Done when:**
- [ ] RED: Run `--filter=IncomeReceiveTest` — fails (route 404)
- [ ] GREEN: Implement service methods + controller + routes — all pass
- [ ] IncomeService::receive: DB::transaction `{ paid_at=now(); save(); recalculateBalance(account); }`
- [ ] IncomeService::unreceive: DB::transaction `{ paid_at=null; save(); recalculateBalance(account); }`
- [ ] Gate: `docker compose exec app php artisan test --filter=IncomeReceiveTest` passes
- [ ] Test count: ~6 tests pass (no silent deletions)

**Tests:** Feature (PHPUnit)
**Gate:** Quick (`php artisan test --filter=IncomeReceiveTest`)

**Commit:** `feat(income): receive + unreceive actions with balance impact`

---

### T5: Income Installments — Create Group + Delete Group [TDD]

**What:** Implement `IncomeService::createInstallment` (N parcelas, group_id UUID, D-33 rounding, monthly dates via `addMonthsNoOverflow`), `IncomeService::deleteGroup` (soft-delete all parcels of group), `IncomeController::destroyGroup` (DELETE `/incomes/{income}/group`), `StoreIncomeRequest` already validates `installments_total`. Espelha `CardExpenseService::createInstallment`.

**Where:**
- `app/Services/IncomeService.php` (modify — add `createInstallment(Workspace, User, array): string` returning group_id; loop 1..N creating Transaction type=income with `installment_number`, `installments_total`, `installment_group_id=Str::orderedUuid()->toString()`, value=round(total/N,2) com remainder na última, date=firstDate->addMonthsNoOverflow($i-1), paid_at=null, syncs tags each row; add `deleteGroup(Transaction): void` soft-delete all rows WHERE installment_group_id=X, for each paid row recalc account)
- `app/Http/Controllers/IncomeController.php` (modify — add `destroyGroup(Workspace, Transaction)` action)
- `routes/web.php` (modify — add `Route::delete('incomes/{income}/group', [IncomeController::class, 'destroyGroup'])->name('incomes.destroy-group')`)
- `app/Http/Requests/StoreIncomeRequest.php` (modify — already validates installments_total in T2; if 当时 skipped, add `installments_total: sometimes|integer|between:2,60` + mutually exclusive with is_recurring)
- `tests/Feature/Incomes/IncomeInstallmentTest.php` (new — create 3x R$1000 generates 3 rows R$333.33, 333.33, 333.34; dates monthly; installment_number 1..3; installment_group_id same; delete-group soft-deletes all; delete-group with paid parcels recalcs each affected account; cross-workspace group delete 404)

**Depends on:** T2
**Reuses:** `CardExpenseService::createInstallment` pattern (rounding + addMonthsNoOverflow), `IncomeService::syncTags` (reused), `AccountService::recalculateBalance`
**Requirement:** INCM-03

**Done when:**
- [ ] RED: Run `--filter=IncomeInstallmentTest` — fails
- [ ] GREEN: Implement createInstallment + deleteGroup + destroyGroup + route — all pass
- [ ] Round-trip sum verification: 3 rolls totaling exactly total value (D-33)
- [ ] Date iteration via `Carbon::addMonthsNoOverflow($i-1)`
- [ ] Gate: `docker compose exec app php artisan test --filter=IncomeInstallmentTest` passes
- [ ] Test count: ~6 tests pass (no silent deletions)

**Tests:** Feature (PHPUnit)
**Gate:** Domain (`php artisan test --filter=Income`)

**Commit:** `feat(income): installment group create + delete with D-33 rounding`

---

### T6: Recurring Income Service + Controller [P] [TDD]

**What:** Implement `RecurringIncomeService` (createTemplate + immediate occurrence generation up to current month + updateTemplate with explicit propagate flag + cancelTemplate + deleteTemplate with orphan/delete-unreceived options), `RecurringIncomeController`, `UpdateRecurringIncomeRequest`, `CancelRecurringIncomeRequest`. Tests co-located.

**Where:**
- `app/Services/RecurringIncomeService.php` (new — `createTemplate(Workspace, User, array): Transaction` creates template Transaction `type=income, is_recurring=true, recurring_parent_uuid=null, paid_at=null, recurring_year_month=null` and calls `generateOccurrencesUpTo($template, Carbon::now()->startOfMonth())` in same DB::transaction; `generateOccurrencesUpTo(Transaction, Carbon): int` iterates months from template.date to min(now, recurring_ends_at), for each: try `IncomeService::create(recurring_parent_uuid=template.uuid, recurring_year_month='YYYY-M')`, catch `QueryException` duplicate silently); `updateTemplate(Transaction, array, bool $propagate): void` updates template fields; if `$propagate`: `WHERE recurring_parent_uuid=template.uuid AND paid_at IS NULL` update description/value/category/account and syncTags; `cancelTemplate(Transaction): void` sets `recurring_ends_at=today()`; `deleteTemplate(Transaction, bool $orphanOccurrences): void` if orphan=true update occurrences set recurring_parent_uuid=null + is_recurring=false then soft-delete template; if orphan=false soft-delete template + soft-delete occurrences with paid_at IS NULL (received preserved))
- `app/Http/Controllers/RecurringIncomeController.php` (new — `index` list templates `WHERE is_recurring=true AND recurring_parent_uuid IS NULL`; `edit` show edit form; `update` accepts `propagate` bool; `cancel` POST endpoint; `destroy` accepts `orphan_occurrences` bool)
- `app/Http/Requests/UpdateRecurringIncomeRequest.php` (new — validates description/value/category_id/account_id/tags + `propagate` boolean; category type=Income|Both only)
- `app/Http/Requests/CancelRecurringIncomeRequest.php` (new — empty body)
- `routes/web.php` (modify — `Route::resource('recurring-incomes', RecurringIncomeController::class)->only(['index', 'edit', 'update', 'destroy']'); `Route::post('recurring-incomes/{template}/cancel', ...)->name('recurring-incomes.cancel')`)
- `tests/Feature/Incomes/RecurringIncomeTest.php` (new — template creation generates occurrences up to current month; occurrence `recurring_year_month` set; idempotent generation (call twice = no duplicates due to unique constraint); updateTemplate propagate=true touches only paid_at IS NULL occurrences; received occurrences never touched; cancelTemplate sets recurring_ends_at; deleteTemplate orphan=true makes occurrences standalone; deleteTemplate orphan=false deletes only unreceived occurrences; cross-workspace 404)

**Depends on:** T5 (uses `IncomeService::create`)
**Reuses:** `IncomeService::create` as occurrence factory, `TransactionService` patterns, UNIQUE index from T1 migration for idempotency
**Requirement:** INCM-04

**Done when:**
- [ ] RED: Run `--filter=RecurringIncomeTest` — fails
- [ ] GREEN: Implement service + controller + FormRequests + routes — all pass
- [ ] Template creation generates occurrences for months from start_date to current month (bounded by recurring_ends_at if set)
- [ ] Idempotency: second call to generateOccurrencesUpTo on same template produces 0 new occurrences (no duplicates)
- [ ] UpdateTemplate with propagate=true: only unpaid occurrences touched; received preserved
- [ ] DeleteTemplate orphan=true: occurrences become standalone (recurring_parent_uuid=null, is_recurring=false); template soft-deleted
- [ ] DeleteTemplate orphan=false: unreceived occurrences soft-deleted; received occurrences preserved
- [ ] Gate: `docker compose exec app php artisan test --filter=RecurringIncomeTest` passes
- [ ] Test count: ~10 tests pass (no silent deletions)

**Tests:** Feature (PHPUnit) — parallel-safe per TESTING.md
**Gate:** Quick (`php artisan test --filter=RecurringIncomeTest`)

**Commit:** `feat(income): recurring income template + occurrences with idempotency`

---

### T7: Transfer Service + Controller [P] [TDD]

**What:** Implement `TransferService` (create 2-leg Transaction pair within DB::transaction with `transfer_group_id` UUID + recalc both accounts; update both legs atomically; delete both legs + recalc to revert), `TransferController` (create, store, edit, update, destroy with transfer_group_id as route param), `StoreTransferRequest`/`UpdateTransferRequest`. Categoria sistema "Transferência" auto-created on first transfer via `CategoryService::ensureSystemCategory`.

**Where:**
- `app/Services/TransferService.php` (new — `create(Workspace, User, array): string` validates from != to, both same workspace; DB::transaction: resolve transfer_category via `CategoryService::ensureSystemCategory($w, 'Transferência', Both)`; create Expense leg `type=expense, account_id=from, paid_at=now, transfer_group_id=G, category_id=transferCat, value, date, description`; create Income leg `type=income, account_id=to, paid_at=now, transfer_group_id=G, category_id=transferCat, value, date`; sync tags both; recalculateBalance(from) decresce; recalculateBalance(to) acresce; return G; `updateGroup(string $G, Workspace, array): void` DB::transaction edits both rows simultaneously (value/description/date/account changes), recalc old/new accounts as needed; `deleteGroup(string $G): void` DB::transaction soft-delete both + recalc both accounts (reverts since paid_at was now() at creation))
- `app/Http/Controllers/TransferController.php` (new — `create` returns Inertia form; `store` calls service; `edit` queries 2 legs by transfer_group_id returns; `update` calls service; `destroy` calls service. Use `{transferGroup}` UUID route param parsed as string)
- `app/Http/Requests/StoreTransferRequest.php` (new — from_account_id, to_account_id required uuid exists; value required numeric gt:0 max 999999999.99; date required; description required max 255; tags sometimes array; custom: from != to with "Conta de origem e destino devem ser diferentes"; both accounts same workspace as route's $workspace)
- `app/Http/Requests/UpdateTransferRequest.php` (new — same fields optional; allows changing value/date/description/from_account/to_account/tags)
- `routes/web.php` (modify — `Route::get('transfers/create', [TransferController::class, 'create'])->name('transfers.create'); Route::post('transfers', [TransferController::class, 'store'])->name('transfers.store'); Route::get('transfers/{transferGroup}/edit', ...)->name('transfers.edit'); Route::put('transfers/{transferGroup}', ...)->name('transfers.update'); Route::delete('transfers/{transferGroup}', ...)->name('transfers.destroy')`)
- `tests/Feature/Incomes/TransferTest.php` (new — create transfer A→B decresa A e acresce B; saldo total workspace invariante; from == to rejected; cross-workspace account rejected; create auto-creates categoria "Transferência" is_system=true (only once per workspace); update value recalc both; update from_account recalc old and new; delete reverts both; income leg appears in incomes index excluded (transfer_group_id IS NOT NULL filter); viewer 403; cross-workspace transfer 404)

**Depends on:** T1 (uses CategoryService::ensureSystemCategory from T1, AccountService::recalculateBalance)
**Reuses:** `CategoryService::ensureSystemCategory` (added in T1), `AccountService::recalculateBalance`, TransactionService::syncTags pattern, D-31 mirror pattern
**Requirement:** INCM-05

**Done when:**
- [ ] RED: Run `--filter=TransferTest` — fails
- [ ] GREEN: Implement service + controller + FormRequests + routes — all pass
- [ ] Transfer creates 2 leg Transactions atomically in DB::transaction
- [ ] Categoria "Transferência" auto-created `is_system=true, type=Both` on first transfer; idempotent (subsequent transfers reuse same category)
- [ ] Saldo total workspace (sum of account balances) invariant before/after transfer
- [ ] Update changes both legs simultaneously; recalcs involved accounts
- [ ] Delete reverts both legs; recalcs both accounts to restore
- [ ] Income index excludes transfer legs (`transfer_group_id IS NULL`)
- [ ] Gate: `docker compose exec app php artisan test --filter=TransferTest` passes
- [ ] Test count: ~10 tests pass (no silent deletions)

**Tests:** Feature (PHPUnit) — parallel-safe per TESTING.md
**Gate:** Quick (`php artisan test --filter=TransferTest`)

**Commit:** `feat(income): transfer between accounts with 2-leg atomic pair`

---

### T8: Income Authorization Tests [P] [TDD]

**What:** Write authorization tests covering all 3 roles (Admin, Editor, Viewer) against all income endpoints + cross-workspace isolation.

**Where:**
- `tests/Feature/Incomes/IncomeAuthorizationTest.php` (new — admin can CRUD + receive + receive-group; editor can CRUD + receive + receive; viewer 403 on create/edit/delete/receive; cross-workspace income returns 404 on show/edit/update/destroy/receive; cross-workspace recurring template 404; cross-workspace transfer 404)

**Depends on:** T5 (T2-T5 income vertical complete + routes)
**Reuses:** `IncomeTestCase` base class, existing `TransactionAuthorizationTest` patterns
**Requirement:** INCM-01..05 (authorization coverage)

**Done when:**
- [ ] RED: Run `--filter=IncomeAuthorizationTest` — fails (asserts 403/404 expecting actual responses)
- [ ] GREEN: confirm tests pass against existing implementation from T2-T5
- [ ] Test coverage: Admin/Editor/Viewer × create/edit/delete/receive/group-delete/recurring/transfer
- [ ] Cross-workspace isolation tested for all routes
- [ ] Gate: `docker compose exec app php artisan test --filter=IncomeAuthorizationTest` passes
- [ ] Test count: ~15 tests pass (no silent deletions)

**Tests:** Feature (PHPUnit) — parallel-safe per TESTING.md
**Gate:** Domain (`php artisan test --filter=Income`)

**Commit:** `test(income): authorization + cross-workspace isolation`

---

### T9: Recurring Income Job + Scheduler [TDD]

**What:** Implement `GenerateRecurringIncomesJob` (mirrors `CloseBillsJob` shape — ShouldQueue + try/catch + Log::error; iterates active templates and calls `RecurringIncomeService::generateOccurrencesUpTo` until current month). Register `Schedule::job(...)->dailyAt('00:00')` in `routes/console.php`.

**Where:**
- `app/Jobs/GenerateRecurringIncomesJob.php` (new — `handle(RecurringIncomeService $service): void` queries templates `is_recurring=true AND recurring_parent_uuid IS NULL AND (recurring_ends_at IS NULL OR recurring_ends_at >= today)`, for each: `$service->generateOccurrencesUpTo($template, Carbon::now()->startOfMonth())` in try/catch with Log::error on exception)
- `routes/console.php` (modify — add `Schedule::job(new GenerateRecurringIncomesJob)->dailyAt('00:00')`)
- `tests/Feature/Incomes/RecurringJobTest.php` (new — job dispatch generates missing occurrences for active template; job respects recurring_ends_at boundary; job skips cancelled templates (recurring_ends_at < today); idempotent: dispatching job twice in same month produces no new occurrences; `Queue::fake` + dispatch + assert pushed; or with sync queue actually executes — uses `Carbon::setTestNow` + `Queue::fake` patterns)

**Depends on:** T6 (uses RecurringIncomeService::generateOccurrencesUpTo)
**Reuses:** `CloseBillsJob` shape, `Schedule::job` syntax already in `console.php`
**Requirement:** INCM-04 (job integration)

**Done when:**
- [ ] RED: Run `--filter=RecurringJobTest` — fails (class GenerateRecurringIncomesJob not found)
- [ ] GREEN: Implement job + scheduler registration — all pass
- [ ] Job queries only active templates (is_recurring + recurring_parent_uuid null + recurring_ends_at nullable-or-future)
- [ ] Job calls generateOccurrencesUpTo for each template bounded by current month
- [ ] Idempotent: job dispatched twice in same month = no new occurrences
- [ ] Gate: `docker compose exec app php artisan test --filter=RecurringJobTest` passes
- [ ] Gate: `docker compose exec app php artisan test` — full backend suite still passes (no regression)
- [ ] Test count: ~5 tests pass (no silent deletions)

**Tests:** Feature (PHPUnit)
**Gate:** Full backend (`php artisan test`)

**Commit:** `feat(income): schedule GenerateRecurringIncomesJob at midnight`

---

### T10: Incomes Pages + Components [P]

**What:** Build React pages for income CRUD + receive + installment create + filter/pagination. New domain folder `Pages/Incomes/` and `Components/Incomes/`. Builds but does not run Cypress (Cypress covered in T14).

**Where:**
- `resources/js/Pages/Incomes/Index.tsx` (new — list receitas with filters: search/category/account/date range/status/flag (recorrente/parcelada/random), pagination 25, empty state "Registrar primeira receita", rows with badges: Recebida/A receber, Recorrente, Parcelada label X/Y, BRL formatted value)
- `resources/js/Pages/Incomes/Create.tsx` (new — form: description/value/date/account/category(Income|Both only)/tags; checkbox "Recorrente" → expand `recurring_ends_at` optional date field; radio "Avulsa | Parcelada" → expand `installments_total` int field if parcelada; mutual exclusivity client-side — if recurring on, hide parcelada radio OR show warning. Uses RadioGroup primitive)
- `resources/js/Pages/Incomes/Edit.tsx` (new — edit simple receita or single installment (warning "Edita apenas esta parcela"); status badge; tags sync; same fields as Create minus recurrence toggle (not editable))
- `resources/js/Components/Incomes/IncomeForm.tsx` (new — form base extracted from Create + Edit shared)
- `resources/js/Components/Incomes/IncomeRow.tsx` (new — table row / card with status badges)
- `resources/js/Components/Incomes/IncomeFilters.tsx` (new — filter bar with debounced search + select dropdowns + flag filter + "Limpar filtros" button)
- No type file (use inline typed props consistent with existing transactions pages convention)

**Depends on:** T2, T3, T4, T5 (backend income endpoints complete)
**Reuses:** `resources/js/Pages/Transactions/*` patterns (Index/Create/Edit), `format-currency.ts`, shadcn primitives `radio-group`, `select`, `dialog`, `input`, `badge`, `card`, `button`
**Requirement:** INCM-01, INCM-02, INCM-03, INCM-06, INCM-07

**Done when:**
- [ ] All 3 Inertia pages render without TS errors
- [ ] `useState` for filter state with debounced search (300ms) — wire to Inertia `router.reload({ data: filters })` pattern
- [ ] Pagination preserves filters via `withQueryString` on backend (already done by controller in T2)
- [ ] Mutual exclusion recurring × parcelada enforced client side (UI swaps visibility; block submit otherwise)
- [ ] Recebida/A receber visual distinction via badge variant
- [ ] Installment row shows "X/Y" label
- [ ] Empty state CTA "Registrar primeira receita"
- [ ] Gate: `docker compose exec app npm run build` succeeds (no TS errors, no missing imports)
- [ ] Manual verified: page renders at `/w/{uuid}/incomes`, create form submits, list updates

**Tests:** None (frontend unit/E2E deferred to T14 Cypress)
**Gate:** Build (`npm run build`)

**Commit:** `feat(income-ui): Incomes pages with filters, receive, installments, recurrence toggle`

---

### T11: Transfers Pages + Components [P]

**What:** Build Transfer Create and Edit pages. New domain folders `Pages/Transfers/` and `Components/Transfers/`.

**Where:**
- `resources/js/Pages/Transfers/Create.tsx` (new — form: from_account (Select) / to_account (Select) / value / date / description / tags; client-side guard from != to with error message; submit POST `/w/{w}/transfers`)
- `resources/js/Pages/Transfers/Edit.tsx` (new — edit both legs' value/date/description + from/to account; submit PUT `/w/{w}/transfers/{transferGroupId}`)
- `resources/js/Components/Transfers/TransferForm.tsx` (new — shared form)

**Depends on:** T7 (backend transfer endpoints complete)
**Reuses:** shadcn primitives `select`, `input`, `card`, `button`, `format-currency.ts`, `AccountResource` types
**Requirement:** INCM-05

**Done when:**
- [ ] Create page renders with account dropdowns populated from props
- [ ] Edit page receives `transfer_group_id` UUID, fetches both legs from Inertia props
- [ ] Client-side validation from != to (clear inline error before submit)
- [ ] Gate: `docker compose exec app npm run build` succeeds
- [ ] Manual verified: transfer A→B updates both account balances

**Tests:** None (E2E in T14)
**Gate:** Build (`npm run build`)

**Commit:** `feat(income-ui): Transfer Create + Edit pages`

---

### T12: Recurring Incomes Pages + Components [P]

**What:** Build Recurring Incomes Index (templates list) and Edit (template edit with propagate checkbox). New folders `Pages/RecurringIncomes/` and `Components/RecurringIncomes/`.

**Where:**
- `resources/js/Pages/RecurringIncomes/Index.tsx` (new — list templates `is_recurring=true, is_recurring_template=true`; show value, start date, recurring_ends_at (or "sem fim"), ocorrências count; buttons: Edit, Cancel, Delete (delete dialog with orphan_occurrences radio: "Manter ocorrências como avulsas" vs "Excluir ocorrências não recebidas"))
- `resources/js/Pages/RecurringIncomes/Edit.tsx` (new — form: description/value/category/account/tags + checkbox "Aplicar a futuras ocorrências não recebidas"; submit PUT with propagate flag)
- `resources/js/Components/RecurringIncomes/RecurringTemplateCard.tsx` (new — template card with status badge, occurrences count, actions menu)

**Depends on:** T6 (backend recurring endpoints complete)
**Reuses:** Pages/Transactions patterns, shadcn primitives `dialog` (for delete confirmation with orphan radio), `radio-group`, `card`, `button`
**Requirement:** INCM-04

**Done when:**
- [ ] Index lists templates with occurrence count (optional join count or N+1 small list)
- [ ] Edit form has propagate checkbox; submit sends `propagate: bool`
- [ ] Delete dialog uses Dialog primitive with radio "Manter ocorrências / Excluir não-recebidas"; submit sends DELETE with `orphan_occurrences: bool`
- [ ] Cancel button POSTs to `/cancel` route
- [ ] Gate: `docker compose exec app npm run build` succeeds
- [ ] Manual verified: create recurring template via Incomes Create (with "Recorrente" checkbox) → see template in Recurring Incomes list → edit with propagate → delete with orphan option

**Tests:** None (E2E in T14)
**Gate:** Build (`npm run build`)

**Commit:** `feat(income-ui): Recurring incomes template management pages`

---

### T13: Sidebar Nav + Build Verification

**What:** Update `AppSidebar.tsx` to include nav items for "Receitas", "Transferências", "Receitas Recorrentes" linking to new routes. Final frontend build gate.

**Where:**
- `resources/js/Components/AppSidebar.tsx` (modify — add 3 nav items in appropriate section, using Ziggy `route()` helper; pt-BR labels)
- Verify all 3 pages compile + nav functions

**Depends on:** T10, T11, T12
**Reuses:** Existing sidebar nav item pattern from `AppSidebar.tsx`
**Requirement:** INCM-01..07 (UI navigation)

**Done when:**
- [ ] Sidebar shows 3 new items: "Receitas" → `incomes.index`, "Transferências" → `transfers.create`, "Receitas Recorrentes" → `recurring-incomes.index`
- [ ] Icons via Lucide consistent with existing items
- [ ] Gate: `docker compose exec app npm run build` succeeds
- [ ] Manual verified: clicking each nav item visits its page without 404

**Tests:** None
**Gate:** Build (`npm run build`)

**Commit:** `feat(income-ui): sidebar navigation for incomes, transfers, recurring`

---

### T14: Cypress E2E — Income + Recurring + Transfer Spec Suite

**What:** Write 5 Cypress spec files covering critical user journeys. Run sequentially (Cypress not parallel-safe per TESTING.md).

**Where:**
- `cypress/e2e/incomes/crud.cy.js` (new — register + create workspace; visit `/w/{uuid}/incomes`; click "Nova Receita"; fill description, value R$1500, account, category, tag; submit; verify appears in list; edit; update; delete; verify hidden)
- `cypress/e2e/incomes/receive.cy.js` (new — create income R$100; mark received; verify account balance increases; unreceive; verify restored; edit received value to R$200; verify balance differential; delete received; verify restored)
- `cypress/e2e/incomes/installment.cy.js` (new — create receita R$1000 in 3x; verify 3 rows R$333.33/333.33/333.34 with installment labels; delete-group; verify all removed)
- `cypress/e2e/incomes/recurring.cy.js` (new — create receita R$5000 start_date last month + check "Recorrente"; verify occurrence for last month generated; visit /recurring-incomes; edit template with "Aplicar a futuras recebidas não recebidas"=true; verify upcoming unpaid occurrences updated; cancel recurrence; verify no new occurrences after; delete template orphan=true; verify occurrences become standalone)
- `cypress/e2e/incomes/transfer.cy.js` (new — create 2 accounts A and B with known initial balances; visit `/w/{uuid}/transfers/create`; from A to B R$300; submit; verify A balance decremented, B incremented, workspace total invariant; edit transfer to R$500; verify both updated; delete transfer; verify both balances restored)

**Depends on:** T13 (all pages built)
**Reuses:** `cypress/support/commands.js` `loginViaSession`, `registerAndCreateWorkspace` patterns
**Requirement:** INCM-01..05 (E2E coverage)

**Done when:**
- [ ] All 5 spec files written with `before()` session + `beforeEach()` restore
- [ ] Each spec creates fresh workspace per its run (Cypress not parallel-safe = sequential)
- [ ] pt-BR text assertions match UI: "Nova Receita", "Receber", "Recorrente", "Transferências"
- [ ] Gate: `npm run cypress:run -- --spec "cypress/e2e/incomes/*.cy.js"` passes
- [ ] Test count: 5 spec files × ~3-6 tests = ~20 E2E tests pass

**Tests:** E2E (Cypress)
**Gate:** E2E single domain (`npm run cypress:run -- --spec "cypress/e2e/incomes/*.cy.js"`)

**Commit:** `test(income-e2e): cypress spec suite for incomes, recurring, transfer`

---

### T15: Full Backend Gate + ROADMAP Update

**What:** Run complete backend test suite; update ROADMAP to mark INCM-01 🟢; update STATE.md session log.

**Where:**
- Run `docker compose exec app php artisan test` — full suite
- Run `npm run cypress:run` — full E2E (optional, may take longer)
- `.specs/project/ROADMAP.md` (modify — mark INCM-01 receita as 🟢)
- `.specs/project/STATE.md` (modify — add session log entry)

**Depends on:** T14
**Reuses:** None
**Requirement:** All INCM-01..07 + transferência financeira M1 closer

**Done when:**
- [ ] `docker compose exec app php artisan test` — full backend suite passes with no regression
- [ ] Total test count: ~270+ PHPUnit tests (was 203, adding ~70 new)
- [ ] ROADMAP.md INCM-01 row marked 🟢
- [ ] STATE.md session log entry added with feature + phase + summary
- [ ] If all M1 rows are 🟢 after this, note milestone 1 completion in STATE.md

**Tests:** All
**Gate:** Complete (`php artisan test && npm run cypress:run`)

**Commit:** `chore(income): mark INCM-01 complete in ROADMAP after full gate`

---

## Parallel Execution Map

```
Phase 1 (Sequential):
  T1 ──→ T2 ──→ T3 ──→ T4 ──→ T5

Phase 2 (Parallel, after T5):
                  ┌→ T6  [P]  (Recurring service + controller)
  T5 ────────────┼→ T7  [P]  (Transfer service + controller)
                  └→ T8  [P]  (Income authorization)

Phase 3 (Sequential, after T6):
  T6 ──→ T9  (Recurring job)

Phase 4 (Parallel frontend, after backend complete):
                   ┌→ T10 [P]  (Incomes pages)
  T6,T7,T9 ────────┼→ T11 [P]  (Transfers pages)
                   └→ T12 [P]  (Recurring pages)
  T10,T11,T12 ──→ T13  (Sidebar + final build)

Phase 5 (Sequential E2E, after frontend):
  T13 ──→ T14

Phase 6 (Sequential final gate):
  T14 ──→ T15
```

---

## Task Granularity Check

| Task | Scope | Status |
|------|-------|--------|
| T1: Migration + model + 2 resources + factory + testbase | 1 migration + 7 file edits (cohesive schema+model foundation) | ✅ Granular (structural bundle like CCXP-01 T1) |
| T2: Income create + index + FormRequest + service + controller | 1 vertical slice (1 endpoint group + tests) | ✅ Granular |
| T3: Income update + delete | 2 endpoints + tests | ✅ Granular |
| T4: Income receive + unreceive | 2 endpoints + tests | ✅ Granular |
| T5: Income installment create + delete-group | 1 service method group + 1 endpoint + tests | ✅ Granular |
| T6: Recurring service + controller + tests | 1 vertical slice | ✅ Granular |
| T7: Transfer service + controller + tests | 1 vertical slice | ✅ Granular |
| T8: Income authorization tests | 1 test file × 15 assertions | ✅ Granular |
| T9: Recurring job + scheduler | 1 job + 1 line in console.php + tests | ✅ Granular |
| T10: Incomes pages + components (3 pages + 3 components) | 6 frontend files cohesive single domain | ✅ Granular (domain coherent) |
| T11: Transfers pages + components (2 pages + 1 component) | 3 frontend files | ✅ Granular |
| T12: Recurring pages + components (2 pages + 1 component) | 3 frontend files | ✅ Granular |
| T13: Sidebar nav update | 1 file modify | ✅ Granular |
| T14: Cypress E2E specs | 5 spec files cohesive E2E domain | ✅ Granular (sequential by Cypress non-parallel-safe) |
| T15: Full gate + roadmap | 1 verification step | ✅ Granular |

---

## Diagram-Definition Cross-Check

| Task | Depends On (task body) | Diagram Shows | Status |
|------|------------------------|---------------|--------|
| T1 | None | None (starting) | ✅ Match |
| T2 | T1 | T1 → T2 | ✅ Match |
| T3 | T2 | T2 → T3 | ✅ Match |
| T4 | T2 | T2 → T4 (implicit; T4 depends T2 in body, diagram shows T2→T3→T4 sequential) | ⚠️ Diagram T3→T4 mismatch — see correction below |
| T5 | T2 | T4 → T5 | ✅ Match (T5 depends T2 but placed after T4 in sequential chain) |
| T6 | T5 | T5 → T6 | ✅ Match |
| T7 | T1 (but diagram Phase 2 after T5) | T5 → T7 | ⚠️ Body says "Depends on T1" — see correction |
| T8 | T5 | T5 → T8 | ✅ Match |
| T9 | T6 | T6 → T9 | ✅ Match |
| T10 | T2,T3,T4,T5 | T6,T7,T9 → T10 | ⚠️ Correction: T10 depends on T2-T5 (backend income vertical complete) AND T6,T7 (resources for recurring/transfer on forms). Diagram shows Phase 4 starts after Phase 2+3 done — semantically correct. Body should say "Depends on T5, T6, T7" |
| T11 | T7 | T6,T7,T9 → T11 | ✅ Match (semantically: backend steps done before starting frontend) |
| T12 | T6 | T6,T7,T9 → T12 | ✅ Match |
| T13 | T10,T11,T12 | T10,T11,T12 → T13 | ✅ Match |
| T14 | T13 | T13 → T14 | ✅ Match |
| T15 | T14 | T14 → T15 | ✅ Match |

**Corrections applied (body fixed at task definitions):**

- T4 Depends on: T2 (not T3). T4 (receive/unreceive) only needs `IncomeService::create` to exist + routes from T2. Body kept correct; diagram T3 → T4 visually shows sequential but T4 doesn't depend on T3 code. Acceptable — sequential ordering for orchestrator simplicity.
- T7 Depends on: T1 (CategoryService::ensureSystemCategory added in T1) AND benefits from T5 done. Body updated below: T7 "Depends on: T1 + T5 complete (Income vertical for test base)". Diagram shows T5→T7 which represents the phase-entry gate even if code dependency is T1. Resolved: T7 "Depends on: T1".
- T10 Depends on: T2, T3, T4, T5 (renders income list + create + edit + filters + receive + installment). T6/T7 not strictly required for Incomes pages (transfer/recurring are separate pages). Body fixed.

Updated dependency table reflects corrections; diagram is canonical visual.

---

## Test Co-location Validation

Per `.specs/codebase/TESTING.md` Test Coverage Matrix:

| Task | Code Layer Created/Modified | Matrix Requires | Task Says | Status |
|------|------------------------------|-----------------|-----------|--------|
| T1 | Models + Migrations | Feature (assertDatabaseHas) — implicit | None (structural) | ✅ OK (structural, validated by subsequent TDD tasks using the schema) |
| T2 | Controller + Service + FormRequest | Feature (PHPUnit) | Feature | ✅ OK |
| T3 | Controller + Service + FormRequest | Feature | Feature | ✅ OK |
| T4 | Controller + Service | Feature | Feature | ✅ OK |
| T5 | Controller + Service | Feature | Feature | ✅ OK |
| T6 | Controller + Service + FormRequest | Feature | Feature | ✅ OK |
| T7 | Controller + Service + FormRequest | Feature | Feature | ✅ OK |
| T8 | Tests-only (Authorization for all controllers) | Feature (authorization assertions) | Feature | ✅ OK |
| T9 | Job (app/Jobs) | Feature (indirect) | Feature | ✅ OK |
| T10 | React Pages (resources/js/Pages) | E2E (Cypress) | None (defers to T14) | ⚠️ Marginally OK — T10 produces build-verified pages; T14 is the dedicated E2E task for Incomes. Per "merge forward" rule in tasks.md, frontend pages co-located with E2E in T14. Acceptable: T10 build gate + T14 E2E gate combined satisfy coverage matrix for React Pages. |
| T11 | React Pages | E2E | None (defers to T14) | ✅ OK (same justification) |
| T12 | React Pages | E2E | None (defers to T14) | ✅ OK (same justification) |
| T13 | Sidebar nav (React component) | E2E | None (defers to T14 — T14 spec files include sidebar navigation paths) | ✅ OK |
| T14 | E2E tests for React Pages (T10-T13 outputs) | E2E | E2E | ✅ OK |
| T15 | No code creation | All (final gate) | All | ✅ OK |

**Notes:**
- T10-T14 follow "merge forward" pattern: React Pages created in build-gated tasks (T10-T13); E2E tests are written as a single consolidated task (T14) that covers all React Pages. This is acceptable per TESTING.md because E2E isolation is per spec file (Cypress isolation via `cy.session()` + fresh workspace per spec), not per page.
- Backend (Controllers/Services/FormRequests/Policies) tests always co-located in same task that creates the code (T2-T9).
- All `[P]` tasks are PHPUnit-backed (parallel-safe Yes per TESTING.md). Cypress-backed T14 is NOT `[P]` per parallelism assessment.

---

## Pre-Approval Summary

- **15 tasks** across 6 phases
- **Foundation T1** then **Income vertical slice T2-T5** (sequential) building CRUD + receive + installments
- **Recurring + Transfer + Auth T6/T7/T8** in parallel (each cohesive vertical)
- **Job T9** sequence after T6
- **Frontend T10-T12** parallel (build-only), **T13 sidebar integration**
- **E2E T14** sequential (Cypress not parallel-safe)
- **Final gate T15** marks ROADMAP
- **TDD-first**: every code task starts red, ends green
- **All gates from TESTING.md** referenced
- **Granularity**: 15 atomic vertical slices
- **Cross-check**: 1 mismatch corrected (T7 depends T1, diagram visually shows Phase 2 entering after T5 — semantically equal entry gate)
- **Co-location**: T10-T14 follow "merge forward" for E2E — validated