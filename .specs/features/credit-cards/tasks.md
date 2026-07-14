# Cartões de Crédito Tasks

**Design**: `.specs/features/credit-cards/design.md`
**Status**: Draft

---

## Execution Plan

### Phase 1: Foundation (Sequential)

Model + migration + factory + workspace relation. Everything else depends on these.

```
T1 ──→ T2
```

### Phase 2: Backend Building Blocks (Parallel)

After T1+T2, these are independent files that can be created simultaneously.

```
      ┌→ T3 [P] ┐
T2 ──┼→ T4 [P] ┼──→ T7
      ├→ T5 [P] ┤
      └→ T6 [P] ┘
```

### Phase 3: Controller + Routes + Backend Tests (Sequential)

Wires everything together. Controller depends on all building blocks. Tests verify the full HTTP stack.

```
T7
```

### Phase 4: Frontend Pages (Parallel)

After routes exist (T7), the three page files are independent and can be created simultaneously.

```
      ┌→ T8 [P] ┐
T7 ──┼→ T9 [P] ┼──→ T11
      └→ T10[P] ┘
```

### Phase 5: E2E Tests (Sequential)

Cypress spec tests the full user journey across all pages.

```
T11
```

---

## Task Breakdown

### T1: Create CreditCard Migration + Model

**What**: Create the `credit_cards` table migration and the `CreditCard` Eloquent model with UUIDs, casts, and relations.
**Where**: `database/migrations/2026_07_14_000002_create_credit_cards_table.php`, `app/Models/CreditCard.php`
**Depends on**: None
**Reuses**: `database/migrations/2026_07_13_184442_create_accounts_table.php` (migration structure), `app/Models/Account.php` (model structure)
**Requirement**: CARD-01 (index display), CARD-07 (available_limit field), CARD-08 (available_limit in resource)

**Done when**:
- [ ] Migration creates `credit_cards` table with: id, uuid (unique), workspace_id (FK cascade), created_by (FK nullable nullOnDelete), name, credit_limit (decimal 15,2 default 0), available_limit (decimal 15,2 default 0), closing_day (tinyInteger), due_day (tinyInteger), softDeletes, timestamps
- [ ] Model uses `HasFactory`, `SoftDeletes` traits
- [ ] Model fillable: uuid, workspace_id, created_by, name, credit_limit, available_limit, closing_day, due_day
- [ ] Model casts: credit_limit → decimal:2, available_limit → decimal:2, closing_day → integer, due_day → integer
- [ ] Model `getRouteKeyName()` returns 'uuid'
- [ ] Model has `workspace(): BelongsTo` and `creator(): BelongsTo`
- [ ] Migration runs: `php artisan migrate` succeeds
- [ ] Test count: existing tests still pass (no silent deletions)

**Tests**: none (model layer — tested via feature tests in T7)
**Gate**: build (`php artisan migrate`)
**Commit**: `feat(cards): add credit_cards migration and CreditCard model`

---

### T2: Create CreditCardFactory + Add Workspace Relation

**What**: Create the factory for tests and add `creditCards(): HasMany` relation to the Workspace model.
**Where**: `database/factories/CreditCardFactory.php`, `app/Models/Workspace.php` (edit)
**Depends on**: T1
**Reuses**: `database/factories/AccountFactory.php` (factory pattern), `app/Models/Workspace.php` existing `accounts()` relation
**Requirement**: CARD-01 (workspace has cards)

**Done when**:
- [ ] Factory definition: uuid (Str::orderedUuid), name (fake company), credit_limit (randomFloat 1000-50000), available_limit (closure returning credit_limit), closing_day (numberBetween 1-28), due_day (numberBetween 1-28)
- [ ] Factory `$model = CreditCard::class`
- [ ] Workspace model has `creditCards(): HasMany` method
- [ ] `php artisan migrate:fresh` succeeds with factory
- [ ] Test count: existing tests still pass

**Tests**: none (factory + relation — tested via feature tests in T7)
**Gate**: build (`php artisan migrate:fresh`)
**Commit**: `feat(cards): add CreditCard factory and workspace relation`

---

### T3: Create StoreCardRequest + UpdateCardRequest [P]

**What**: Create FormRequests for card creation and update with validation rules.
**Where**: `app/Http/Requests/StoreCardRequest.php`, `app/Http/Requests/UpdateCardRequest.php`
**Depends on**: T1, T2
**Reuses**: `app/Http/Requests/StoreAccountRequest.php`, `app/Http/Requests/UpdateAccountRequest.php`
**Requirement**: CARD-03 (store validation), CARD-04 (update validation)

**Done when**:
- [ ] StoreCardRequest rules: name (required string max:255), credit_limit (required numeric min:0), closing_day (required integer between:1,31), due_day (required integer between:1,31)
- [ ] StoreCardRequest messages: pt-BR, same style as StoreAccountRequest
- [ ] UpdateCardRequest rules: same fields with `sometimes` prefix
- [ ] UpdateCardRequest messages: pt-BR
- [ ] No PHP syntax errors: `php artisan route:list` succeeds

**Tests**: none (FormRequests — validated via feature tests in T7)
**Gate**: build (`php artisan route:list`)
**Commit**: `feat(cards): add StoreCardRequest and UpdateCardRequest`

---

### T4: Create CreditCardResource [P]

**What**: Create the ApiResource for serializing CreditCard to the frontend.
**Where**: `app/Http/Resources/CreditCardResource.php`
**Depends on**: T1, T2
**Reuses**: `app/Http/Resources/AccountResource.php`
**Requirement**: CARD-01, CARD-08 (available_limit exposed)

**Done when**:
- [ ] Resource outputs: uuid, name, credit_limit (float), available_limit (float), closing_day (int), due_day (int), created_at (ISO string)
- [ ] Follows same JsonResource pattern as AccountResource
- [ ] No PHP syntax errors

**Tests**: none (Resource — tested via feature tests in T7)
**Gate**: build (`php artisan route:list`)
**Commit**: `feat(cards): add CreditCardResource`

---

### T5: Create CreditCardPolicy [P]

**What**: Create the authorization policy for credit cards.
**Where**: `app/Policies/CreditCardPolicy.php`
**Depends on**: T1, T2
**Reuses**: `app/Policies/AccountPolicy.php` (verbatim copy with class name change)
**Requirement**: CARD-02 (viewer can see), CARD-06 (viewer cannot mutate)

**Done when**:
- [ ] `viewAny`: any workspace member (members()->where exists)
- [ ] `create`: admin or editor (hasWriteAccess)
- [ ] `update`: admin or editor
- [ ] `delete`: admin only
- [ ] Private helpers: `hasWriteAccess`, `isAdmin`, `getUserRole` — same as AccountPolicy
- [ ] No PHP syntax errors

**Tests**: none (Policy — tested via feature tests in T7)
**Gate**: build (`php artisan route:list`)
**Commit**: `feat(cards): add CreditCardPolicy`

---

### T6: Create CreditCardService [P]

**What**: Create the service class with create, update, recalculateAvailableLimit, and archive methods.
**Where**: `app/Services/CreditCardService.php`
**Depends on**: T1, T2
**Reuses**: `app/Services/AccountService.php` (create/update/archive pattern)
**Requirement**: CARD-07 (available_limit = credit_limit on create), CARD-09 (recalculate on credit_limit update), CARD-10 (public recalculate method for CCXP-01)

**Done when**:
- [ ] `create(Workspace, User, array): CreditCard` — sets uuid, workspace_id, created_by, name, credit_limit, available_limit = credit_limit, closing_day, due_day
- [ ] `update(CreditCard, array): CreditCard` — updates fields; if credit_limit changes, calls recalculateAvailableLimit
- [ ] `recalculateAvailableLimit(CreditCard): void` — PUBLIC method; available_limit = credit_limit - sum of open card expenses (in CARD-01, no expenses table, so = credit_limit)
- [ ] `archive(CreditCard): void` — soft-deletes card
- [ ] No PHP syntax errors

**Tests**: none (Service — tested via feature tests in T7)
**Gate**: build (`php artisan route:list`)
**Commit**: `feat(cards): add CreditCardService`

---

### T7: Create CreditCardController + Routes + PHPUnit Feature Tests

**What**: Create the resource controller, register routes in web.php, and write all 5 PHPUnit feature test files (24 tests total) verifying the full HTTP stack.
**Where**: `app/Http/Controllers/CreditCardController.php` (new), `routes/web.php` (edit), `tests/Feature/Cards/CardCreationTest.php`, `tests/Feature/Cards/CardUpdateTest.php`, `tests/Feature/Cards/CardAuthorizationTest.php`, `tests/Feature/Cards/CardDeletionTest.php`, `tests/Feature/Cards/CardListTest.php` (5 new test files)
**Depends on**: T3, T4, T5, T6
**Reuses**: `app/Http/Controllers/AccountController.php` (controller pattern), `routes/web.php` existing resource registration, `tests/Feature/Accounts/AccountCreationTest.php` (test pattern), `tests/Feature/Accounts/AccountAuthorizationTest.php`, `tests/Feature/Accounts/AccountDeletionTest.php`, `tests/Feature/Accounts/AccountUpdateTest.php`
**Requirement**: CARD-01 through CARD-10 (all requirements verified)

**Done when**:
- [ ] Controller has index, create, store, edit, update, destroy methods
- [ ] Each method: `$this->authorize(...)` + `abort_if($card->workspace_id !== $workspace->id, 404)` where applicable
- [ ] index returns Inertia `Cards/Index` with `cards` prop (CreditCardResource::collection)
- [ ] create returns Inertia `Cards/Create`
- [ ] store redirects to `cards.index`
- [ ] edit returns Inertia `Cards/Edit` with `card` prop (new CreditCardResource)
- [ ] update redirects to `cards.index`
- [ ] destroy redirects to `cards.index`
- [ ] Routes: `Route::resource('cards', CreditCardController::class)` added in workspace prefix group
- [ ] CardCreationTest: 7 tests (create, available_limit=credit_limit, validation, closing_day range, due_day range, negative limit, zero limit)
- [ ] CardUpdateTest: 4 tests (update name, update days, recalculate available_limit on credit_limit change, validation)
- [ ] CardAuthorizationTest: 9 tests (viewer can see, viewer 403 on create/update/delete, editor can create/update, editor 403 on delete, cross-workspace 404, non-member 403)
- [ ] CardDeletionTest: 2 tests (soft-delete, not in list)
- [ ] CardListTest: 2 tests (all fields displayed, empty state)
- [ ] Gate check passes: `php artisan test --filter=Card`
- [ ] Test count: 24 tests pass (no silent deletions)

**Tests**: feature (PHPUnit)
**Gate**: full (`php artisan test --filter=Card`)
**Commit**: `feat(cards): add CreditCardController, routes, and feature tests`

---

### T8: Create Cards/Index Page + Update AppSidebar [P]

**What**: Create the credit cards index page (card grid with limit, available, closing/due days) and wire the sidebar nav link to the real route.
**Where**: `resources/js/Pages/Cards/Index.tsx` (new), `resources/js/Components/AppSidebar.tsx` (edit)
**Depends on**: T7
**Reuses**: `resources/js/Pages/Accounts/Index.tsx` (grid layout pattern), `resources/js/Components/AppSidebar.tsx` existing nav items pattern, `resources/js/lib/format-currency.ts`
**Requirement**: CARD-01 (index display), CARD-02 (viewer sees list without actions), CARD-08 (available_limit display)

**Done when**:
- [ ] Index.tsx renders AuthenticatedLayout with cards grid
- [ ] Each card shows: name (CardTitle), credit_limit (formatCurrency), available_limit (formatCurrency), closing_day badge, due_day badge
- [ ] Editar link and Excluir button present (same pattern as Accounts/Index)
- [ ] Empty state: dashed card with "Nenhum cartão cadastrado" + CTA
- [ ] Inline `CreditCard` TypeScript interface defined in file
- [ ] AppSidebar line 78: `/credit-cards` replaced with conditional `route('cards.index', { workspace: workspaceUuid })` (same pattern as accounts/categories/tags nav items)
- [ ] Page renders without TypeScript errors: `npm run build` succeeds
- [ ] Test count: existing tests still pass

**Tests**: none (frontend page — E2E tested in T11 via merge-forward)
**Gate**: build (`npm run build`)
**Commit**: `feat(cards): add cards index page and wire sidebar nav`

---

### T9: Create Cards/Create Page [P]

**What**: Create the credit card creation form page.
**Where**: `resources/js/Pages/Cards/Create.tsx`
**Depends on**: T7
**Reuses**: `resources/js/Pages/Accounts/Create.tsx` (form layout pattern)
**Requirement**: CARD-03 (create form)

**Done when**:
- [ ] Form uses `useForm` with fields: name (string), credit_limit (string), closing_day (string), due_day (string)
- [ ] Form posts to `route('cards.store', { workspace: workspace.uuid })`
- [ ] Fields: #name (Input text), #credit_limit (Input number step 0.01), #closing_day (Input number min 1 max 31), #due_day (Input number min 1 max 31)
- [ ] Submit button "Criar Cartão" + Cancelar link
- [ ] Error messages rendered for each field (form.errors.{field})
- [ ] Page renders without TypeScript errors: `npm run build` succeeds
- [ ] Test count: existing tests still pass

**Tests**: none (frontend page — E2E tested in T11 via merge-forward)
**Gate**: build (`npm run build`)
**Commit**: `feat(cards): add cards create page`

---

### T10: Create Cards/Edit Page [P]

**What**: Create the credit card edit form page.
**Where**: `resources/js/Pages/Cards/Edit.tsx`
**Depends on**: T7
**Reuses**: `resources/js/Pages/Accounts/Edit.tsx` (edit form pattern)
**Requirement**: CARD-04 (edit form)

**Done when**:
- [ ] Props: `{ card: CreditCard }` with inline interface
- [ ] Form uses `useForm` pre-populated with card data: name, credit_limit, closing_day, due_day
- [ ] Form puts to `route('cards.update', { workspace: workspace.uuid, card: card.uuid })`
- [ ] Same field IDs as Create (#name, #credit_limit, #closing_day, #due_day)
- [ ] Submit button "Salvar" + Cancelar link
- [ ] Error messages rendered for each field
- [ ] Page renders without TypeScript errors: `npm run build` succeeds
- [ ] Test count: existing tests still pass

**Tests**: none (frontend page — E2E tested in T11 via merge-forward)
**Gate**: build (`npm run build`)
**Commit**: `feat(cards): add cards edit page`

---

### T11: Write Cypress E2E Test

**What**: Write the Cypress E2E spec testing the full credit card CRUD user journey (index, create, validation, edit, update limit, delete).
**Where**: `cypress/e2e/cards/crud.cy.js`
**Depends on**: T8, T9, T10
**Reuses**: `cypress/e2e/accounts/crud.cy.js` (spec structure), `cypress/support/commands.js` (`cy.loginViaSession`)
**Requirement**: CARD-01, CARD-03, CARD-04, CARD-05, CARD-07, CARD-09

**Done when**:
- [ ] Spec uses `cy.loginViaSession('cards-session')` in before() + beforeEach()
- [ ] Creates workspace in before() hook, extracts workspaceUuid from URL
- [ ] `shows cards index page` — navigates to `/w/{uuid}/cards`, asserts "Cartões" visible
- [ ] `creates a credit card` — clicks "Novo Cartão", fills #name, #credit_limit, #closing_day, #due_day, submits, asserts card name visible in list
- [ ] `shows validation errors on create` — submits empty form, asserts pt-BR error messages visible
- [ ] `edits a credit card` — clicks "Editar" in card, changes #name, submits, asserts updated name visible
- [ ] `updates credit_limit and sees available_limit update` — edits card, changes #credit_limit, submits, asserts updated available_limit visible
- [ ] `deletes a credit card` — creates second card, clicks "Excluir", asserts card name no longer exists
- [ ] Gate check passes: `npx cypress run --spec cypress/e2e/cards/crud.cy.js`
- [ ] Test count: 6 E2E tests pass

**Tests**: e2e (Cypress)
**Gate**: full (`npx cypress run --spec cypress/e2e/cards/crud.cy.js`)
**Commit**: `test(e2e): add credit card CRUD E2E tests`

---

## Parallel Execution Map

```
Phase 1 (Sequential):
  T1 ──→ T2

Phase 2 (Parallel):
  T2 complete, then:
    ├── T3 [P]
    ├── T4 [P]  } Can run simultaneously (independent files)
    ├── T5 [P]
    └── T6 [P]

Phase 3 (Sequential):
  T3, T4, T5, T6 complete, then:
    T7 (controller + routes + all PHPUnit tests)

Phase 4 (Parallel):
  T7 complete, then:
    ├── T8 [P]
    ├── T9 [P]  } Can run simultaneously (independent page files)
    └── T10[P]

Phase 5 (Sequential):
  T8, T9, T10 complete, then:
    T11 (Cypress E2E — needs all pages to test full journey)
```

**Parallelism constraint:** All `[P]` tasks in the same phase create different files with no shared mutable state. PHPUnit feature tests (T7) run sequentially because the controller is a single file. Cypress tests (T11) run after all frontend pages exist.

---

## Task Granularity Check

| Task                                  | Scope               | Status       |
| ------------------------------------- | ------------------- | ------------ |
| T1: Migration + Model                 | 2 files (coupled)   | ✅ Granular  |
| T2: Factory + Workspace relation      | 2 files (coupled)   | ✅ Granular  |
| T3: 2 FormRequests                   | 2 files (same concern) | ✅ Granular |
| T4: Resource                         | 1 file              | ✅ Granular  |
| T5: Policy                           | 1 file              | ✅ Granular  |
| T6: Service                         | 1 file              | ✅ Granular  |
| T7: Controller + Routes + 5 Tests    | 7 files (cohesive integration) | ⚠️ Large but cohesive |
| T8: Index page + Sidebar            | 2 files             | ✅ Granular  |
| T9: Create page                     | 1 file              | ✅ Granular  |
| T10: Edit page                      | 1 file              | ✅ Granular  |
| T11: Cypress E2E                    | 1 file              | ✅ Granular  |

**T7 justification:** The controller is a single file that can't be split across tasks. All 5 PHPUnit test files verify the controller's HTTP endpoints and can't be deferred (co-location rule). The merge-forward approach merges controller creation + its tests into one cohesive task. A sub-agent with the design doc can handle this — all files follow existing patterns closely (AccountController, AccountCreationTest, etc.).

---

## Diagram-Definition Cross-Check

| Task | Depends On (task body) | Diagram Shows | Status |
| ---- | ---------------------- | ------------- | ------ |
| T1   | None                   | Entry point   | ✅ Match |
| T2   | T1                     | T1 → T2       | ✅ Match |
| T3   | T1, T2                 | T2 → T3 (parallel) | ✅ Match |
| T4   | T1, T2                 | T2 → T4 (parallel) | ✅ Match |
| T5   | T1, T2                 | T2 → T5 (parallel) | ✅ Match |
| T6   | T1, T2                 | T2 → T6 (parallel) | ✅ Match |
| T7   | T3, T4, T5, T6         | T3-T6 → T7    | ✅ Match |
| T8   | T7                     | T7 → T8 (parallel) | ✅ Match |
| T9   | T7                     | T7 → T9 (parallel) | ✅ Match |
| T10  | T7                     | T7 → T10 (parallel) | ✅ Match |
| T11  | T8, T9, T10            | T8-T10 → T11  | ✅ Match |

---

## Test Co-location Validation

| Task | Code Layer Created/Modified | Required Test Type | Task Says | Status |
| ---- | --------------------------- | -------------------| --------- | ------ |
| T1   | Model + Migration           | none (no unit tests per project convention) | none | ✅ OK |
| T2   | Factory + Model edit        | none               | none     | ✅ OK |
| T3   | FormRequests                | none (validated via feature tests) | none | ✅ OK |
| T4   | Resource                   | none (validated via feature tests) | none | ✅ OK |
| T5   | Policy                     | none (validated via feature tests) | none | ✅ OK |
| T6   | Service                    | none (validated via feature tests) | none | ✅ OK |
| T7   | Controller + Routes        | feature (PHPUnit) | feature  | ✅ OK |
| T8   | Page (Index) + Component edit | e2e (Cypress) | none (merge-forward to T11) | ✅ Merge-forward |
| T9   | Page (Create)              | e2e (Cypress)     | none (merge-forward to T11) | ✅ Merge-forward |
| T10  | Page (Edit)                | e2e (Cypress)     | none (merge-forward to T11) | ✅ Merge-forward |
| T11  | Test file only             | e2e (Cypress)     | e2e      | ✅ OK |

**Merge-forward justification (T8-T10 → T11):** The Cypress E2E spec tests the full CRUD user journey (navigate → create → edit → update → delete) across all 3 page files. The spec cannot be written or run until all pages exist. Per the tasks reference: "When a task creates code that can't be tested until a later task completes, restructure: Merge forward — move the untestable task's tests into the earliest task where they become runnable." T11 is the earliest task where the E2E spec becomes runnable (after T8, T9, T10 complete). The E2E test is one cohesive spec file, not splittable across page tasks.

**Backend building blocks (T1-T6):** The project convention is "feature tests only, no unit tests" (AGENTS.md). Model, migration, factory, FormRequests, Resource, Policy, and Service are all tested through PHPUnit feature tests at the HTTP level in T7. `Tests: none` is valid because the project explicitly excludes unit tests for these layers.

---

## Commit Sequence

| Task | Commit Message |
|------|----------------|
| T1   | `feat(cards): add credit_cards migration and CreditCard model` |
| T2   | `feat(cards): add CreditCard factory and workspace relation` |
| T3   | `feat(cards): add StoreCardRequest and UpdateCardRequest` |
| T4   | `feat(cards): add CreditCardResource` |
| T5   | `feat(cards): add CreditCardPolicy` |
| T6   | `feat(cards): add CreditCardService` |
| T7   | `feat(cards): add CreditCardController, routes, and feature tests` |
| T8   | `feat(cards): add cards index page and wire sidebar nav` |
| T9   | `feat(cards): add cards create page` |
| T10  | `feat(cards): add cards edit page` |
| T11  | `test(e2e): add credit card CRUD E2E tests` |