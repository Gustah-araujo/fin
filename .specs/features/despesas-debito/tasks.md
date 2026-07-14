# Despesas em Débito — Tasks

**Design:** `.specs/features/despesas-debito/design.md`
**Spec:** `.specs/features/despesas-debito/spec.md`
**Status:** Draft

---

## Execution Plan

```
Phase 1: Foundation (sequential)
  T1 ──→ T2 ──→ T3

Phase 2: HTTP Layer (sequential)
  T3 ──→ T4

Phase 3: Backend Verification (parallel, after T4)
            ┌→ T5 [P]
  T4 ──→ T6 [P]
            └→ T7 [P]

Phase 4: Frontend (parallel, after T4)
            ┌→ T8 [P]
  T4 ──→ T9 [P]

Phase 5: E2E (sequential, after T5-T9)
  T5, T6, T7, T8, T9 ──→ T10
```

---

## Task Breakdown

### T1: Create Migration, Model, Factory, Account Relation

**What:** Create `transactions` table migration, Transaction Eloquent model, TransactionFactory, and `Account::transactions()` HasMany relation.
**Where:**
- `database/migrations/xxxx_xx_xx_xxxxxx_create_transactions_table.php` (new)
- `app/Models/Transaction.php` (new)
- `database/factories/TransactionFactory.php` (new)
- `app/Models/Account.php` (modify — add `transactions()` relation)

**Depends on:** None
**Reuses:** Account, Category model patterns (uuid, SoftDeletes, casts, getRouteKeyName)
**Requirement:** DEBT-01

**Done when:**
- [ ] Migration creates `transactions` table with columns: uuid (unique), workspace_id (FK workspaces, cascade), account_id (FK accounts, nullable), category_id (FK categories), type (string, default 'expense'), description (string 255), value (decimal 15,2), date (date), paid_at (timestamp nullable), created_by (FK users), timestamps, soft_deletes
- [ ] Transaction model has: `$fillable`, `casts()` (type→TransactionType, value→decimal, date→date, paid_at→datetime), `getRouteKeyName()→uuid`, SoftDeletes + HasFactory traits
- [ ] Transaction model relationships: `workspace()`, `account()`, `category()`, `creator()`, `tags()` morphToMany
- [ ] TransactionFactory generates valid data for all required fields
- [ ] Account model has `hasMany(Transaction::class)` relation
- [ ] Gate check: `php artisan migrate:fresh --seed` succeeds
- [ ] Gate check: `php artisan test --filter=Transaction` finds no tests yet (baseline: 0 tests)

**Tests:** None (structural — tested implicitly by T5-T7)
**Gate:** None

---

### T2: Upgrade AccountService::recalculateBalance

**What:** Replace stub implementation with real balance calculation summing paid transactions.
**Where:**
- `app/Services/AccountService.php` (modify — `recalculateBalance()` method)
- `tests/Feature/Transactions/AccountBalanceRecalculationTest.php` (new)

**Depends on:** T1
**Reuses:** AccountService existing class, TestCase pattern from AccountCreationTest
**Requirement:** DEBT-02, DEBT-02 AC1, DEBT-02 AC2

**Done when:**
- [ ] `recalculateBalance()` computes `current_balance = initial_balance + sum(paid_income) - sum(paid_expenses)` using account's transactions relation
- [ ] Returns early if account has no transactions (balance = initial_balance)
- [ ] Test: creates account + inserts paid expense directly via factory → recalculateBalance → balance = initial - expense value
- [ ] Test: creates account + inserts paid income (type=income) directly → recalculateBalance → balance = initial + income
- [ ] Test: creates account + multiple paid expenses → recalculateBalance → balance = initial - sum
- [ ] Test: different accounts are independent (pay expense on A → only A's balance changes)
- [ ] Test: formula holds: `current_balance = initial_balance + sum(paid_income) - sum(paid_expenses)`
- [ ] Gate check: `php artisan test --filter=AccountBalanceRecalculationTest` — 5 tests pass

**Tests:** Feature
**Gate:** `php artisan test --filter=AccountBalanceRecalculationTest`

---

### T3: TransactionService, FormRequests, Policy, Resource, Tag Morph

**What:** Create business logic layer: TransactionService, StoreTransactionRequest, UpdateTransactionRequest, TransactionPolicy, TransactionResource, and Tag `morphedByMany`.
**Where:**
- `app/Services/TransactionService.php` (new)
- `app/Http/Requests/StoreTransactionRequest.php` (new)
- `app/Http/Requests/UpdateTransactionRequest.php` (new)
- `app/Policies/TransactionPolicy.php` (new)
- `app/Http/Resources/TransactionResource.php` (new)
- `app/Models/Tag.php` (modify — add `morphedByMany(Transaction::class, 'taggable')`)

**Depends on:** T2 (needs AccountService for balance recalculation in service)
**Reuses:** AccountService::recalculateBalance(), StoreAccountRequest pattern (messages, rules), AccountPolicy pattern (hasWriteAccess, isAdmin), AccountResource pattern (whenLoaded), Tag model
**Requirement:** DEBT-01, DEBT-02

**Done when:**
- [ ] TransactionService::create(workspace, user, data) creates transaction with paid_at=null, syncs tags
- [ ] TransactionService::update(transaction, data) updates fields, syncs tags, recalculates balance if paid status/account/value changed
- [ ] TransactionService::pay(transaction) sets paid_at=now(), calls recalculateBalance on account
- [ ] TransactionService::unpay(transaction) sets paid_at=null, calls recalculateBalance on account
- [ ] TransactionService::archive(transaction) recalculates balance if paid, then soft-deletes
- [ ] TransactionService::syncTags(transaction, tagUuids) syncs polymorphic taggables
- [ ] All balance-affecting operations (pay, unpay, paid-update, paid-delete) wrapped in `DB::transaction()`
- [ ] StoreTransactionRequest: validates description (required, string, max:255), value (required, numeric, gt:0, max:999999999.99), date (required, date), account_id (required, exists:accounts), category_id (required, exists:categories), tags (optional array), tags.* (exists:tags,uuid)
- [ ] StoreTransactionRequest: after() hook validates account belongs to workspace, category belongs to workspace, category type is Expense or Both
- [ ] StoreTransactionRequest: pt-BR messages for all validations
- [ ] UpdateTransactionRequest: same rules but all `sometimes`, includes `paid_at` (nullable, date)
- [ ] TransactionPolicy: viewAny (member), create (admin/editor), update (admin/editor), delete (admin only)
- [ ] TransactionResource: returns uuid, description, value (float), type, date, paid_at (ISO), account (whenLoaded), category (whenLoaded), tags (whenLoaded collection), created_by (whenLoaded), created_at (ISO)
- [ ] Tag model has `transactions()` morphToMany relation
- [ ] Gate check: `php artisan optimize:clear` clears caches (no syntax errors)

**Tests:** None (tested implicitly by T5-T7 feature tests; service logic verified through HTTP layer)
**Gate:** `php artisan optimize:clear`

---

### T4: TransactionController and Routes

**What:** Create TransactionController with resource CRUD + pay/unpay methods, register routes in web.php.
**Where:**
- `app/Http/Controllers/TransactionController.php` (new)
- `routes/web.php` (modify — add resource + 2 custom routes)
- `app/Http/Controllers/Controller.php` (modify — import if needed)

**Depends on:** T3
**Reuses:** AccountController pattern (authorize → service → redirect), WorkspaceMemberController custom routes pattern (pay/unpay)
**Requirement:** DEBT-01, DEBT-02

**Done when:**
- [ ] `index(Workspace $workspace)`: authorize viewAny, eager-load transactions with account/category/tags, filter by query params (search, category, account, from_date, to_date, status), paginate 25 with query string, return Inertia with transactions/accounts/categories as Resources
- [ ] `create(Workspace $workspace)`: authorize create, load accounts + categories (expense/both) + tags, return Inertia
- [ ] `store(StoreTransactionRequest, Workspace, TransactionService)`: authorize create, service.create(), redirect to index
- [ ] `edit(Workspace, Transaction)`: abort_if workspace mismatch → 404, authorize update, load transaction + accounts/categories/tags, return Inertia
- [ ] `update(UpdateTransactionRequest, Workspace, Transaction, TransactionService)`: abort_if 404, authorize update, service.update(), redirect to index
- [ ] `destroy(Workspace, Transaction, TransactionService)`: abort_if 404, authorize delete, service.archive(), redirect to index
- [ ] `pay(Workspace, Transaction, TransactionService)`: abort_if 404, authorize update, service.pay(), redirect back
- [ ] `unpay(Workspace, Transaction, TransactionService)`: abort_if 404, authorize update, service.unpay(), redirect back
- [ ] Routes: `Route::resource('transactions', TransactionController::class)` inside w/{workspace} group
- [ ] Routes: `Route::post('transactions/{transaction}/pay', [...])` named `transactions.pay`
- [ ] Routes: `Route::post('transactions/{transaction}/unpay', [...])` named `transactions.unpay`
- [ ] Gate check: `php artisan route:list | grep transactions` shows all 9 routes (index, create, store, show, edit, update, destroy, pay, unpay)

**Tests:** None (tested by T5-T7 — tests become runnable in the next task group per "merge forward" rule)
**Gate:** `php artisan route:list --name=transactions`

---

### T5: TransactionCreationTest, TransactionUpdateTest, TransactionDeletionTest [P]

**What:** PHPUnit feature tests for create, update, delete flows including validation and edge cases.
**Where:**
- `tests/Feature/Transactions/TransactionCreationTest.php` (new)
- `tests/Feature/Transactions/TransactionUpdateTest.php` (new)
- `tests/Feature/Transactions/TransactionDeletionTest.php` (new)

**Depends on:** T4
**Reuses:** AccountCreationTest, AccountUpdateTest, AccountDeletionTest patterns
**Requirement:** DEBT-01

**Done when:**
- [ ] TransactionCreationTest: `test_user_can_create_transaction` — POST store → redirect → assertDatabaseHas with paid_at=null
- [ ] TransactionCreationTest: `test_validation_errors_on_create` — empty POST → assertSessionHasErrors
- [ ] TransactionCreationTest: `test_transaction_with_zero_value_is_rejected` — value=0 → errors
- [ ] TransactionCreationTest: `test_transaction_with_negative_value_is_rejected` — value=-50 → errors
- [ ] TransactionCreationTest: `test_transaction_with_future_date_is_accepted` — date tomorrow → ok
- [ ] TransactionCreationTest: `test_transaction_with_income_only_category_is_rejected` — type=Income category → errors
- [ ] TransactionCreationTest: `test_transaction_account_must_belong_to_same_workspace` → validation error
- [ ] TransactionCreationTest: `test_transaction_stores_tags_correctly` — POST with tags → taggables has records
- [ ] TransactionCreationTest: `test_transaction_list_shows_correct_data` — GET index → assertInertia has N transactions
- [ ] TransactionCreationTest: `test_empty_transaction_list` — GET index with no data → assertInertia has 0
- [ ] TransactionUpdateTest: `test_user_can_update_transaction` — PUT → redirect → assertDatabaseHas updated fields
- [ ] TransactionUpdateTest: `test_updating_transaction_syncs_tags` — PUT with new tags → old replaced
- [ ] TransactionUpdateTest: `test_updating_paid_transaction_value_recalculates_balance` — value changed on paid → balance diff correct
- [ ] TransactionUpdateTest: `test_moving_paid_transaction_recalculates_both_accounts` — account changed → old restored, new deducted
- [ ] TransactionDeletionTest: `test_user_can_soft_delete_transaction` — DELETE → assertSoftDeleted
- [ ] TransactionDeletionTest: `test_deleting_paid_transaction_restores_balance` — delete paid → balance restored
- [ ] TransactionDeletionTest: `test_soft_deleted_transaction_not_in_list` — after delete → assertInertia has 0
- [ ] Gate check: `php artisan test --filter=TransactionCreationTest` — 10 tests pass
- [ ] Gate check: `php artisan test --filter=TransactionUpdateTest` — 4 tests pass
- [ ] Gate check: `php artisan test --filter=TransactionDeletionTest` — 3 tests pass
- [ ] Total: 17 tests pass

**Tests:** Feature
**Gate:** `php artisan test --filter="TransactionCreationTest|TransactionUpdateTest|TransactionDeletionTest"`

---

### T6: TransactionPaymentTest + AccountBalanceRecalculationTest [P]

**What:** PHPUnit feature tests for pay/unpay flows, balance integrity, idempotency.
**Where:**
- `tests/Feature/Transactions/TransactionPaymentTest.php` (new)
- `tests/Feature/Transactions/AccountBalanceRecalculationTest.php` (new — only if not already created in T2; fallback: add remaining balance tests)

**Depends on:** T4
**Reuses:** AccountCreationTest patterns, DB assertions
**Requirement:** DEBT-02

**Done when:**
- [ ] TransactionPaymentTest: `test_user_can_pay_transaction` — POST pay → paid_at set, balance decreased
- [ ] TransactionPaymentTest: `test_user_can_unpay_transaction` — POST unpay → paid_at null, balance restored
- [ ] TransactionPaymentTest: `test_paying_already_paid_transaction_is_idempotent` — no double deduction
- [ ] TransactionPaymentTest: `test_unpaying_unpaid_transaction_is_idempotent` — no error
- [ ] TransactionPaymentTest: `test_payment_changes_only_target_account` — other accounts unchanged
- [ ] TransactionPaymentTest: `test_pay_and_unpay_toggles_correctly` — full cycle: pay → down → unpay → back
- [ ] TransactionPaymentTest: `test_editor_can_pay_and_unpay` — editor role → 200 OK
- [ ] TransactionPaymentTest: `test_viewer_cannot_pay_transaction` — viewer → 403
- [ ] TransactionPaymentTest: `test_viewer_cannot_unpay_transaction` — viewer → 403
- [ ] AccountBalanceRecalculationTest: if T2 created, verify existing 5 tests still pass (no regression)
- [ ] AccountBalanceRecalculationTest: `test_balance_matches_formula_with_multiple_transactions` — 3 paid + mixed = correct
- [ ] Gate check: `php artisan test --filter=TransactionPaymentTest` — 9 tests pass
- [ ] Gate check: `php artisan test --filter=AccountBalanceRecalculationTest` — 6 tests pass (5 from T2 + 1 new)

**Tests:** Feature
**Gate:** `php artisan test --filter="TransactionPaymentTest|AccountBalanceRecalculationTest"`

---

### T7: TransactionAuthorizationTest + TransactionFilteringTest [P]

**What:** PHPUnit feature tests for authorization (role enforcement, cross-workspace) and server-side filtering/pagination.
**Where:**
- `tests/Feature/Transactions/TransactionAuthorizationTest.php` (new)
- `tests/Feature/Transactions/TransactionFilteringTest.php` (new)

**Depends on:** T4
**Reuses:** AccountAuthorizationTest patterns, Inertia assertion patterns
**Requirement:** DEBT-01 (AC6, AC7), DEBT-03, DEBT-04

**Done when:**
- [ ] TransactionAuthorizationTest: `test_viewer_cannot_create_transaction` → 403
- [ ] TransactionAuthorizationTest: `test_viewer_cannot_update_transaction` → 403
- [ ] TransactionAuthorizationTest: `test_viewer_cannot_delete_transaction` → 403
- [ ] TransactionAuthorizationTest: `test_editor_can_create_transaction` → 200/redirect
- [ ] TransactionAuthorizationTest: `test_editor_can_update_transaction` → 200/redirect
- [ ] TransactionAuthorizationTest: `test_editor_cannot_delete_transaction` → 403 (admin only)
- [ ] TransactionAuthorizationTest: `test_cannot_access_transaction_from_other_workspace` → 404
- [ ] TransactionFilteringTest: `test_can_search_transactions_by_description` → ?search= → only matches
- [ ] TransactionFilteringTest: `test_can_filter_transactions_by_category` → ?category=X → only that category
- [ ] TransactionFilteringTest: `test_can_filter_transactions_by_account` → ?account=X → only that account
- [ ] TransactionFilteringTest: `test_can_filter_transactions_by_date_range` → from/to → only in range
- [ ] TransactionFilteringTest: `test_can_filter_by_paid_status` → ?status=paid/unpaid → correctly filtered
- [ ] TransactionFilteringTest: `test_filters_combine_with_and_logic` → multiple params → intersection
- [ ] TransactionFilteringTest: `test_transaction_list_paginates_at_25` → seed 30 → page 1 has 25
- [ ] TransactionFilteringTest: `test_pagination_preserves_filters` → filter → page 2 → filter maintained
- [ ] Gate check: `php artisan test --filter=TransactionAuthorizationTest` — 7 tests pass
- [ ] Gate check: `php artisan test --filter=TransactionFilteringTest` — 8 tests pass

**Tests:** Feature
**Gate:** `php artisan test --filter="TransactionAuthorizationTest|TransactionFilteringTest"`

---

### T8: Transactions/Index.tsx [P]

**What:** Create the transaction list page with filter bar, transaction cards, pagination, and inline pay/unpay/delete actions.
**Where:** `resources/js/Pages/Transactions/Index.tsx` (new)
**Reuses:** Accounts/Index.tsx pattern (AuthenticatedLayout, header, empty state, useForm for mutations), Categories/Index.tsx grouped sections pattern, formatCurrency(), Badge, Select, Input, Button, Card components
**Requirement:** DEBT-01 (AC2, AC5, AC6), DEBT-02 (AC1, AC2, AC6), DEBT-03, DEBT-04

**Props:** `transactions: PaginatedCollection<Transaction>`, `accounts: Account[]`, `categories: Category[]`

**Done when:**
- [ ] Page renders inside AuthenticatedLayout
- [ ] Header shows "Despesas" with count and "Nova Despesa" button (Link to create)
- [ ] Filter bar: search Input (debounced 300ms), category Select, account Select, date range (from/to Input[type=date]), status Select (all/paid/unpaid)
- [ ] Filters update URL query params via `router.get()` preserving existing params
- [ ] Active filter count badge + "Limpar filtros" button visible when filters active
- [ ] Transaction list: one Card per transaction with:
  - Paid status icon (✓ emerald-600 for paid, ○ amber-600 for unpaid)
  - Description (bold), value (right-aligned, BRL, semibold)
  - Date, account name, paid date (if paid)
  - Category name with color dot, tag chips (Badge with tag color)
  - For paid: `opacity-75` Card style, "Pago em DD/MM" meta text
  - Action buttons: [Pagar]/[Desmarcar] (primary/outline), [Editar] (outline Link), [Excluir] (destructive)
- [ ] Pay/Unpay: `useForm().post()` to pay/unpay routes → `onSuccess: router.reload()`
- [ ] Delete: `useForm().delete()` → `onSuccess: router.reload()`
- [ ] Pagination: rendered from Laravel paginator `links` data (prev/next + page numbers)
- [ ] Empty state: dashed Card with "Nenhuma despesa registrada" + CTA
- [ ] TypeScript strict: all types defined, no `any` casts

**Tests:** None (tested by Cypress in T10; E2E verifies UI behavior)
**Gate:** `npm run build` (Vite production build succeeds — verifies TS compilation + no import errors)

---

### T9: Transactions/Create.tsx and Transactions/Edit.tsx [P]

**What:** Create the transaction create and edit form pages.
**Where:**
- `resources/js/Pages/Transactions/Create.tsx` (new)
- `resources/js/Pages/Transactions/Edit.tsx` (new)

**Reuses:** Accounts/Create.tsx and Edit.tsx patterns (AuthenticatedLayout, Card form wrapper, useForm, Label+Input+Select, button row), formatCurrency
**Requirement:** DEBT-01 (AC1, AC3)

**Done when:**
- [ ] Create.tsx renders inside AuthenticatedLayout with header "Nova Despesa"
- [ ] Create form fields: description (text Input), value (number Input step=0.01), date (date Input, default today), account (Select from accounts prop), category (Select from categories filtered to expense/both), tags (multi-select from tags prop)
- [ ] Create form: validation errors displayed below each field (red text)
- [ ] Create form: submit via `useForm().post()` to `route('transactions.store')` → redirect to index
- [ ] Create form: "Cancelar" button → Link to index
- [ ] Edit.tsx renders inside AuthenticatedLayout with header "Editar Despesa"
- [ ] Edit form: same fields as Create, pre-filled from `transaction` prop
- [ ] Edit form: if transaction is paid, show warning text: "Esta despesa já foi paga. Alterar o valor ou conta recalculará o saldo."
- [ ] Edit form: submit via `useForm().put()` to `route('transactions.update')` → redirect to index
- [ ] Both pages: TypeScript strict, no `any` casts
- [ ] Both pages: pt-BR labels and placeholders

**Tests:** None (tested by Cypress in T10)
**Gate:** `npm run build`

---

### T10: Cypress E2E Transactions Spec

**What:** End-to-end browser tests for the full transaction CRUD + payment + filtering user journey.
**Where:** `cypress/e2e/transactions/crud.cy.js` (new)
**Reuses:** `cypress/e2e/accounts/crud.cy.js` and `categories/crud.cy.js` patterns (cy.loginViaSession, workspace creation in before(), card selectors)

**Depends on:** T5, T6, T7, T8, T9
**Requirement:** DEBT-01, DEBT-02, DEBT-03

**Done when:**
- [ ] `before()` hook: creates workspace via `cy.loginViaSession('transactions-session')`
- [ ] Test: `shows transactions index page` — visits /w/{uuid}/transactions, sees "Despesas"
- [ ] Test: `creates a transaction` — fills form, submits, sees transaction in list
- [ ] Test: `shows validation errors on create` — submits empty form, sees error messages
- [ ] Test: `edits a transaction` — clicks "Editar", modifies description, saves, sees update
- [ ] Test: `pays a transaction` — clicks "Pagar", transaction changes to paid style (muted)
- [ ] Test: `unpays a transaction` — clicks "Desmarcar", transaction returns to unpaid style
- [ ] Test: `deletes a transaction` — creates then deletes, verifies removed from list
- [ ] Test: `filters transactions by search` — types in search, list filters to matches
- [ ] Test: `filters transactions by status` — selects "Pago"/"Pendente", list updates
- [ ] Test: `creates transaction with tags` — selects tags, submits, tag chips visible
- [ ] Gate check: `npx cypress run --spec "cypress/e2e/transactions/crud.cy.js"` — all 10 tests pass

**Tests:** E2E
**Gate:** `npx cypress run --spec "cypress/e2e/transactions/crud.cy.js"`

---

## Parallel Execution Map

```
Phase 1 (Sequential):
  T1 ──→ T2 ──→ T3
  (migration → service → business logic)

Phase 2 (Sequential):
  T3 ──→ T4
  (business logic → HTTP layer)

Phase 3 (Parallel — 3 sub-agents):
  T4 complete, then:
    ├── T5 [P] (Creation + Update + Deletion tests — 17 tests)
    ├── T6 [P] (Payment + Balance tests — 15 tests)
    └── T7 [P] (Authorization + Filtering tests — 15 tests)

Phase 4 (Parallel — 2 sub-agents, can overlap with Phase 3):
  T4 complete, then:
    ├── T8 [P] (Index.tsx page)
    └── T9 [P] (Create + Edit pages)

Phase 5 (Sequential):
  T5, T6, T7, T8, T9 complete, then:
    T10 (Cypress E2E — needs backend tests passing + frontend rendered)
```

---

## Requirement Traceability

| Task | DEBT-01 | DEBT-02 | DEBT-03 | DEBT-04 |
|------|---------|---------|---------|---------|
| T1 | AC1 (schema) | — | — | — |
| T2 | — | AC1, AC2 (balance engine) | — | — |
| T3 | AC1 (create), AC3 (update), AC4 (delete) | AC3, AC4, AC5 (paid mutations) | — | — |
| T4 | AC1-AC7 (HTTP endpoints) | AC1, AC2 (pay/unpay routes) | AC1-AC5 (filter query params) | AC1 (paginate) |
| T5 | AC1-AC5 (CRUD tests) | AC3, AC4, AC5 (paid update/delete) | — | — |
| T6 | — | AC1, AC2, AC3, AC4, AC5, AC7 (payment tests) | — | — |
| T7 | AC6, AC7 (auth/isolation) | — | AC1-AC6 (filter tests) | AC1, AC2 (pagination tests) |
| T8 | AC2, AC5, AC6 (UI list) | AC1, AC2, AC6 (pay/unpay UI) | AC1-AC6 (filter UI) | AC1, AC2 (pagination UI) |
| T9 | AC1, AC3 (create/edit forms) | — | — | — |
| T10 | AC1-AC4 (E2E journey) | AC1, AC2 (E2E payment) | AC1, AC2, AC5 (E2E filters) | — |

---

## Task Granularity Check

| Task | Scope | Files | Status |
|------|-------|-------|--------|
| T1 | Migration + Model + Factory + relation | 4 files (1 new migration, 2 new, 1 modify) | ⚠️ Cohesive (all structural foundation for same model) |
| T2 | One method upgrade + its tests | 2 files (1 modify, 1 new) | ✅ Granular |
| T3 | Service + 2 Requests + Policy + Resource + Tag morph | 6 files (5 new, 1 modify) | ⚠️ Cohesive (all business logic for same feature, no HTTP layer) |
| T4 | Controller + routes | 2 files (1 new, 1 modify) | ✅ Granular |
| T5 | 3 test files (Creation, Update, Deletion) | 3 files (new) | ✅ Granular per domain |
| T6 | 1-2 test files (Payment, Balance) | 2 files (new) | ✅ Granular |
| T7 | 2 test files (Authorization, Filtering) | 2 files (new) | ✅ Granular |
| T8 | 1 page component | 1 file (new) | ✅ Granular |
| T9 | 2 page components | 2 files (new) | ⚠️ Acceptable (Create+Edit share same form pattern) |
| T10 | 1 Cypress spec | 1 file (new) | ✅ Granular |

**⚠️ Notes:**
- T1: 4 files but all tightly coupled (model can't exist without migration; factory tests model; relation is 1 line). Splitting would create artificial dependencies with no benefit.
- T3: 6 files but all in same layer (business logic). Splitting FormRequests from Service would require the controller task to stitch them — more touchpoints, more failure modes.
- T9: Create+Edit share identical form structure. Single sub-agent handles both efficiently.

---

## Diagram-Definition Cross-Check

| Task | Depends On (body) | Diagram Shows | Status |
|------|-------------------|---------------|--------|
| T1 | None | No incoming arrows | ✅ Match |
| T2 | T1 | T1 → T2 | ✅ Match |
| T3 | T2 | T2 → T3 | ✅ Match |
| T4 | T3 | T3 → T4 | ✅ Match |
| T5 | T4 | T4 → T5 [P] | ✅ Match |
| T6 | T4 | T4 → T6 [P] | ✅ Match |
| T7 | T4 | T4 → T7 [P] | ✅ Match |
| T8 | T4 | T4 → T8 [P] | ✅ Match |
| T9 | T4 | T4 → T9 [P] | ✅ Match |
| T10 | T5, T6, T7, T8, T9 | All → T10 | ✅ Match |

No mismatches. T5-T9 are all parallel (depend only on T4). None depend on each other.

---

## Test Co-location Validation

Since no TESTING.md exists, the project test strategy is inferred from existing patterns:
- **Backend code** → PHPUnit Feature Tests (HTTP-level, tests full controller→service→DB stack)
- **Frontend pages** → Cypress E2E (browser-level, tests full user journey)

| Task | Code Layer | Required Test | Task Says | Status |
|------|-----------|---------------|-----------|--------|
| T1 | Model, Migration | Feature (PHPUnit) | None (merge-forward) | ⚠️ Deferred to T5 |
| T2 | Service method | Feature (PHPUnit) | Feature (co-located) | ✅ OK |
| T3 | Service, Requests, Policy, Resource | Feature (PHPUnit) | None (merge-forward) | ⚠️ Deferred to T5-T7 |
| T4 | Controller, Routes | Feature (PHPUnit) | None (merge-forward) | ⚠️ Deferred to T5-T7 |
| T5 | Tests | Feature | Feature | ✅ OK |
| T6 | Tests | Feature | Feature | ✅ OK |
| T7 | Tests | Feature | Feature | ✅ OK |
| T8 | Frontend page | E2E (Cypress) | None (merge-forward) | ⚠️ Deferred to T10 |
| T9 | Frontend pages | E2E (Cypress) | None (merge-forward) | ⚠️ Deferred to T10 |
| T10 | E2E tests | E2E | E2E | ✅ OK |

**Merge-forward justification:** T1, T3, T4 create code that PHPUnit feature tests can only test AFTER T4 completes (controller must exist for HTTP-level tests to run). T10 tests T8-T9 after T10 completes. Tests are in the earliest task where they become executable. No code leaves the pipeline unverified — the gate at T5-T7 and T10 catches all regressions.
