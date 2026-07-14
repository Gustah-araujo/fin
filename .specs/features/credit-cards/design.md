# Cartões de Crédito Design

**Spec**: `.specs/features/credit-cards/spec.md`
**Status**: Draft

---

## Architecture Overview

Credit cards follow the exact same architecture pattern established by Accounts: a workspace-scoped resource controller → service layer → Eloquent model with UUIDs. No new patterns are introduced. The sidebar already has a "Cartões" section with a placeholder link.

```mermaid
graph TD
    A[User: navigate to /w/{workspace}/cards] --> B[CreditCardController]
    B --> C[CreditCardPolicy: authorize]
    B --> D[CreditCardService: business logic]
    D --> E[CreditCard model]
    D --> F[DB: credit_cards table]
    B --> G[CreditCardResource: serialize]
    G --> H[Inertia Page: Cards/Index, Cards/Create, Cards/Edit]
    H --> I[AuthenticatedLayout + AppSidebar]
```

### Request flow (Create example)

```
POST /w/{workspace}/cards
  → StoreCardRequest (validation: name, credit_limit, closing_day, due_day)
  → CreditCardPolicy@create (admin/editor only)
  → CreditCardService@create
    → CreditCard::create([uuid, workspace_id, name, credit_limit, closing_day, due_day, available_limit = credit_limit])
  → redirect → cards.index
```

---

## Code Reuse Analysis

### Existing Components to Leverage

| Component                | Location                                           | How to Use                                                             |
| ------------------------ | -------------------------------------------------- | ---------------------------------------------------------------------- |
| AccountController        | `app/Http/Controllers/AccountController.php`       | Copy structure: resource controller, same authorize/abort_if pattern  |
| AccountService           | `app/Services/AccountService.php`                  | Mirror create/update/archive pattern; add recalculateAvailableLimit   |
| AccountPolicy            | `app/Policies/AccountPolicy.php`                    | Copy verbatim — same role checks (admin/editor/viewer)                |
| StoreAccountRequest      | `app/Http/Requests/StoreAccountRequest.php`        | Same FormRequest structure; replace fields                             |
| AccountResource          | `app/Http/Resources/AccountResource.php`           | Same JsonResource structure; replace fields                            |
| AuthenticatedLayout      | `resources/js/Layouts/AuthenticatedLayout.tsx`      | Wrap pages with it (same as Accounts)                                  |
| AppSidebar               | `resources/js/Components/AppSidebar.tsx`           | Already has "Cartões de Crédito" entry at line 78 — update href to route helper |
| Accounts/Index.tsx       | `resources/js/Pages/Accounts/Index.tsx`            | Card grid layout pattern; clone for cards                             |
| Accounts/Create.tsx      | `resources/js/Pages/Accounts/Create.tsx`           | Form + Card layout pattern; clone for cards                            |
| Accounts/Edit.tsx        | `resources/js/Pages/Accounts/Edit.tsx`             | Edit form pattern; clone for cards                                     |
| formatCurrency           | `resources/js/lib/format-currency.ts`              | Reuse directly for BRL display                                         |
| AccountFactory           | `database/factories/AccountFactory.php`            | Mirror factory pattern                                                 |
| AccountCreationTest      | `tests/Feature/Accounts/AccountCreationTest.php`   | Test structure pattern: create, validation, list, edge cases           |
| AccountAuthorizationTest | `tests/Feature/Accounts/AccountAuthorizationTest.php` | Authorization test pattern: viewer 403, cross-workspace 404         |

### Integration Points

| System                     | Integration Method                                         |
| -------------------------- | ----------------------------------------------------------- |
| Workspace model            | Add `creditCards(): HasMany` relation to Workspace         |
| Routes (web.php)           | Add `Route::resource('cards', CreditCardController::class)` in workspace prefix group |
| AppSidebar                 | Line 78: change `href: '/credit-cards'` → `route('cards.index', { workspace: workspaceUuid })` |
| AuthServiceProvider        | Register CreditCardPolicy (auto-discovered by Laravel)       |

---

## Components

### CreditCard Model

- **Purpose**: Eloquent model representing a credit card within a workspace
- **Location**: `app/Models/CreditCard.php`
- **Interfaces**:
  - `getRouteKeyName(): string` — returns 'uuid' for route model binding
  - `workspace(): BelongsTo` — workspace ownership
  - `creator(): BelongsTo` — user who created the card
- **Traits**: `HasFactory`, `SoftDeletes`
- **Fillable**: `uuid`, `workspace_id`, `created_by`, `name`, `credit_limit`, `available_limit`, `closing_day`, `due_day`
- **Casts**: `credit_limit` → `decimal:2`, `available_limit` → `decimal:2`, `closing_day` → `integer`, `due_day` → `integer`
- **Reuses**: Mirrors `Account` model structure exactly

### CreditCard Migration

- **Purpose**: Create `credit_cards` table
- **Location**: `database/migrations/2026_07_14_000002_create_credit_cards_table.php`
- **Schema**:
  ```
  id (bigIncrements, PK)
  uuid (uuid, unique)
  workspace_id (foreignId → workspaces, cascadeOnDelete)
  created_by (foreignId → users, nullable, nullOnDelete)
  name (string)
  credit_limit (decimal 15,2, default 0)
  available_limit (decimal 15,2, default 0)
  closing_day (tinyInteger, 1-31)
  due_day (tinyInteger, 1-31)
  softDeletes
  timestamps
  ```
- **Reuses**: Same migration structure as `create_accounts_table`

### CreditCardService

- **Purpose**: Business logic for CRUD + available_limit recalculation
- **Location**: `app/Services/CreditCardService.php`
- **Interfaces**:
  - `create(Workspace $workspace, User $creator, array $data): CreditCard` — creates card, sets `available_limit = credit_limit`
  - `update(CreditCard $card, array $data): CreditCard` — updates fields; if `credit_limit` changes, recalculates `available_limit`
  - `recalculateAvailableLimit(CreditCard $card): void` — `available_limit = credit_limit - sum of open card expenses` (in CARD-01, with no expenses table, equals `credit_limit`; CCXP-01 will call this after expense mutations). **Public method** — designed as the integration point for CCXP-01.
  - `archive(CreditCard $card): void` — soft-deletes card
- **Dependencies**: None (no AccountService dependency needed in CARD-01)
- **Reuses**: Mirrors `AccountService` structure

### CreditCardController

- **Purpose**: Resource controller for credit cards
- **Location**: `app/Http/Controllers/CreditCardController.php`
- **Interfaces**:
  - `index(Workspace $workspace): Response` — lists cards via Inertia
  - `create(Workspace $workspace): Response` — shows create form
  - `store(StoreCardRequest $request, Workspace $workspace, CreditCardService $service): RedirectResponse`
  - `edit(Workspace $workspace, CreditCard $card): Response` — shows edit form
  - `update(UpdateCardRequest $request, Workspace $workspace, CreditCard $card, CreditCardService $service): RedirectResponse`
  - `destroy(Workspace $workspace, CreditCard $card, CreditCardService $service): RedirectResponse`
- **Authorization**: `$this->authorize('viewAny', [CreditCard::class, $workspace])` etc. in each method. Cross-workspace guard: `abort_if($card->workspace_id !== $workspace->id, 404)`.
- **Reuses**: Copy of `AccountController` with field name changes

### CreditCardPolicy

- **Purpose**: Authorization rules for credit cards
- **Location**: `app/Policies/CreditCardPolicy.php`
- **Interfaces**: `viewAny`, `create`, `update`, `delete` — identical to AccountPolicy
- **Rules**:
  - `viewAny` — any workspace member
  - `create`, `update` — admin or editor
  - `delete` — admin only
- **Reuses**: Verbatim copy of `AccountPolicy` and `TransactionPolicy`

### StoreCardRequest

- **Purpose**: Validation for card creation
- **Location**: `app/Http/Requests/StoreCardRequest.php`
- **Rules**:
  ```php
  'name'         => ['required', 'string', 'max:255'],
  'credit_limit' => ['required', 'numeric', 'min:0'],
  'closing_day'  => ['required', 'integer', 'between:1,31'],
  'due_day'      => ['required', 'integer', 'between:1,31'],
  ```
- **Messages**: pt-BR, same style as StoreAccountRequest
- **Reuses**: Same FormRequest pattern as StoreAccountRequest

### UpdateCardRequest

- **Purpose**: Validation for card updates
- **Location**: `app/Http/Requests/UpdateCardRequest.php`
- **Rules**: Same as StoreCardRequest but all fields use `sometimes` prefix
- **Reuses**: Same pattern as UpdateAccountRequest

### CreditCardResource

- **Purpose**: Serialize CreditCard for Inertia props
- **Location**: `app/Http/Resources/CreditCardResource.php`
- **Output**:
  ```php
  [
      'uuid'            => $this->uuid,
      'name'            => $this->name,
      'credit_limit'    => (float) $this->credit_limit,
      'available_limit' => (float) $this->available_limit,
      'closing_day'     => $this->closing_day,
      'due_day'         => $this->due_day,
      'created_at'      => $this->created_at?->toISOString(),
  ]
  ```
- **Reuses**: Same JsonResource pattern as AccountResource

### CreditCardFactory

- **Purpose**: Factory for tests
- **Location**: `database/factories/CreditCardFactory.php`
- **Definition**:
  ```php
  'uuid'            => Str::orderedUuid()->toString(),
  'name'            => fake()->company(),
  'credit_limit'    => fake()->randomFloat(2, 1000, 50000),
  'available_limit' => fn (array $attrs) => $attrs['credit_limit'],
  'closing_day'     => fake()->numberBetween(1, 28),
  'due_day'         => fake()->numberBetween(1, 28),
  ```
- **Reuses**: Same factory pattern as AccountFactory

### Workspace Model Update

- **Purpose**: Add relationship to CreditCard
- **Location**: `app/Models/Workspace.php` (edit existing)
- **Change**: Add `creditCards(): HasMany` method
- **Reuses**: Same pattern as existing `accounts()`, `categories()`, `transactions()`

### Routes Update

- **Purpose**: Register card resource routes
- **Location**: `routes/web.php` (edit existing, line ~80)
- **Change**: Add inside `Route::prefix('w/{workspace}')->group()`:
  ```php
  Route::resource('cards', CreditCardController::class);
  ```
- **Route name**: `cards.index`, `cards.create`, `cards.store`, `cards.edit`, `cards.update`, `cards.destroy`

### AppSidebar Update

- **Purpose**: Wire "Cartões de Crédito" nav item to real route
- **Location**: `resources/js/Components/AppSidebar.tsx` (edit existing, line 78)
- **Change**:
  - Before: `{ label: 'Cartões de Crédito', href: '/credit-cards', icon: CreditCard }`
  - After: Use `route('cards.index', { workspace: workspaceUuid })` when workspace is available, same conditional pattern as accounts/categories/tags

---

## Frontend Components

### Cards/Index.tsx

- **Location**: `resources/js/Pages/Cards/Index.tsx`
- **Props**: `{ cards: CreditCardData[] }`
- **Layout**: Grid of Card components (same grid as Accounts/Index)
- **Card content**:
  - Title: card name
  - Two values (not one like accounts): `Limite: R$ X` and `Disponível: R$ Y`
  - Badges: `Fechamento: Dia {closing_day}` / `Vencimento: Dia {due_day}`
  - Actions: Editar (link), Excluir (button) — same as Accounts
- **Empty state**: Same dashed card with "Nenhum cartão cadastrado" + CTA
- **Reuses**: Clone of `Accounts/Index.tsx` structure

### Cards/Create.tsx

- **Location**: `resources/js/Pages/Cards/Create.tsx`
- **Form fields** (via `useForm`):
  - `name` (Input text)
  - `credit_limit` (Input number, step 0.01)
  - `closing_day` (Input number, min 1, max 31)
  - `due_day` (Input number, min 1, max 31)
- **Reuses**: Clone of `Accounts/Create.tsx` structure

### Cards/Edit.tsx

- **Location**: `resources/js/Pages/Cards/Edit.tsx`
- **Props**: `{ card: CreditCardData }`
- **Form**: Same fields as Create, pre-populated with `put` method
- **Reuses**: Clone of `Accounts/Edit.tsx` structure

### TypeScript Interfaces

Inline in each Page component (same pattern as Accounts — no global `.d.ts` types yet):
```typescript
interface CreditCard {
    uuid: string;
    name: string;
    credit_limit: number;
    available_limit: number;
    closing_day: number;
    due_day: number;
    created_at: string;
}
```

---

## Data Models

### CreditCard

```sql
credit_cards
├── id              BIGINT UNSIGNED (PK, auto-increment)
├── uuid            CHAR(36) UNIQUE
├── workspace_id    BIGINT UNSIGNED (FK → workspaces.id, cascadeOnDelete)
├── created_by      BIGINT UNSIGNED NULLABLE (FK → users.id, nullOnDelete)
├── name            VARCHAR(255)
├── credit_limit    DECIMAL(15,2) DEFAULT 0
├── available_limit DECIMAL(15,2) DEFAULT 0
├── closing_day     TINYINT (1-31)
├── due_day         TINYINT (1-31)
├── created_at      TIMESTAMP
├── updated_at      TIMESTAMP
└── deleted_at      TIMESTAMP NULLABLE
```

**Relationships**:
- `CreditCard` belongs to `Workspace` (workspace_id)
- `CreditCard` belongs to `User` as creator (created_by)
- `Workspace` has many `CreditCard` (new relation)
- Future (CCXP-01): `CreditCard` has many `CardExpense`

### available_limit Lifecycle

```
CARD-01 (this feature):
  CREATE card     → available_limit = credit_limit
  UPDATE card     → available_limit = credit_limit - 0 (no expenses yet)
  
CCXP-01 (future):
  CREATE expense  → available_limit -= expense.value
  UPDATE expense  → available_limit recalculated
  DELETE expense  → available_limit += expense.value
  PAY bill        → available_limit = credit_limit (cycle resets)
```

---

## Test Plan

### Backend Tests (PHPUnit Feature Tests)

Following the existing pattern: one test file per concern, in `tests/Feature/Cards/`. Each test uses factory + `actingAs` + `route()` helper + assertions (`assertRedirect`, `assertDatabaseHas`, `assertForbidden`, `assertNotFound`, `assertInertia`, `assertSoftDeleted`).

#### CardCreationTest.php

| Test Method                                          | Action                                                      | Asserts                                                         | Requirements |
| ----------------------------------------------------- | ----------------------------------------------------------- | --------------------------------------------------------------- | ------------ |
| `test_user_can_create_credit_card`                    | POST `cards.store` with valid data                          | Redirect to index; DB has card with all fields                  | CARD-01, CARD-03 |
| `test_available_limit_equals_credit_limit_on_create`  | POST `cards.store` with credit_limit = 5000                 | DB has `available_limit = 5000`                                  | CARD-07 |
| `test_validation_errors_on_create`                    | POST `cards.store` with empty payload                       | Session errors for name, credit_limit, closing_day, due_day      | CARD-03 |
| `test_closing_day_must_be_between_1_and_31`           | POST with closing_day = 0 and closing_day = 32              | Session error for closing_day                                    | CARD-03 (edge) |
| `test_due_day_must_be_between_1_and_31`                | POST with due_day = 0 and due_day = 32                      | Session error for due_day                                        | CARD-03 (edge) |
| `test_credit_limit_cannot_be_negative`                 | POST with credit_limit = -100                                | Session error for credit_limit                                   | CARD-03 (edge) |
| `test_credit_card_with_zero_limit_is_accepted`        | POST with credit_limit = 0                                   | Redirect; DB has card with credit_limit = 0, available_limit = 0 | CARD-07 (edge) |

**Pattern source**: `tests/Feature/Accounts/AccountCreationTest.php`

#### CardUpdateTest.php

| Test Method                                          | Action                                                      | Asserts                                                         | Requirements |
| ----------------------------------------------------- | ----------------------------------------------------------- | --------------------------------------------------------------- | ------------ |
| `test_user_can_update_card_name`                      | PUT `cards.update` with new name                             | Redirect; DB has updated name                                    | CARD-04 |
| `test_user_can_update_closing_and_due_day`            | PUT with new closing_day=15, due_day=25                      | Redirect; DB has updated days                                    | CARD-04 |
| `test_updating_credit_limit_recalculates_available_limit` | PUT with credit_limit = 8000 (original 5000, no expenses) | DB has available_limit = 8000                                    | CARD-09 |
| `test_validation_errors_on_update`                    | PUT with closing_day = 99                                    | Session error for closing_day                                    | CARD-04 (edge) |

**Pattern source**: `tests/Feature/Accounts/AccountUpdateTest.php`

#### CardAuthorizationTest.php

| Test Method                                          | Action                                                      | Asserts                                                         | Requirements |
| ----------------------------------------------------- | ----------------------------------------------------------- | --------------------------------------------------------------- | ------------ |
| `test_viewer_can_see_card_list`                       | GET `cards.index` as viewer                                  | 200 + Inertia component with cards data                         | CARD-02 |
| `test_viewer_cannot_create_card`                      | POST `cards.store` as viewer                                 | 403 Forbidden                                                    | CARD-06 |
| `test_viewer_cannot_update_card`                      | PUT `cards.update` as viewer                                 | 403 Forbidden                                                    | CARD-06 |
| `test_viewer_cannot_delete_card`                       | DELETE `cards.destroy` as viewer                             | 403 Forbidden                                                    | CARD-06 |
| `test_editor_can_create_card`                         | POST `cards.store` as editor                                 | Redirect (success)                                               | CARD-06 |
| `test_editor_can_update_card`                          | PUT `cards.update` as editor                                 | Redirect (success)                                               | CARD-06 |
| `test_only_admin_can_delete_card`                     | DELETE `cards.destroy` as editor                             | 403 Forbidden                                                    | CARD-05, CARD-06 |
| `test_cannot_access_card_from_other_workspace`        | PUT card from workspace B via workspace A URL                | 404 Not Found                                                    | CARD-06 (edge) |
| `test_non_member_cannot_access_cards`                 | GET `cards.index` as non-member                              | 403 Forbidden                                                    | CARD-02 (edge) |

**Pattern source**: `tests/Feature/Accounts/AccountAuthorizationTest.php`

#### CardDeletionTest.php

| Test Method                                          | Action                                                      | Asserts                                                         | Requirements |
| ----------------------------------------------------- | ----------------------------------------------------------- | --------------------------------------------------------------- | ------------ |
| `test_admin_can_soft_delete_card`                     | DELETE `cards.destroy` as admin                              | Redirect; `assertSoftDeleted`                                    | CARD-05 |
| `test_soft_deleted_card_not_in_list`                  | DELETE then GET `cards.index`                                | Inertia has cards = 0                                            | CARD-05 |

**Pattern source**: `tests/Feature/Accounts/AccountDeletionTest.php`

#### CardListTest.php

| Test Method                                          | Action                                                      | Asserts                                                         | Requirements |
| ----------------------------------------------------- | ----------------------------------------------------------- | --------------------------------------------------------------- | ------------ |
| `test_index_displays_all_card_fields`                 | GET `cards.index` with 2 cards                              | Inertia component `Cards/Index`; has cards=2; each has uuid, name, credit_limit, available_limit, closing_day, due_day | CARD-01, CARD-08 |
| `test_empty_workspace_shows_empty_state`              | GET `cards.index` with 0 cards                              | Inertia has cards=0                                               | CARD-01 (edge) |

**Pattern source**: `tests/Feature/Accounts/AccountCreationTest.php::test_account_list_shows_balances`

#### Backend Test Summary

| File                                | Tests | Requirements                |
| ----------------------------------- | ----- | --------------------------- |
| `CardCreationTest.php`              | 7     | CARD-01, CARD-03, CARD-07   |
| `CardUpdateTest.php`                | 4     | CARD-04, CARD-09            |
| `CardAuthorizationTest.php`         | 9     | CARD-02, CARD-05, CARD-06   |
| `CardDeletionTest.php`              | 2     | CARD-05                     |
| `CardListTest.php`                  | 2     | CARD-01, CARD-08            |
| **Total**                           | **24** | **All 10 requirements**    |

**Gate command:** `php artisan test --filter=Card` (run inside container)

---

### E2E Tests (Cypress)

Following the existing pattern in `cypress/e2e/`: one spec file per domain, uses `cy.loginViaSession()` for auth, creates workspace in `before()` hook, tests full user journey through the browser.

#### cypress/e2e/cards/crud.cy.js

| Test (it)                          | User Journey                                                 | Asserts                                                         | Requirements |
| ---------------------------------- | ------------------------------------------------------------ | --------------------------------------------------------------- | ------------ |
| `shows cards index page`           | Navigate to `/w/{uuid}/cards`                                | "Cartões" heading visible                                        | CARD-01 |
| `creates a credit card`            | Click "Novo Cartão" → fill name, credit_limit, closing_day, due_day → submit | URL includes `/cards`; card name visible in list                | CARD-03, CARD-07 |
| `shows validation errors on create`| Visit create page → submit empty form                        | Error messages visible: "O nome é obrigatório", "O limite é obrigatório", etc. | CARD-03 (edge) |
| `edits a credit card`              | Find card → click "Editar" → change name → submit            | URL includes `/cards`; updated name visible                      | CARD-04 |
| `updates credit_limit and sees available_limit update` | Edit card → change credit_limit → submit | Updated `available_limit` visible (formatCurrency)   | CARD-09 |
| `deletes a credit card`            | Create a second card → click "Excluir"                       | Card name no longer exists in list                               | CARD-05 |

**Spec structure** (mirrors `cypress/e2e/accounts/crud.cy.js`):

```javascript
describe('Credit Card CRUD', () => {
    let workspaceUuid;

    before(() => {
        cy.loginViaSession('cards-session');

        cy.visit('/workspace/create');
        cy.get('#name').type('E2E Cards');
        cy.get('button[type="submit"]').click();

        cy.url().should('match', /\/w\/([a-f0-9-]+)/);
        cy.url().then((url) => {
            workspaceUuid = url.match(/\/w\/([a-f0-9-]+)/)[1];
        });
    });

    beforeEach(() => {
        cy.loginViaSession('cards-session');
    });

    // ... tests
});
```

**Key selectors used** (consistent with existing specs):
- `#name` — name input
- `#credit_limit` — credit limit input
- `#closing_day` — closing day input
- `#due_day` — due day input
- `button[type="submit"]` — form submit
- `.contains('Novo Cartão')` — create button
- `.closest('[data-slot="card"]')` — card container in grid
- `.contains('button', 'Excluir')` — delete button

**Gate command:** `npx cypress run --spec cypress/e2e/cards/crud.cy.js` (run inside container, app must be running at `localhost:8090`)

---

### Test Coverage Matrix

| Requirement ID | Backend Test                                        | E2E Test                          |
| -------------- | --------------------------------------------------- | --------------------------------- |
| CARD-01        | CardListTest, CardCreationTest                      | `shows cards index page`           |
| CARD-02        | CardAuthorizationTest (viewer_can_see)             | (implicit — viewer sees list)     |
| CARD-03        | CardCreationTest (all 7 tests)                      | `creates a credit card`, `shows validation errors` |
| CARD-04        | CardUpdateTest (all 4 tests)                        | `edits a credit card`             |
| CARD-05        | CardDeletionTest, CardAuthorizationTest (admin_only) | `deletes a credit card`          |
| CARD-06        | CardAuthorizationTest (9 tests)                    | (implicit — UI hides actions for viewers) |
| CARD-07        | CardCreationTest (available_limit, zero_limit)     | `creates a credit card`           |
| CARD-08        | CardListTest (index_displays_all_fields)           | (implicit — rendered in card grid) |
| CARD-09        | CardUpdateTest (recalculates_available_limit)      | `updates credit_limit`            |
| CARD-10        | (unit-level: CreditCardService@recalculateAvailableLimit — covered via CardUpdateTest) | (N/A — internal method) |

---

## Error Handling Strategy

| Error Scenario                              | Handling                     | User Impact                |
| ------------------------------------------- | ---------------------------- | -------------------------- |
| Missing required fields on create           | FormRequest validation       | Inline error messages      |
| closing_day or due_day outside 1-31        | FormRequest `between:1,31`   | Inline error message       |
| credit_limit negative                       | FormRequest `min:0`          | Inline error message       |
| Viewer attempts create/edit/delete          | Policy returns false → 403   | Forbidden page             |
| Accessing card from another workspace       | `abort_if` 404              | Not Found page             |
| Soft-deleted card accessed via direct URL   | Route model binding (SoftDeletes scope) → 404 | Not Found |

---

## Tech Decisions

| Decision                          | Choice                        | Rationale                                                              |
| --------------------------------- | ----------------------------- | --------------------------------------------------------------------- |
| Route name `cards` not `credit-cards` | `cards`                      | Shorter, consistent with `accounts`, `transactions`. Sidebar label stays "Cartões de Crédito" |
| `available_limit` as persisted column | Column + service recalc      | O(1) reads; trade-off: must keep in sync on every expense mutation. Service exposes public `recalculateAvailableLimit` as integration point for CCXP-01 |
| `closing_day`/`due_day` as `tinyInteger` | tinyInteger                  | Domain values 1-31; tinyInteger(1 byte) is sufficient                  |
| No enum for card type              | Plain model                   | User decided cards are generic (no physical/virtual/additional)         |
| No `show` page                      | Index + Create + Edit only    | Cards are simple entities; detail view not needed (shows in card grid); consistent with Accounts which also has no show page |
| Factory `available_limit` as closure | `fn ($attrs) => $attrs['credit_limit']` | Keeps available_limit in sync with credit_limit by default in tests   |

---

## File Inventory

### New Files (18)

| # | File                                             | Type     |
|---|--------------------------------------------------|----------|
| 1 | `app/Models/CreditCard.php`                      | Model    |
| 2 | `database/migrations/2026_07_14_000002_create_credit_cards_table.php` | Migration |
| 3 | `app/Services/CreditCardService.php`             | Service  |
| 4 | `app/Http/Controllers/CreditCardController.php`  | Controller |
| 5 | `app/Policies/CreditCardPolicy.php`             | Policy   |
| 6 | `app/Http/Requests/StoreCardRequest.php`        | FormRequest |
| 7 | `app/Http/Requests/UpdateCardRequest.php`      | FormRequest |
| 8 | `app/Http/Resources/CreditCardResource.php`     | Resource |
| 9 | `database/factories/CreditCardFactory.php`        | Factory  |
| 10| `resources/js/Pages/Cards/Index.tsx`             | Page     |
| 11| `resources/js/Pages/Cards/Create.tsx`            | Page     |
| 12| `resources/js/Pages/Cards/Edit.tsx`              | Page     |
| 13| `tests/Feature/Cards/CardCreationTest.php`       | PHPUnit  |
| 14| `tests/Feature/Cards/CardUpdateTest.php`         | PHPUnit  |
| 15| `tests/Feature/Cards/CardAuthorizationTest.php`  | PHPUnit  |
| 16| `tests/Feature/Cards/CardDeletionTest.php`       | PHPUnit  |
| 17| `tests/Feature/Cards/CardListTest.php`            | PHPUnit  |
| 18| `cypress/e2e/cards/crud.cy.js`                    | Cypress  |

### Modified Files (3)

| # | File                              | Change                                                          |
|---|-----------------------------------|-----------------------------------------------------------------|
| 1 | `app/Models/Workspace.php`        | Add `creditCards(): HasMany` relation                           |
| 2 | `routes/web.php`                  | Add `Route::resource('cards', CreditCardController::class)`    |
| 3 | `resources/js/Components/AppSidebar.tsx` | Line 78: replace hardcoded `/credit-cards` with `route('cards.index', ...)` conditional |

---

## Requirement → Design Mapping

| Requirement ID | Component(s)                                                                 |
| -------------- | ---------------------------------------------------------------------------- |
| CARD-01        | CreditCardController@index, Index.tsx, CreditCardResource, migration         |
| CARD-02        | CreditCardPolicy@viewAny, Index.tsx (conditional render of action buttons)  |
| CARD-03        | CreditCardController@store, StoreCardRequest, CreditCardService@create       |
| CARD-04        | CreditCardController@update, UpdateCardRequest, CreditCardService@update     |
| CARD-05        | CreditCardController@destroy, CreditCardService@archive                      |
| CARD-06        | CreditCardPolicy@create/update/delete (returns false for viewer)             |
| CARD-07        | CreditCardService@create (sets available_limit = credit_limit)              |
| CARD-08        | CreditCardResource (exposes available_limit), Index.tsx display              |
| CARD-09        | CreditCardService@update (recalculates available_limit on credit_limit change) |
| CARD-10        | CreditCardService@recalculateAvailableLimit (public method for CCXP-01)     |