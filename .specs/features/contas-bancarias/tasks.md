# Contas Bancárias — Tasks

**Design**: `.specs/features/contas-bancarias/design.md`
**Spec**: `.specs/features/contas-bancarias/spec.md`
**Status**: Draft

---

## Execution Plan

### Phase 1: Prerequisites (Parallel OK)

```
T1 [P]  ──┐
           ├──→ T3
T2 [P]  ──┘
```

### Phase 2: Data Layer (Sequential)

```
T3 → T4
```

### Phase 3: Backend Core + Creation (Sequential)

T4 writes the test first, then builds the full backend stack:
enum → migration → model → factory → resource → policy → service → form request → controller → routes → test passes.

```
T4
```

### Phase 4: Remaining Backend Tests (Parallel OK)

T4's controller and service have all methods. These tasks add form requests (new files) and test files. No shared file modifications.

```
      ┌→ T5 [P]
T4 ──┼→ T6 [P]
      └→ T7 [P]
```

### Phase 5: Frontend Pages (Parallel OK)

All pages depend on T4 (routes exist). No shared files.

```
      ┌→ T8 [P]
T4 ──┼→ T9 [P]
      └→ T10 [P]
```

### Phase 6: E2E + Integration (Sequential)

E2E needs all frontend rendered. Sidebar update piggybacks on routes.

```
T8, T9, T10 → T11 → T12
```

---

## Task Breakdown

### T1: Install shadcn Select [P]

**What**: Install `@/components/ui/select.tsx` via shadcn CLI for account type dropdown
**Where**: `resources/js/components/ui/select.tsx`
**Depends on**: None
**Reuses**: Existing shadcn pattern (components in `components/ui/`)
**Requirement**: ACCT-01 (UI dependency)

**Done when**:
- [ ] `npx shadcn-ui@latest add select` succeeds
- [ ] `components/ui/select.tsx` exists
- [ ] No TypeScript compile errors: `npx tsc --noEmit`

**Tests**: none
**Gate**: `npx tsc --noEmit`

---

### T2: Share workspace in Inertia [P]

**What**: Add current workspace to `HandleInertiaRequests::share()` so all pages and the sidebar have workspace context (uuid, name, user's role)
**Where**: `app/Http/Middleware/HandleInertiaRequests.php`
**Depends on**: None
**Reuses**: Existing `$shared["auth"]` and `$shared["workspaces"]` pattern
**Requirement**: ACCT-01 (prerequisite for all workspace-scoped features)

**Done when**:
- [ ] `HandleInertiaRequests::share()` resolves `{workspace}` from route params, loads model, wraps in WorkspaceResource
- [ ] Shared data includes `workspace: { uuid, name, role }` when inside `w/{workspace}` routes
- [ ] Shared data includes `workspace: null` for non-workspace routes (guest, auth-only)
- [ ] Existing `Workspace/Members` page still works (relies on explicit `workspace` prop)
- [ ] Gate: `php artisan test --filter=WorkspaceCreationTest` — existing tests still pass

**Tests**: none (existing tests serve as regression)
**Gate**: `php artisan test --filter=WorkspaceCreationTest`

---

### T3: Data Layer Foundation

**What**: Create AccountType enum, accounts migration, Account model, AccountFactory, AccountResource
**Where**: 
- `app/Enums/AccountType.php`
- `database/migrations/XXXX_create_accounts_table.php`
- `app/Models/Account.php`
- `database/factories/AccountFactory.php`
- `app/Http/Resources/AccountResource.php`
**Depends on**: T2 (workspace FK on migration)
**Reuses**: Workspace model pattern (HasFactory, getRouteKeyName), WorkspaceFactory pattern (Str::orderedUuid), WorkspaceResource pattern (JsonResource)
**Requirement**: ACCT-01, ACCT-01.1

**Done when**:
- [ ] `AccountType` enum exists with `Checking`, `Savings`, `Investment` backed by string
- [ ] Migration creates `accounts` table with all columns per design (uuid unique, workspace_id FK, created_by FK nullable, name, type, initial_balance decimal, current_balance decimal, deleted_at, timestamps)
- [ ] Migration runs without error: `php artisan migrate:fresh`
- [ ] `Account` model has `HasFactory`, `SoftDeletes`, `$fillable`, `getRouteKeyName()` → `"uuid"`
- [ ] `Account` model has `belongsTo(Workspace::class)`, `belongsTo(User::class, 'created_by')`
- [ ] `AccountFactory` generates valid accounts with uuid, name, type, balances
- [ ] `AccountResource` returns `{ uuid, name, type, initial_balance, current_balance, created_at }`

**Tests**: none (data layer; tested implicitly via feature tests in T4-T7)
**Gate**: `php artisan migrate:fresh && php artisan tinker --execute="echo App\Models\Account::factory()->make()->toJson();"`

---

### T4: Backend Creation + AccountCreationTest (TDD)

**What**: Write AccountCreationTest first (5 tests fail), then build the complete backend stack to make all creation tests pass.
**Where**:
- `tests/Feature/Accounts/AccountCreationTest.php`
- `app/Http/Requests/StoreAccountRequest.php`
- `app/Policies/AccountPolicy.php`
- `app/Services/AccountService.php`
- `app/Http/Controllers/AccountController.php`
- `routes/web.php` (modify — add resource route)
**Depends on**: T3 (model, factory, migration exist)
**Reuses**: WorkspacePolicy role-check pattern, WorkspaceService UUID pattern, WorkspaceController resource pattern
**Requirement**: ACCT-01 (creation + list acceptance criteria)

**TDD sequence**:
1. Write `AccountCreationTest` with 5 test methods (all fail — no routes)
2. Create `StoreAccountRequest` (name, type, initial_balance rules + pt-BR messages)
3. Create `AccountPolicy` with all methods: `viewAny`, `create`, `update`, `delete`
4. Create `AccountService` with all methods: `create`, `update`, `recalculateBalance`, `archive`
5. Create `AccountController` with all methods: `index`, `create`, `store`, `edit`, `update`, `destroy`
   - `store()` authorizes via Policy, calls `AccountService::create()`, redirects to index
   - `index()` authorizes via Policy, returns Inertia page with paginated accounts via AccountResource
6. Register `Route::resource('accounts', AccountController::class)` in web.php
7. Run `AccountCreationTest` — all 5 tests pass
8. Run full test suite to verify no regressions

**Done when**:
- [ ] `AccountCreationTest` passes: `php artisan test --filter=AccountCreationTest`
  - `test_user_can_create_account` → 302, DB has account
  - `test_validation_errors_on_create` → 302 with session errors
  - `test_account_list_shows_balances` → 200 Inertia, 2 accounts with values
  - `test_account_with_zero_balance_is_accepted` → 302, balance = 0
  - `test_account_with_negative_balance_is_accepted` → 302, balance = -500
- [ ] All existing tests still pass: `php artisan test`
- [ ] Controller uses `$this->authorize()` before service calls
- [ ] Service generates UUID via `Str::orderedUuid()`

**Tests**: feature (AccountCreationTest — 5 tests)
**Gate**: `php artisan test --filter=AccountCreationTest` (5 pass, 0 fail)

---

### T5: Account Update + AccountUpdateTest (TDD) [P]

**What**: Write AccountUpdateTest first (2 tests fail — UpdateAccountRequest missing), then create the form request and verify tests pass.
**Where**:
- `tests/Feature/Accounts/AccountUpdateTest.php`
- `app/Http/Requests/UpdateAccountRequest.php`
**Depends on**: T4 (AccountController, AccountService, routes, policy exist)
**Reuses**: AccountFactory from T3, AccountService::update from T4, existing StoreAccountRequest pattern
**Requirement**: ACCT-01.3 (edit acceptance criteria)

**TDD sequence**:
1. Write `AccountUpdateTest` with 2 test methods (fail — UpdateAccountRequest doesn't exist)
2. Create `UpdateAccountRequest` (name sometimes, type sometimes, initial_balance sometimes + pt-BR messages)
3. Run tests — all pass (AccountController::edit, update already exist from T4)

**Done when**:
- [ ] `AccountUpdateTest` passes: `php artisan test --filter=AccountUpdateTest`
  - `test_user_can_update_account` → 302, DB updated
  - `test_cannot_update_account_with_invalid_type` → 422 validation error
- [ ] Gate: `php artisan test --filter=AccountUpdateTest` (2 pass, 0 fail)

**Tests**: feature (AccountUpdateTest — 2 tests)
**Gate**: `php artisan test --filter=AccountUpdateTest`

---

### T6: Account Deletion + AccountDeletionTest (TDD) [P]

**What**: Write AccountDeletionTest first (tests fail — hasTransactions logic not in service), then implement hasTransactions check in AccountService.
**Where**:
- `tests/Feature/Accounts/AccountDeletionTest.php`
- `app/Services/AccountService.php` (modify — enhance `archive()` method)
**Depends on**: T4 (AccountController, AccountService, routes exist)
**Reuses**: AccountFactory from T3, AccountService::archive from T4
**Requirement**: ACCT-01.4 (delete/archive acceptance criteria)

**TDD sequence**:
1. Write `AccountDeletionTest` with 2 test methods (fail — archive with transactions not implemented)
2. Enhance `AccountService::archive()` with `hasTransactions()` check:
   - Currently always returns false (no transaction tables exist yet)
   - Stub: check for a `has_transactions` column or always allow hard-delete for now
   - Add `SoftDeletes` — deleted_at is set regardless
3. Run tests — all pass

**Done when**:
- [ ] `AccountDeletionTest` passes: `php artisan test --filter=AccountDeletionTest`
  - `test_user_can_hard_delete_account` → 302, account removed from DB
  - `test_account_with_transactions_is_soft_deleted` → 302, deleted_at set, still in DB
- [ ] Gate: `php artisan test --filter=AccountDeletionTest` (2 pass, 0 fail)

**Tests**: feature (AccountDeletionTest — 2 tests)
**Gate**: `php artisan test --filter=AccountDeletionTest`

---

### T7: Account Authorization + AccountAuthorizationTest (TDD) [P]

**What**: Write AccountAuthorizationTest first (4 tests may pass or fail depending on Policy implementation completeness), ensure all authorization gates are correct.
**Where**: `tests/Feature/Accounts/AccountAuthorizationTest.php`
**Depends on**: T4 (AccountPolicy, routes exist)
**Reuses**: AccountFactory from T3, WorkspacePolicy role helpers pattern
**Requirement**: ACCT-01 (authorization edge cases: viewer restrictions, cross-workspace isolation)

**Done when**:
- [ ] `AccountAuthorizationTest` passes: `php artisan test --filter=AccountAuthorizationTest`
  - `test_viewer_cannot_create_account` → 403
  - `test_viewer_cannot_update_account` → 403
  - `test_viewer_cannot_delete_account` → 403
  - `test_cannot_access_account_from_other_workspace` → 404
- [ ] Gate: `php artisan test --filter=AccountAuthorizationTest` (4 pass, 0 fail)

**Tests**: feature (AccountAuthorizationTest — 4 tests)
**Gate**: `php artisan test --filter=AccountAuthorizationTest`

---

### T8: Accounts Index Page [P]

**What**: Build the account list page with card grid, empty state, and navigation header
**Where**: `resources/js/Pages/Accounts/Index.tsx`
**Depends on**: T4 (routes + AccountController::index returns Inertia props)
**Reuses**: `AuthenticatedLayout`, `Card/CardContent/CardHeader/CardTitle`, `Badge`, `Button`, Home.tsx grid layout pattern
**Requirement**: ACCT-01.2 (list with BRL balance)

**Done when**:
- [ ] Page wraps in `AuthenticatedLayout`
- [ ] Typed Props: `{ accounts: { uuid, name, type, initial_balance, current_balance, created_at }[] }`
- [ ] Responsive grid: 1 col mobile, 2 col tablet, 3 col desktop
- [ ] Each card shows: account name, type badge (Corrente/Poupança/Investimento with colors), current balance in BRL
- [ ] Empty state: "Nenhuma conta cadastrada" with ghost card + CTA button "Criar primeira conta"
- [ ] Header: "Contas" title + "Nova Conta" button → `route('accounts.create', { workspace })`
- [ ] Each card has "Editar" link → `route('accounts.edit', { workspace, account })`
- [ ] Each card has "Excluir" button with `useForm().delete()`
- [ ] Type badge colors: checking = blue, savings = emerald, investment = amber (matching financial convention)
- [ ] Balance formatted as BRL: `R$ X.XXX,XX`
- [ ] Gate: `npx tsc --noEmit` (no TypeScript errors)

**Tests**: none (tested via E2E in T11)
**Gate**: `npx tsc --noEmit`

---

### T9: Accounts Create Page [P]

**What**: Build the account creation form
**Where**: `resources/js/Pages/Accounts/Create.tsx`
**Depends on**: T4 (routes exist)
**Reuses**: `AuthenticatedLayout`, `Card/CardContent/CardHeader/CardTitle`, `Input`, `Label`, `Button`, `Select` (from T1), `useForm()`
**Requirement**: ACCT-01.1 (create acceptance criteria)

**Done when**:
- [ ] Page wraps in `AuthenticatedLayout`
- [ ] Form uses `useForm({ name: '', type: 'checking', initial_balance: '' })`
- [ ] Fields: name (Input), type (Select with 3 options in pt-BR), initial_balance (Input type="number" step="0.01")
- [ ] Labels in pt-BR: "Nome da Conta", "Tipo da Conta", "Saldo Inicial"
- [ ] Submit calls `form.post(route('accounts.store', { workspace }))`
- [ ] Cancel link → `route('accounts.index', { workspace })`
- [ ] Inline errors shown below each field via `form.errors.name`, etc.
- [ ] Submit button disabled during `form.processing`
- [ ] Gate: `npx tsc --noEmit`

**Tests**: none (tested via E2E in T11)
**Gate**: `npx tsc --noEmit`

---

### T10: Accounts Edit Page [P]

**What**: Build the account edit form (same structure as Create, pre-populated)
**Where**: `resources/js/Pages/Accounts/Edit.tsx`
**Depends on**: T4 (routes exist), T9 (copy form pattern)
**Reuses**: `AuthenticatedLayout`, same form components as T9, `useForm()` with initial values from props
**Requirement**: ACCT-01.3 (edit acceptance criteria)

**Done when**:
- [ ] Page wraps in `AuthenticatedLayout`
- [ ] Typed Props: `{ account: { uuid, name, type, initial_balance, current_balance } }`
- [ ] Form pre-populated from `account` prop: `useForm({ name: account.name, type: account.type, initial_balance: String(account.initial_balance) })`
- [ ] Same form structure as Create (name, type Select, initial_balance)
- [ ] Submit calls `form.put(route('accounts.update', { workspace, account }))`
- [ ] Cancel link → `route('accounts.index', { workspace })`
- [ ] Gate: `npx tsc --noEmit`

**Tests**: none (tested via E2E in T11)
**Gate**: `npx tsc --noEmit`

---

### T11: Cypress E2E — Account CRUD Journey

**What**: Write end-to-end test covering the full create → view → edit → delete user journey
**Where**: `cypress/e2e/accounts/crud.cy.js`
**Depends on**: T8, T9, T10 (all pages rendered)
**Reuses**: Existing Cypress patterns (cy.visit, cy.get, cy.contains, describe/it)
**Requirement**: ACCT-01 (full CRUD acceptance criteria)

**Done when**:
- [ ] Test: "creates an account and sees it in the list"
  - Login as admin → nav to /w/{workspace}/accounts → click "Nova Conta" → fill form → submit → redirected to list → new account visible
- [ ] Test: "shows validation errors on empty submission"
  - Nav to create → submit empty → error messages visible
- [ ] Test: "edits an existing account"
  - On list → click "Editar" → change name → submit → updated name on list
- [ ] Test: "deletes an account"
  - On list → click "Excluir" → account removed from DOM
- [ ] Gate: `npx cypress run --spec "cypress/e2e/accounts/crud.cy.js"` (4 tests pass, 0 fail)

**Tests**: e2e (this is the test file itself — 4 test scenarios)
**Gate**: `npx cypress run --spec "cypress/e2e/accounts/crud.cy.js"`

---

### T12: Sidebar Navigation Update

**What**: Update AppSidebar "Contas" link to use workspace-scoped `route()` helper
**Where**: `resources/js/Components/AppSidebar.tsx` (modify)
**Depends on**: T2 (workspace shared), T4 (route exists), T11 (verified via E2E navigation)
**Reuses**: Existing nav item pattern, `usePage().props.workspace`
**Requirement**: ACCT-01 (sidebar navigation to accounts)

**Done when**:
- [ ] Import `usePage` and access `workspace.uuid` from shared props
- [ ] Replace hardcoded `href: '/accounts'` with `route('accounts.index', { workspace: workspaceUuid })`
- [ ] Sidebar click navigates to correct workspace-scoped URL
- [ ] Gate: `npx tsc --noEmit` + manual navigation smoke test

**Tests**: none (verified manually + covered by E2E nav in T11)
**Gate**: `npx tsc --noEmit`

---

## Parallel Execution Map

```
Phase 1 (Parallel):
  T1 [P] ──┐  Install shadcn Select
           ├──→ (both complete) → T3
  T2 [P] ──┘  Share workspace in Inertia

Phase 2 (Sequential):
  T3 → T4
  Data layer → Backend core + tests

Phase 3 (Parallel, after T4):
  T4 complete, then:
    ├── T5 [P]  Update tests + form request (new file only)
    ├── T6 [P]  Delete tests (modifies AccountService minimally)
    ├── T7 [P]  Auth tests (new file only)
    ├── T8 [P]  Index page (new file)
    ├── T9 [P]  Create page (new file)
    └── T10 [P] Edit page (new file)

Phase 4 (Sequential):
  T8, T9, T10 complete → T11 (E2E needs all pages)
  T11 complete → T12 (sidebar verified by E2E)

Total: 12 tasks
Max parallel workers: 6 (T5-T10 in phase 3)
```

---

## Task Granularity Check

| Task | Scope | Status |
|------|-------|--------|
| T1: Install shadcn Select | 1 CLI command | ✅ Granular |
| T2: Share workspace in Inertia | 1 file modified | ✅ Granular |
| T3: Data Layer Foundation | 5 files (enum, migration, model, factory, resource) — coupled, can't split | ⚠️ Cohesive unit |
| T4: Backend Core + Creation Test | 6 files + test — minimum stack for feature test to pass | ⚠️ Vertical slice |
| T5: Update + UpdateTest | 2 files (1 new form request + 1 test) | ✅ Granular |
| T6: Deletion + DeletionTest | 2 files (1 modified service + 1 test) | ✅ Granular |
| T7: Authorization + AuthTest | 1 file (test only) | ✅ Granular |
| T8: Index Page | 1 component | ✅ Granular |
| T9: Create Page | 1 component | ✅ Granular |
| T10: Edit Page | 1 component | ✅ Granular |
| T11: Cypress E2E | 1 test file | ✅ Granular |
| T12: Sidebar Update | 1 file modified | ✅ Granular |

T3 and T4 are the largest tasks but are cohesive units that can't be split without breaking the TDD cycle (test needs model + migration + service + controller + routes to pass).

---

## Diagram-Definition Cross-Check

| Task | Depends On (body) | Diagram Shows | Status |
|------|--------------------|---------------|--------|
| T1 | None | None → T1 | ✅ Match |
| T2 | None | None → T2 | ✅ Match |
| T3 | T2 | T1, T2 complete → T3 | ✅ Match |
| T4 | T3 | T3 → T4 | ✅ Match |
| T5 | T4 | T4 → T5 [P] | ✅ Match |
| T6 | T4 | T4 → T6 [P] | ✅ Match |
| T7 | T4 | T4 → T7 [P] | ✅ Match |
| T8 | T4 | T4 → T8 [P] | ✅ Match |
| T9 | T4 | T4 → T9 [P] | ✅ Match |
| T10 | T4 | T4 → T10 [P] | ✅ Match |
| T11 | T8, T9, T10 | T8,T9,T10 → T11 | ✅ Match |
| T12 | T2, T4, T11 | T11 → T12 | ✅ Match |

All dependencies match. No conflicts.

---

## Test Co-location Validation

| Task | Code Layer | Test Type Required | Task Says | Status |
|------|-----------|-------------------|-----------|--------|
| T1 | UI component (select) | none (shadcn, tested in E2E) | none | ✅ OK |
| T2 | Middleware | none (existing tests serve) | none | ✅ OK |
| T3 | Enum, Model, Migration | feature (tested in T4-T7) | none | ⚠️ Deferred to T4 |
| T4 | Controller, Service, Request, Policy | feature | feature | ✅ OK |
| T5 | FormRequest | feature | feature | ✅ OK |
| T6 | Service (enhancement) | feature | feature | ✅ OK |
| T7 | Policy (verify) | feature | feature | ✅ OK |
| T8 | Page component | e2e (tested in T11) | none | ⚠️ Deferred to T11 |
| T9 | Page component | e2e (tested in T11) | none | ⚠️ Deferred to T11 |
| T10 | Page component | e2e (tested in T11) | none | ⚠️ Deferred to T11 |
| T11 | E2E test file | e2e | e2e | ✅ OK |
| T12 | Navigation (sidebar) | e2e (tested in T11) | none | ⚠️ Deferred to T11 |

**Resolution for deferred tests:** T3's data layer is tested by T4's feature tests (AccountCreationTest uses the model/factory). T8-T10 frontend pages are tested by T11's E2E test. T12 is covered by T11's navigation. These deferrals are intentional — the code can't be tested in the task that creates it because the test infrastructure doesn't exist yet. The merge-forward strategy places tests in the earliest task where they become runnable.

---

## Commit Plan

| Task | Commit Message |
|------|---------------|
| T1 | `chore: install shadcn Select component` |
| T2 | `feat(infra): share current workspace in Inertia shared data` |
| T3 | `feat(accounts): add AccountType enum, migration, model, factory, resource` |
| T4 | `feat(accounts): add account creation with backend and feature tests` |
| T5 | `feat(accounts): add account update with validation and tests` |
| T6 | `feat(accounts): add account deletion with soft-delete support` |
| T7 | `test(accounts): add authorization tests for role-based access` |
| T8 | `feat(accounts): add account list page with card grid` |
| T9 | `feat(accounts): add account creation form page` |
| T10 | `feat(accounts): add account edit form page` |
| T11 | `test(e2e): add Cypress E2E tests for account CRUD` |
| T12 | `feat(accounts): wire sidebar accounts link to workspace-scoped route` |
