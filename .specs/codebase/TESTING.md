# Testing Infrastructure

**Analyzed:** 2026-07-14

## Test Frameworks

**Backend / Feature:** PHPUnit (via Laravel 13.x test suite) — `phpunit.xml`
**E2E / Frontend:** Cypress 15.x (via Cypress Docker image) — `cypress.config.mjs`
**Coverage:** No coverage tool in use

*Note: Project policy (D-10) states "Feature tests only — no unit tests". The `tests/Unit/` directory exists with only the Laravel default `ExampleTest.php` and is deliberately unused. All backend testing is Feature-level.*

## Test Organization

### Backend Feature Tests

**Location:** `tests/Feature/{Domain}/`
**Naming:** `{Entity}{Action}Test.php` (e.g., `AccountCreationTest.php`, `TransactionAuthorizationTest.php`)
**Structure:** Domain-based folders matching feature domains:

```
tests/Feature/
├── Auth/
│   ├── AuthenticationTest.php
│   ├── RegistrationTest.php
│   ├── PasswordResetTest.php
│   └── PasswordChangeTest.php
├── Workspace/
│   ├── WorkspaceCreationTest.php
│   ├── InviteTest.php
│   └── MemberRoleTest.php
├── Accounts/
│   ├── AccountCreationTest.php
│   ├── AccountUpdateTest.php
│   ├── AccountDeletionTest.php
│   └── AccountAuthorizationTest.php
├── Categories/
│   ├── CategoryCreationTest.php
│   ├── CategoryUpdateTest.php
│   ├── CategoryDeletionTest.php
│   ├── CategoryAuthorizationTest.php
│   └── DefaultCategoryTest.php
├── Tags/
│   ├── TagCreationTest.php
│   ├── TagDeletionTest.php
│   └── TagAuthorizationTest.php
└── Transactions/
    ├── TransactionCreationTest.php
    ├── TransactionUpdateTest.php
    ├── TransactionDeletionTest.php
    ├── TransactionPaymentTest.php
    ├── TransactionFilteringTest.php
    ├── TransactionAuthorizationTest.php
    └── AccountBalanceRecalculationTest.php
```

**Test file pattern:** One test class per behavior category, multiple test methods per class.
**Test method naming:** `test_{scenario_description}` (e.g., `test_user_can_create_account`, `test_viewer_cannot_create_transaction`).

### E2E Cypress Tests

**Location:** `cypress/e2e/{Domain}/`
**Naming:** `{entity}.cy.js` (e.g., `crud.cy.js`, `register.cy.js`)
**Structure:** Domain-based folders:

```
cypress/e2e/
├── auth/
│   ├── register.cy.js
│   └── login.cy.js
├── workspace/
│   └── invite.cy.js
├── accounts/
│   └── crud.cy.js
├── categories/
│   └── crud.cy.js
├── tags/
│   └── crud.cy.js
└── transactions/
    └── crud.cy.js
```

**Support files:**
- `cypress/support/e2e.js` — imports commands
- `cypress/support/commands.js` — custom commands: `register`, `getVerificationLink`, `loginViaSession`, `registerAndCreateWorkspace`
- `cypress/screenshots/` — screenshot output directory

## Testing Patterns

### Feature Tests (PHPUnit)

**Approach:** HTTP-level integration tests exercising the full Laravel stack (routing, middleware, controllers, form requests, services, models, database).

**Base setup:** `tests/TestCase.php` — extends Laravel's base TestCase, uses `RefreshDatabase` trait. Every test starts with a clean in-memory SQLite database.

**Environment (phpunit.xml):**
- Database: SQLite `:memory:`
- Cache: `array`
- Queue: `sync`
- Mail: `array`
- Session: `array`

**Standard test setup pattern:**
```php
$user = User::factory()->create();
$workspace = Workspace::factory()->create();
$workspace->members()->attach($user, ["role" => WorkspaceRole::Admin->value]);
```

**Assertion patterns observed:**

| Pattern | Usage | Example |
|---------|-------|---------|
| `$response->assertRedirect(route(...))` | Verify successful creation/update redirects | `assertRedirect(route("accounts.index", $workspace))` |
| `$response->assertSessionHasErrors([...])` | Verify FormRequest validation failures | `assertSessionHasErrors(["name", "type", "initial_balance"])` |
| `$this->assertDatabaseHas(...)` | Verify Eloquent persistence | `assertDatabaseHas("accounts", ["name" => "Nubank", ...])` |
| `$response->assertInertia(fn ($page) => ...)` | Verify InertiaJS component + props rendering | `->component("Accounts/Index", false)->has("accounts", 2)` |
| `$response->assertForbidden()` | Verify policy-based authorization denial (Viewer role) | `$response->assertForbidden()` |
| `$response->assertNotFound()` | Verify cross-workspace isolation | `assertNotFound()` |
| `$response->assertStatus(200)` | Verify successful GET responses | `$response->assertStatus(200)` |

**Authorization test pattern:** Each domain has an `{Entity}AuthorizationTest.php` file testing all three workspace roles (Admin, Editor, Viewer) against every CRUD action, plus cross-workspace isolation tests.

**Factories:** All models have factories in `database/factories/`. Factories generate UUIDs (`Str::orderedUuid()->toString()`), realistic fake data (company names, random floats for balances), and required fields. Workspace membership is attached manually in tests (not in factory).

### E2E Tests (Cypress)

**Approach:** Browser-level tests hitting the real application at `http://localhost:8090` (app container). Tests use real Mailpit (`http://localhost:8026`) for email verification flows.

**Session management:** `cy.session()` via `loginViaSession` command — registers a new user, verifies email via Mailpit API, caches the session for reuse across tests in the same spec file.

**Custom commands (cypress/support/commands.js):**

| Command | Purpose |
|---------|---------|
| `cy.register(email)` | Registers a new user via the form |
| `cy.getVerificationLink(email)` | Fetches verification URL from Mailpit API |
| `cy.loginViaSession(sessionId)` | Full registration + email verification + session caching |
| `cy.registerAndCreateWorkspace(name)` | Registers, verifies, creates workspace, returns workspace UUID |

**E2E test pattern:**
```javascript
before(() => {
    cy.loginViaSession('accounts-session');
    // Setup: create workspace, seed data
});

beforeEach(() => {
    cy.loginViaSession('accounts-session');  // Restore cached session
});

it('creates an account', () => {
    cy.visit(`/w/${workspaceUuid}/accounts`);
    cy.contains('Nova Conta').click();
    cy.get('#name').type('Conta Teste');
    cy.get('#type').click();
    cy.contains('Corrente').click();
    cy.contains('Criar Conta').click();
    cy.contains('Conta Teste').should('be.visible');
});
```

**Assertions use:** `cy.contains(...).should('be.visible')`, `cy.url().should('include', ...)`, `cy.contains(...).should('not.exist')` — text-based, not data-testid based.

**Selector strategy:** Mix of `#field-id` selectors and text-based `cy.contains(...)`. Cards identified via `[data-slot="card"]` ancestor selector.

## Test Execution

### Backend Feature Tests

**Run inside Docker container:**
```bash
docker compose exec app php artisan test
```

**Run specific domain:**
```bash
docker compose exec app php artisan test --filter=Account
```

**Run specific test class:**
```bash
docker compose exec app php artisan test --filter=AccountCreationTest
```

**Run specific test method:**
```bash
docker compose exec app php artisan test --filter=test_user_can_create_account
```

**Run all feature tests:**
```bash
docker compose exec app php artisan test --testsuite=Feature
```

**Configuration:** `phpunit.xml` at project root. Uses SQLite in-memory, array drivers for cache/session/mail.

### E2E Cypress Tests

**Run headless (Docker profile):**
```bash
npm run cypress:run
# which runs: docker compose --profile testing run --rm cypress npx cypress run
```

**Run specific spec:**
```bash
docker compose --profile testing run --rm cypress npx cypress run --spec "cypress/e2e/accounts/crud.cy.js"
```

**Open Cypress UI (requires DISPLAY):**
```bash
npm run cypress:open
```

**Configuration:** `cypress.config.mjs` — baseUrl `http://localhost:8090`, viewport 1280x800.

### Prerequisites for E2E

1. **All Docker containers running:** `docker compose up -d` (app + db + mailpit must be up)
2. **App container accessible at port 8090**
3. **Mailpit accessible at port 8026** (for email verification flow)

## Test Coverage Matrix

| Code Layer | Required Test Type | Location Pattern | Run Command |
| ---------- | ------------------- | ---------------- | ----------- |
| Controllers (Http/Controllers) | Feature (PHPUnit) | `tests/Feature/{Domain}/{Entity}{Action}Test.php` | `docker compose exec app php artisan test --filter={Domain}` |
| FormRequests (Http/Requests) | Feature (validation assertions) | `tests/Feature/{Domain}/{Entity}CreationTest.php` (and Update variants) | `docker compose exec app php artisan test --filter={Entity}` |
| Policies (app/Policies) | Feature (authorization assertions) | `tests/Feature/{Domain}/{Entity}AuthorizationTest.php` | `docker compose exec app php artisan test --filter=Authorization` |
| Services (app/Services) | Feature (indirectly via controller tests) | tests/Feature/{Domain}/{Entity}{Action}Test.php | `docker compose exec app php artisan test --filter={Domain}` |
| Models + Migrations | Feature (assertDatabaseHas/Has) | tests/Feature/{Domain}/{Entity}CreationTest.php | `docker compose exec app php artisan test --filter={Domain}` |
| Enums (app/Enums) | Feature (via role assignment) | `tests/Feature/{Domain}/{Entity}AuthorizationTest.php` | `docker compose exec app php artisan test --filter=Authorization` |
| React Pages (resources/js/Pages) | E2E (Cypress) | `cypress/e2e/{domain}/crud.cy.js` | `npm run cypress:run -- --spec "cypress/e2e/{domain}/crud.cy.js"` |
| InertiaJS rendering | Feature (assertInertia) + E2E (UI) | `tests/Feature/{Domain}/{Entity}CreationTest.php` + `cypress/e2e/{domain}/*.cy.js` | Both commands above |
| Middleware (auth, workspace guard) | Feature (redirects, forbidden) | `tests/Feature/Auth/AuthenticationTest.php` + `tests/Feature/Workspace/WorkspaceCreationTest.php` | `docker compose exec app php artisan test --filter=Auth` |

## Parallelism Assessment

| Test Type | Parallel-Safe? | Isolation Model | Evidence |
| --------- | -------------- | ---------------- | -------- |
| PHPUnit Feature | Yes (within same run) | SQLite in-memory + `RefreshDatabase` trait (fresh DB per test method) | `tests/TestCase.php` uses `RefreshDatabase`; `phpunit.xml` sets `DB_DATABASE=:memory:` |
| Cypress E2E | No (by default) | Shared app instance on port 8090; tests use `cy.session()` for auth isolation but share the same DB state | `cypress.config.mjs` has no `parallel` config; `network_mode: host` shares app instance |

*Cypress tests are NOT parallel-safe because they share a single running application instance with a persistent MariaDB database. `cy.session()` isolates auth cookies but not database state. Test isolation relies on creating fresh workspaces per spec file via `before()` hooks.*

## Gate Check Commands

| Gate Level | When to Use | Command |
| ---------- | ----------- | ------- |
| Quick | After a single feature test file change | `docker compose exec app php artisan test --filter={EntityName}` |
| Domain | After completing all tests for one domain (e.g., all Account tests) | `docker compose exec app php artisan test --filter={Domain}` |
| Full Backend | After completing a milestone feature | `docker compose exec app php artisan test` |
| E2E (single spec) | After implementing a React page for a domain | `npm run cypress:run -- --spec "cypress/e2e/{domain}/crud.cy.js"` |
| E2E (full) | Before declaring a milestone complete | `npm run cypress:run` |
| Complete Gate | Milestone completion — all tests pass | `docker compose exec app php artisan test && npm run cypress:run` |

## Testing Conventions Summary

| Convention | Details |
|-----------|---------|
| No unit tests | D-10: All backend testing is Feature-level. Service classes tested indirectly through HTTP tests. |
| TDD-first | Tests written BEFORE implementation code. One test per acceptance criteria from spec.md. |
| Factories for all models | `database/factories/{Model}Factory.php` — UUIDs generated via `Str::orderedUuid()` |
| Domain-folded test structure | `tests/Feature/{Domain}/` mirrors `app/Http/Controllers/{Domain}/` |
| Behavior-class naming | `{Entity}{Behavior}Test.php` (Creation, Update, Deletion, Authorization, Filtering, Payment) |
| Role matrix in auth tests | Each domain's `AuthorizationTest` covers Admin (allowed), Editor (partial), Viewer (forbidden) |
| Cross-workspace isolation | Every domain tests that workspace B resources can't be accessed via workspace A's routes |
| Inertia assertions | `assertInertia` used to verify component name + props shape in list views |
| E2E via real browser | Cypress hits real app on port 8090, real Mailpit on port 8026 for email flows |
| E2E session caching | `cy.session()` wraps registration + verification flow, keyed by spec ID |
| E2E text-based assertions | pt-BR UI text used for assertions (`'Contas'`, `'Nova Conta'`, `'Criar Conta'`) |
| No coverage metrics | No xdebug/coverage tool configured. Test thoroughness measured by spec traceability. |