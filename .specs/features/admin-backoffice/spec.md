# Admin Backoffice (`/admin`) — Specification

**Source:** GitHub issue "[Feature] Área Administrativa Global (Backoffice `/admin`)"
**Phase:** New — Global Administration
**Status:** Spec — pending approval (see Open Questions)

## Problem Statement

The entire system operates inside a workspace context; there is no global administrative interface for platform operations and support. System administrators need a dedicated backoffice (`/admin`), fully decoupled from workspace scope, containing: a Dashboard home screen, management of registered users (with e-mail verification tracking and support actions), and a read-only view of all workspaces and their members.

## Goals

- [ ] Users have a `type` column (`admin` / `customer`, default `customer`) that is NOT mass-assignable
- [ ] All `/admin/*` routes (views + action endpoints) are protected by `EnsureUserIsAdmin` middleware returning 403 for non-admins
- [ ] Admin routes work with NO active workspace in session (no `EnsureHasWorkspace` interference)
- [ ] Admins are redirected to `/admin` after login; customers keep current behavior
- [ ] "Área Admin" quick-access button in the App header, visible only to admins
- [ ] Dedicated `AdminLayout` with sidebar (Dashboard, Usuários, Workspaces) + "Voltar para o App" button
- [ ] Dashboard (`/admin`) shows "Total de Usuários Ativos" widget
- [ ] Users listing (`/admin/users`) with DataTable, search, verification filter, and conditional actions dropdown (resend e-mail sync + manual verify) with Sonner toasts
- [ ] User details (`/admin/users/{user}`) with user's workspaces widget (name, role, link)
- [ ] Workspaces listing (`/admin/workspaces`) with view button
- [ ] Workspace details (`/admin/workspaces/{workspace}`) with metadata only (NO financial data) + members widget
- [ ] Reusable `DropdownButton` shared component (created if none exists — confirmed: none exists today)
- [ ] All GET routes have smoke tests (PHPUnit + Cypress)

## Key Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| User type | New `type` string column + `App\Enums\UserType` (`admin`/`customer`), default `customer`, excluded from `$fillable` | Follows `WorkspaceRole` enum pattern; issue requires no mass assignment |
| Authorization | Middleware `EnsureUserIsAdmin` (alias `ensure.admin`) calling `abort(403)`, NOT a Policy | Single global rule, not model-scoped; policies are per-model in this codebase |
| Route group | `Route::middleware(['auth','verified','ensure.admin'])->prefix('admin')` with `admin.*` route names, OUTSIDE the `ensure.has.workspace` group | Existing `EnsureHasWorkspace` only applies to its own group; separate group guarantees workspace-independence |
| Route binding | UUID binding (`admin/users/{user}`, `admin/workspaces/{workspace}`) via `getRouteKeyName()` | Project convention (UUIDs everywhere) |
| Users table | Reuse existing `DataTable` component + new JSON endpoint `GET admin/users.datatable` | DataTable is JSON/axios-based and supports custom `cell` renderers (verified in code) — matches issue's "DataTable" requirement |
| Dropdown | New shared `Components/DropdownButton.tsx` composing shadcn `DropdownMenu` + `Button` | No reusable wrapper exists today (only inline usages in `UserMenu`/`WorkspaceSwitcher`); issue requires a reusable component |
| Toasts on /admin | `AdminLayout` mounts sonner `Toaster` + consumes `flash.success/error` (same pattern as `AuthenticatedLayout`) | Flash toast consumption currently lives only in `AuthenticatedLayout`; admin pages must not depend on it |
| Widget "Usuários Ativos" | Count of users with `email_verified_at !== null` | "Active" = confirmed account (pending user answer — see Open Questions) |
| Workspace owner | Workspace detail shows all members with `role = admin` as "Proprietário/Administrador" | `workspaces` table has NO `owner_id`; ownership is implicit via pivot role |
| Post-login redirect | In `AuthenticatedSessionController::store()`: unverified users → `verification.notice` (unchanged); verified admins → `/admin`; verified customers → current behavior | E-mail verification flow takes precedence (existing rule) |
| Cypress admin seeding | New non-production testing endpoint `POST /testing/create-admin` (in the existing `testing.*` block) | Cypress has no way to promote a user to admin via UI (by design) |

## Out of Scope

| Feature | Reason |
|---------|--------|
| Promote/demote users to admin via UI | Issue explicitly restricts admin promotion to direct DB manipulation |
| Edit/delete users or workspaces from backoffice | Read-only + support actions only, per issue |
| Expose financial data in workspace details | Issue explicitly forbids (revenues, expenses, balances, conversations) |
| Admin metrics beyond user count | "Base estrutural para futuras métricas" — only the first widget now |
| User type change on existing flows | Registration, Google OAuth, invites continue creating `customer` users |

---

## User Stories

### P1: Coluna `type` no Usuário ⭐ MVP

**User Story**: As the platform, I need a `type` field on users so that administrators can be distinguished from customers.

**Acceptance Criteria**:

1. WHEN the migration runs THEN the `users` table SHALL have a `type` string column with values `admin` / `customer`, defaulting to `customer`.
2. WHEN existing users are migrated THEN all SHALL be backfilled to `customer`.
3. WHEN a new user registers (e-mail/password or Google OAuth) THEN the account SHALL be created as `customer`.
4. WHEN `$model->fill([...])` or mass assignment attempts to set `type` THEN the attribute SHALL NOT be changed (not fillable).
5. WHEN the codebase references the type THEN it SHALL use `App\Enums\UserType` (string-backed, with `label()` returning "Administrador"/"Cliente" in pt-BR).

**Independent Test**: Create user → `type` is `customer`; attempt `User::create([... 'type' => 'admin'])` → `type` stays `customer`; manual DB update to `admin` persists.

---

### P1: Middleware & Rotas `/admin` ⭐ MVP

**User Story**: As the platform, I need every `/admin/*` route (views and action endpoints) to reject non-admin users with 403 and work without an active workspace.

**Acceptance Criteria**:

1. WHEN a `customer` requests ANY route under `/admin` (GET, POST) THEN system SHALL return HTTP 403 Forbidden.
2. WHEN an unauthenticated user requests an `/admin` route THEN system SHALL be redirected to login (standard `auth` middleware behavior).
3. WHEN an unverified user with `type=admin` requests an `/admin` route THEN system SHALL follow the `verified` middleware behavior (redirect to verification notice).
4. WHEN an `admin` user with ZERO workspaces accesses `/admin/*` THEN system SHALL return HTTP 200 (no redirect to workspace creation).
5. WHEN the middleware is invoked THEN it SHALL check `type === 'admin'` only — NOT workspace membership or e-mail verification status beyond the group's `verified` middleware.

**Independent Test**: Customer hits `GET /admin` and `GET /admin/users` → 403; zero-workspace admin hits `GET /admin` → 200.

---

### P1: Redirecionamento Pós-Login & Navegação Cruzada ⭐ MVP

**User Story**: As an admin, I want to land on `/admin` after login and reach the backoffice from the app header (and back) so that I can move between the two areas quickly.

**Acceptance Criteria**:

1. WHEN a verified `admin` logs in via e-mail/password THEN system SHALL redirect to `/admin` (dashboard) instead of the workspace selection flow.
2. WHEN a user with unverified e-mail logs in THEN system SHALL redirect to the verification notice screen (unchanged existing behavior, regardless of type).
3. WHEN a verified `customer` logs in THEN system SHALL keep the current redirect behavior (workspace selection/app).
4. WHEN `HandleInertiaRequests` shares `auth.user` THEN the payload SHALL include `type` so the frontend can conditionally render admin UI.
5. WHEN an `admin` views the App header (authenticated layout) THEN system SHALL display an "Área Admin" button linking to `/admin`.
6. WHEN a `customer` views the App header THEN the "Área Admin" button SHALL NOT be rendered (not merely hidden).

**Independent Test**: Verified admin logs in → lands on `/admin`; opens App → header shows "Área Admin"; customer logs in → no button.

---

### P1: Layout do Backoffice ⭐ MVP

**User Story**: As an admin, I want a dedicated backoffice layout with its own sidebar navigation so that the admin area is visually and functionally separate from the workspace app.

**Acceptance Criteria**:

1. WHEN any `/admin/*` page renders THEN it SHALL use a new `AdminLayout` (separate from `AuthenticatedLayout`), with header ("Fin · Backoffice Admin") and left sidebar.
2. WHEN the sidebar renders THEN it SHALL contain the items: **Dashboard** (`/admin`), **Usuários** (`/admin/users`), **Workspaces** (`/admin/workspaces`), with the active item highlighted.
3. WHEN the layout renders THEN it SHALL show a **"Voltar para o App"** button navigating to the normal app area (workspace selection/app root).
4. WHEN the layout renders THEN it SHALL mount the sonner `Toaster` and consume `flash.success`/`flash.error` shared props (same toast pattern as `AuthenticatedLayout`) so admin mutations produce toasts.
5. WHEN the current user is an admin on an `/admin` page THEN the layout SHALL NOT render workspace context (no workspace switcher, no `useWorkspace()` usage — it throws without workspace).

**Independent Test**: Admin visits `/admin/users` → sees sidebar with 3 items, active "Usuários" highlighted, "Voltar para o App" button present.

---

### P1: Dashboard (`/admin`) ⭐ MVP

**User Story**: As an admin, I want a dashboard home at `/admin` showing the total of active users so that I have an operational overview entry point.

**Acceptance Criteria**:

1. WHEN an admin accesses `/admin` (or logs in as admin) THEN system SHALL render the Dashboard page showing the "Total de Usuários Ativos" widget.
2. WHEN the widget renders THEN it SHALL display the count of users with `email_verified_at !== null` (definition pending Open Question Q1).
3. WHEN the count is computed THEN it SHALL be passed from the controller as a typed prop (no client-side aggregation).

**Independent Test**: Seed 3 verified + 2 unverified users → `/admin` widget shows 3.

---

### P1: Componente Reutilizável de Dropdown ⭐ MVP

**User Story**: As the app, I need a shared dropdown-button component so that row/table action menus are consistent and not coupled to a single screen.

**Acceptance Criteria**:

1. WHEN the codebase is searched for a reusable dropdown-button component THEN none exists (only inline shadcn `DropdownMenu` compositions) — a new shared component SHALL be created.
2. WHEN created THEN it SHALL live in `resources/js/Components/` (shared, not inside a domain folder), composing shadcn `DropdownMenu` + `Button` primitives (no manual edits to `Components/ui/*`).
3. WHEN consumed THEN it SHALL accept a trigger (label and/or icon) and a list of action items (label, callback, destructive flag) and render them as menu items.
4. WHEN the users table uses it THEN it SHALL be imported from the shared location (no local reimplementation).

**Independent Test**: Component renders trigger button; clicking opens the menu with the passed items; items fire their callbacks.

---

### P1: Listagem de Usuários (`/admin/users`) ⭐ MVP

**User Story**: As an admin, I want a searchable, filterable table of all platform users so that I can audit accounts and find users for support.

**Acceptance Criteria**:

1. WHEN an admin accesses `/admin/users` THEN system SHALL render a DataTable listing all platform users with columns: **Nome**, **E-mail**, **Status Verificado** (badge "Verificado"/"Pendente"), **Workspaces** (count), **Data de Cadastro**, **Ações**.
2. WHEN the admin types in the search field THEN the table SHALL filter server-side by name OR e-mail (case-insensitive partial match).
3. WHEN the admin selects a verification filter (Todos / Verificados / Não Verificados) THEN the table SHALL filter server-side on `email_verified_at`.
4. WHEN a row's **Visualizar** action is triggered THEN system SHALL navigate to `/admin/users/{user}` (uuid).
5. WHEN a row belongs to an unverified user (`email_verified_at === null`) THEN the actions dropdown SHALL render with the options: "Reenviar e-mail de confirmação" and "Marcar como verificado manualmente".
6. WHEN a row belongs to a verified user THEN the actions dropdown SHALL NOT render (only Visualizar).
7. WHEN the listing data is returned THEN it SHALL pass through an ApiResource (e.g., `AdminUserResource` / datatable JSON shape) — no raw arrays.

**Independent Test**: Seed verified + unverified users → search by e-mail fragment narrows rows; filter "Não Verificados" shows only unverified; unverified row shows dropdown.

---

### P1: Ações de Suporte sobre Usuário ⭐ MVP

**User Story**: As an admin, I want to resend the verification e-mail or manually verify an unverified user so that support requests can be resolved.

**Acceptance Criteria**:

1. WHEN the admin triggers "Reenviar e-mail de confirmação" for an unverified user THEN system SHALL POST to a dedicated admin endpoint that sends the verification e-mail **synchronously** (no queue) to that user's e-mail and flash a success toast ("E-mail de verificação reenviado.").
2. WHEN the admin triggers "Marcar como verificado manualmente" THEN system SHALL POST to a dedicated admin endpoint that sets `email_verified_at = now()` for that user and flash a success toast ("Usuário marcado como verificado.").
3. WHEN either action targets an already-verified user THEN system SHALL reject gracefully (HTTP 422 or no-op with informative flash) — not silently duplicate work.
4. WHEN any of these endpoints is hit by a `customer` THEN system SHALL return 403.
5. WHEN the action succeeds THEN the table/page SHALL refresh reflecting the new status.
6. WHEN the actions mutate state THEN they SHALL go through Service classes (not inline controller logic), per project convention.

**Independent Test**: Admin resends → Mail array driver captures 1 verification e-mail to that user + toast; admin verifies manually → `email_verified_at` filled + toast.

---

### P1: Detalhes do Usuário (`/admin/users/{user}`) ⭐ MVP

**User Story**: As an admin, I want a user detail screen with their data and their workspaces so that I can trace a user's presence across the platform.

**Acceptance Criteria**:

1. WHEN an admin accesses `/admin/users/{user}` (uuid binding) THEN system SHALL render the user's data: nome, e-mail, tipo (`admin`/`customer`), status de verificação, data de cadastro.
2. WHEN the user is unverified THEN the page SHALL keep the same support actions (resend / manual verify) as the listing.
3. WHEN the page loads THEN it SHALL render a "Workspaces do Usuário" widget listing every workspace the user belongs to, with: **Nome do Workspace**, **Papel/Role** (Administrador/Editor/Visualizador), and a clickable link to `/admin/workspaces/{workspace}`.
4. WHEN the user belongs to zero workspaces THEN the widget SHALL show an empty state.
5. WHEN any of this data is returned THEN it SHALL pass through ApiResources (nested models wrapped in their own Resources).

**Independent Test**: User in 2 workspaces (admin in one, editor in other) → detail page shows both with correct roles and working links.

---

### P1: Listagem de Workspaces (`/admin/workspaces`) ⭐ MVP

**User Story**: As an admin, I want a listing of all platform workspaces so that I can locate any workspace for support.

**Acceptance Criteria**:

1. WHEN an admin accesses `/admin/workspaces` THEN system SHALL render a list of all workspaces with: **Nome**, **Data de Criação**, **Quantidade de Membros**, and a **Visualizar** button linking to `/admin/workspaces/{workspace}`.
2. WHEN the data is returned THEN it SHALL pass through ApiResources (member count eager-loaded, no N+1).

**Independent Test**: Seed 3 workspaces with 1/2/5 members → listing shows 3 rows with correct member counts.

---

### P1: Detalhes do Workspace (`/admin/workspaces/{workspace}`) ⭐ MVP

**User Story**: As an admin, I want a workspace detail screen with its registration metadata and member list so that I can inspect it without exposing financial data.

**Acceptance Criteria**:

1. WHEN an admin accesses `/admin/workspaces/{workspace}` THEN system SHALL render exclusively registration metadata: **nome**, **data de criação**, **proprietário(s)/criador(es)** (members with `role=admin` — no `owner_id` column exists).
2. WHEN the page loads THEN it SHALL render a "Usuários do Workspace" widget listing every member with: **Nome**, **E-mail**, **Papel/Role** no workspace, and a clickable link to `/admin/users/{user}`.
3. WHEN the page renders THEN it SHALL NOT include any financial data (accounts, transactions, incomes, expenses, balances) or AI conversations — metadata only.
4. WHEN the data is returned THEN it SHALL pass through ApiResources.

**Independent Test**: Workspace with 3 members → detail shows metadata + 3 members with roles and links; page props contain no financial keys.

---

## Testing Strategy

### PHPUnit Feature Tests (one per acceptance criterion group)

- `tests/Feature/Admin/AuthorizationTest.php` — customer gets 403 on every `/admin/*` GET/POST; zero-workspace admin gets 200; `type` not mass-assignable.
- `tests/Feature/Admin/AuthRedirectTest.php` — verified admin → `/admin` after login; customer keeps current redirect; unverified → verification notice.
- `tests/Feature/Admin/DashboardTest.php` — active-users count correctness (verified vs unverified mix).
- `tests/Feature/Admin/UsersListingTest.php` — datatable endpoint: search by name/e-mail, verification filter, resource shape.
- `tests/Feature/Admin/UserActionsTest.php` — synchronous resend (Mail fake), manual verify (`email_verified_at` filled), 422/idempotency on verified user, 403 for customers, `assertToastSuccess()`.
- `tests/Feature/Admin/UserDetailTest.php` / `WorkspaceDetailTest.php` — data correctness, roles, cross-links, absence of financial keys.
- Smoke tests: one per GET route (`admin.dashboard`, `admin.users.index`, `admin.users.show`, `admin.workspaces.index`, `admin.workspaces.show`) — `assertOk()` + `assertInertia(component)`.

### Cypress E2E

- `cypress/e2e/admin/backoffice.cy.ts` — login as admin (via new `/testing/create-admin` seeding) → lands on `/admin` → widget visible → navigate sidebar (Dashboard/Usuários/Workspaces) → cross-navigation "Área Admin" ↔ "Voltar para o App"; smoke at top.
- `cypress/e2e/admin/users.cy.ts` — filter "Não Verificados" → open row dropdown → "Reenviar e-mail de confirmação" + "Marcar como verificado manualmente" with Sonner toasts (`assertToast`); smoke at top.
- `cypress/e2e/admin/cross-navigation.cy.ts` — user details → click workspace in widget → workspace details → click user in widget; smoke at top.

### Execution notes

- Mail assertions use Laravel `Mail::fake()` (tests use `array` mail driver; Cypress E2E may inspect Mailpit).
- Admin state for E2E comes from the non-production `testing.*` route block (never available in production).

---

## Edge Cases

- WHEN an admin user exists with zero workspaces THEN `/admin` works and "Voltar para o App" sends them to workspace selection (exempt from `EnsureHasWorkspace`).
- WHEN a `customer` tries to guess an admin action URL (POST) THEN the middleware aborts 403 before controller logic runs.
- WHEN the admin resends verification to a Google-OAuth user (auto-verified) THEN the action is not offered (row is verified).
- WHEN a user belongs to a workspace but is not in the listing's search results THEN pagination/search must not break on unicode names.
- WHEN the workspace has multiple members with `role=admin` THEN the detail page shows all of them as owners (no single-owner assumption).
- WHEN the admin is also a regular app user THEN both areas remain fully usable (no data interference between contexts).

---

## Open Questions (pending user confirmation)

| # | Question | Proposed answer (used in this spec) |
|---|----------|-------------------------------------|
| Q1 | What exactly counts as "Total de Usuários Ativos"? All registered users? Verified users? Users with recent activity (login)? | Users with `email_verified_at !== null` |
| Q2 | Should the `/admin` route group require e-mail verification (`verified` middleware)? An admin whose e-mail is unverified would land on the verification screen after login. | Yes, keep `verified` — consistent with the rest of the authenticated app |
| Q3 | Workspace "proprietário/criador": there is no `owner_id` on `workspaces` — show all `role=admin` members as owners? | Yes, list all admin-role members |
| Q4 | Users table: reuse the existing JSON-based `DataTable` component with a new `admin/users.datatable` endpoint (project precedent), or build a simpler Inertia server-filtered table? | Reuse existing `DataTable` |
| Q5 | Manual verification / resend on an already-verified user (defensive backend): reject with 422 or no-op success? | Reject with 422 + informative flash |
| Q6 | Cypress needs a new non-production endpoint `POST /testing/create-admin` (promotes/creates an admin and logs in) since admins can't be produced via the UI. Acceptable? | Yes, inside the existing `testing.*` block |
| Q7 | Admin support actions: throttle (e.g. `throttle:6,1` like the existing self-resend) or unthrottled? | Apply `throttle:6,1` to resend |

---

## Requirement Traceability

| ID | Story | Status |
|----|-------|--------|
| ADMB-01 | Coluna `type` no Usuário | Spec |
| ADMB-02 | Middleware & Rotas `/admin` | Spec |
| ADMB-03 | Redirecionamento Pós-Login & Navegação Cruzada | Spec |
| ADMB-04 | Layout do Backoffice | Spec |
| ADMB-05 | Dashboard (`/admin`) | Spec |
| ADMB-06 | Componente Reutilizável de Dropdown | Spec |
| ADMB-07 | Listagem de Usuários (`/admin/users`) | Spec |
| ADMB-08 | Ações de Suporte sobre Usuário | Spec |
| ADMB-09 | Detalhes do Usuário (`/admin/users/{user}`) | Spec |
| ADMB-10 | Listagem de Workspaces (`/admin/workspaces`) | Spec |
| ADMB-11 | Detalhes do Workspace (`/admin/workspaces/{workspace}`) | Spec |

---

## Success Criteria

- [ ] `customer` receives 403 on every `/admin/*` route (views + actions)
- [ ] Backoffice fully functional for admins with zero workspaces
- [ ] Verified admin lands on `/admin` after login; "Área Admin" ↔ "Voltar para o App" cross-navigation works
- [ ] Dashboard shows correct active-users count
- [ ] Users DataTable: search, verification filter, conditional actions dropdown, ApiResource-backed data
- [ ] Resend e-mail is synchronous and manual verify fills `email_verified_at`, both with Sonner toasts
- [ ] User detail shows data + workspaces widget with roles and cross-links
- [ ] Workspace detail shows metadata + members widget, NO financial data anywhere in props
- [ ] `DropdownButton` is a shared reusable component (no local coupling)
- [ ] `type` is not mass-assignable; new signups are always `customer`
- [ ] `composer quality` and `npm run quality` pass
- [ ] All PHPUnit smoke + feature tests and Cypress E2E specs pass in CI
