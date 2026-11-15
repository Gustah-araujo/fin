# Admin Backoffice — Tasks

**Story ID:** ADMB-01..11
**Spec:** `.specs/features/admin-backoffice/spec.md`
**Design:** `.specs/features/admin-backoffice/design.md`

TDD-first. Cada task lista teste + gate. Ordem topológica: base backend (enum/migration/middleware) → auth/shared → UI compartilhada (layout/dropdown) → dashboard → usuários → workspaces → E2E + gates.

---

## Fase 0 — Backend: Base (enum, migration, middleware, rotas)

### T0.1 — `UserType` + migration `type` (TDD)

**Testes primeiro:** `tests/Feature/Admin/UserTypeTest.php`
- `test_new_users_default_to_customer_type`
- `test_existing_users_backfilled_to_customer` (migration roda no refresh — usuários de teste criados antes do alter? simplificar: `test_default_value_is_customer_on_schema`)
- `test_type_is_not_mass_assignable` (`User::create([... 'type' => 'admin'])` → segue `customer`; `fill()` idem)
- `test_manual_db_update_persists_as_admin` (`forceFill + save`)
- `test_type_enum_cast_returns_user_type` (cast para enum + `label()` pt-BR "Administrador"/"Cliente")

**Implementação:**
- `app/Enums/UserType.php` (string-backed, `label()`, espelha `WorkspaceRole`).
- Migration `*_add_type_to_users_table.php`: `string('type')->default('customer')->after('avatar')`.
- `app/Models/User.php`: cast `'type' => UserType::class`; **não** adicionar ao `#[Fillable]`.

**Gate:** `php artisan test --filter=UserTypeTest` verde; `composer quality`.

### T0.2 — Middleware `EnsureUserIsAdmin` + grupo de rotas `/admin` (TDD)

**Testes primeiro:** `tests/Feature/Admin/AuthorizationTest.php`
- `test_customer_gets_403_on_admin_dashboard`
- `test_customer_gets_403_on_admin_users_index`
- `test_customer_gets_403_on_admin_action_post` (POST `users/{user}/verify` stub → 403 antes do controller)
- `test_unauthenticated_redirects_to_login`
- `test_unverified_admin_redirects_to_verification_notice`
- `test_admin_with_zero_workspaces_gets_200_on_admin_dashboard`

**Implementação:**
- `app/Http/Middleware/EnsureUserIsAdmin.php` (compara com `UserType::Admin`, `abort(403)`).
- Alias `ensure.admin` em `bootstrap/app.php`.
- `routes/web.php`: grupo `['auth','verified','ensure.admin']` + prefix `admin` + name `admin.` com as 7 rotas do design (controllers podem ser stubs retornando response mínima; cada task seguinte substitui o stub).
- **Regra:** `users.datatable` registrado antes de `users/{user}`.

**Gate:** `php artisan test --filter=AuthorizationTest` verde; `composer quality`.

---

## Fase 1 — Backend: Redirect pós-login + shared `type`

### T1.1 — Redirect admin + `type` no shared `auth.user` (TDD)

**Testes primeiro:** `tests/Feature/Admin/AuthRedirectTest.php`
- `test_verified_admin_redirects_to_admin_dashboard_after_login`
- `test_verified_customer_keeps_workspace_select_redirect`
- `test_unverified_user_redirects_to_verification_notice_regardless_of_type`
- `test_inertia_shares_user_type` (GET em rota Inertia autenticada → shared props `auth.user.type`)

**Implementação:**
- `AuthenticatedSessionController::store()`: branch `type === UserType::Admin` → `redirect()->route('admin.dashboard')` após o check `hasVerifiedEmail()`.
- `HandleInertiaRequests`: `auth.user` ganha `'type' => $user->type->value`.

**Gate:** `php artisan test --filter=AuthRedirectTest` verde; suíte Auth existente não regrediu (`php artisan test --filter=Auth`).

---

## Fase 2 — Frontend: UI compartilhada

### T2.1 — Hook `useFlashToasts` + refatoração `AuthenticatedLayout`

**Implementação:**
- `resources/js/hooks/use-flash-toasts.ts`: lógica de flash/toast movida **verbatim** de `AuthenticatedLayout.tsx` (incl. `consumedFlashRef`, `dispatchToast`, `router.on('before')` → `toast.dismiss()`).
- `AuthenticatedLayout.tsx` consome o hook (comportamento idêntico).

**Gate:** `npx tsc --noEmit` limpo; `npm run quality`; `npm run build`; Cypress smoke de toasts existente (despesas/convites) verde.

### T2.2 — `AdminLayout` + smoke do shell

**Testes primeiro:** `tests/Feature/Admin/AdminLayoutSmokeTest.php` (ou integrado ao smoke do dashboard em T3.1 — escolher um home para o smoke)
- Admin visita rota admin placeholder → `assertOk` + `assertInertia(component)`.

**Implementação:**
- `resources/js/Layouts/AdminLayout.tsx`: header "Fin · Backoffice Admin", sidebar 3 itens (`data-testid="admin-sidebar-*"`), ativo por `url`, botão "Voltar para o App" → `route('workspace.select')`, `<Toaster position="top-right" closeButton />`, `useFlashToasts()`.
- **Sem** `useWorkspace()`/switcher.

**Gate:** `npm run quality`; `npm run build`.

### T2.3 — Componente `DropdownButton`

**Implementação:**
- `resources/js/Components/DropdownButton.tsx`: props `{label?, icon?, variant?, items: DropdownItem[]}`; `DropdownItem {label, onSelect, destructive?, disabled?}`; compõe shadcn `DropdownMenu` + `Button` (sem editar `Components/ui/*`).

**Gate:** `npx tsc --noEmit`; `npm run quality`; `npm run build` (validação de comportamento via Cypress em Fase 4/6).

---

## Fase 3 — Dashboard (`/admin`)

### T3.1 — `AdminDashboardController` + página Dashboard (TDD)

**Testes primeiro:** `tests/Feature/Admin/DashboardTest.php`
- `test_widget_counts_only_verified_users` (3 verificados + 2 não → prop `activeUsers` = 3)
- `test_dashboard_smoke` → `assertOk` + `assertInertia(component 'Admin/Dashboard')`

**Implementação:**
- `app/Services/Admin/AdminUserService::activeUsersCount(): int`.
- `app/Http/Controllers/Admin/AdminDashboardController::index()` → prop `activeUsers` tipada.
- Stub do T0.2 substituído. Página `resources/js/Pages/Admin/Dashboard.tsx` (widget "Total de Usuários Ativos", `AdminLayout`, shadcn `Card`).
- Frontend: `resources/js/types/admin.ts` com `UserType` e tipos admin.

**Gate:** `php artisan test --filter=DashboardTest` verde; `npm run quality`; `npm run build`.

---

## Fase 4 — Usuários (listagem, ações, detalhe)

### T4.1 — Endpoint `admin/users.datatable` + `AdminUserResource` (TDD)

**Testes primeiro:** `tests/Feature/Admin/UsersListingTest.php`
- `test_datatable_requires_admin` (customer → 403)
- `test_datatable_returns_resource_shape` (`{data, meta}`, campos `uuid/name/email/type/email_verified_at/created_at/workspaces_count`)
- `test_search_filters_by_name_or_email_case_insensitive`
- `test_filter_verified_shows_only_verified`
- `test_filter_unverified_shows_only_unverified`
- `test_sort_by_created_at_desc_default`
- `test_workspaces_count_no_n_plus_one` (com `DB::enableQueryLog` ou `whenCounted`)

**Implementação:**
- `app/Http/Resources/AdminUserResource.php`.
- `AdminUserController::datatable()`: `User::query()->withCount('workspaces')` + `DatatableConfig` (searchable name/email, `Filter::select('email_verified_at')`, sortable name/email/created_at, defaultSort created_at desc) + `DatatableService::paginate()`. Rota substitui stub.

**Gate:** `php artisan test --filter=UsersListingTest` verde; `composer quality`.

### T4.2 — Página de listagem de usuários

**Implementação:**
- `resources/js/Pages/Admin/Users/Index.tsx`: `DataTable` (endpoint `route('admin.users.datatable')`), colunas com `cell` customizadas (Badge Verificado/Pendente; coluna Ações com link Visualizar + `DropdownButton` condicional apenas para `email_verified_at === null`).
- Ações do dropdown: POST via `useForm({})` → `route('admin.users.resend-verification', user)` / `route('admin.users.verify', user)` com `{preserveScroll: true, onSuccess: bumpReload}` (padrão `reloadTrigger` de `Transactions/Index.tsx`).
- Filtro de verificação como select (`Todos/Verificados/Não Verificados`).
- `AdminUserController::index()` → shell Inertia (componente `Admin/Users/Index`).

**Gate:** `npm run quality`; `npm run build`; smoke PHPUnit da rota (em T5.4).

### T4.3 — Ações de suporte: Service + endpoints (TDD)

**Testes primeiro:** `tests/Feature/Admin/UserActionsTest.php`
- `test_resend_sends_verification_email_synchronously` (`Mail::fake()` + assert 1 e-mail para o usuário; sem `Queue::fake` → prova sync do `notifyNow`)
- `test_resend_flashes_success_toast_message` (`assertSessionHas('success', ...)`)
- `test_resend_is_throttled` (6ª requisição em 1min → 429)
- `test_manual_verify_fills_email_verified_at` + flash
- `test_resend_on_verified_user_returns_422` (guard defensivo)
- `test_manual_verify_on_verified_user_returns_422`
- `test_actions_rejected_for_customers_403`

**Implementação:**
- `AdminUserService::resendVerification()` / `manuallyVerify()` com guard `ValidationException` (422).
- `AdminUserController::resendVerification()` / `verify()`: service + `Toast::success(...)` + `back()`. Rotas com `throttle:6,1` no resend (Q7).

**Gate:** `php artisan test --filter=UserActionsTest` verde; `composer quality`.

### T4.4 — Detalhe do usuário (TDD)

**Testes primeiro:** `tests/Feature/Admin/UserDetailTest.php`
- `test_detail_shows_user_data` (nome, e-mail, tipo, verificação, cadastro — via `assertInertia` props)
- `test_detail_lists_workspaces_with_roles` (2 workspaces, roles admin/editor, links uuid)
- `test_detail_empty_state_for_zero_workspaces`
- `test_detail_smoke` → `assertOk` + component `Admin/Users/Show`

**Implementação:**
- `AdminUserController::show()`: `AdminUserResource` + `WorkspaceResource::collection($user->workspaces()->withPivot('role')->get())`.
- Página `resources/js/Pages/Admin/Users/Show.tsx` (dados + widget "Workspaces do Usuário" com roles + links; ações de suporte idem listing quando não verificado).

**Gate:** `php artisan test --filter=UserDetailTest` verde; `npm run quality`; `npm run build`.

---

## Fase 5 — Workspaces (listagem + detalhe)

### T5.1 — `AdminWorkspaceController` + recursos + páginas (TDD)

**Testes primeiro:** `tests/Feature/Admin/WorkspacesListingTest.php` + `tests/Feature/Admin/WorkspaceDetailTest.php`
- Listing: `test_index_requires_admin_403`; `test_index_lists_all_workspaces_with_members_count` (3 workspaces 1/2/5 membros, eager `withCount`); smoke component `Admin/Workspaces/Index`.
- Detail: `test_detail_shows_metadata_only` (nome + data; **sem** owner); `test_detail_lists_members_with_roles_and_links`; `test_detail_props_contain_no_financial_keys` (assertInertia sem accounts/transactions/balances); smoke component `Admin/Workspaces/Show`.

**Implementação:**
- `app/Http/Resources/AdminWorkspaceResource.php`.
- `AdminWorkspaceController::index()` (`withCount('members')`, desc) e `show()` (metadata + `MemberResource::collection($workspace->members()->withPivot('role','created_at')->get())`).
- Páginas `resources/js/Pages/Admin/Workspaces/{Index,Show}.tsx` (lista simples com botão Visualizar; detalhe com widget "Usuários do Workspace").

**Gate:** `php artisan test --filter=Workspace` (admin) verde; `composer quality`; `npm run quality`; `npm run build`.

### T5.2 — AppHeader: botão "Área Admin" + smoke rotas completas

**Implementação:**
- `AppHeader.tsx`: "Área Admin" → `/admin` **somente** se `auth.user?.type === 'admin'` (customer: não renderiza).
- `resources/js/types` shared: `type` no usuário compartilhado.
- Smoke PHPUnit final das 5 rotas GET (`admin.dashboard`, `admin.users.index`, `admin.users.show`, `admin.workspaces.index`, `admin.workspaces.show`) — consolidar os smokes já criados; complementar o que faltar.

**Gate:** smoke suite verde; `npm run quality`; `npm run build`.

---

## Fase 6 — E2E + Gates finais

### T6.1 — Endpoint `testing.create-admin` + comando Cypress

**Testes primeiro:** `tests/Feature/Admin/TestingCreateAdminTest.php`
- `test_creates_verified_admin_and_logs_in` (200, autenticado, `type=admin`)
- `test_promotes_existing_user` (idempotente)
- `test_unavailable_in_production` (`Config::set`/env production → 404)

**Implementação:**
- `routes/web.php`: bloco não-produção no nível raiz, `POST /testing/create-admin` (`{email, name, password}` → firstOrCreate + forceFill type/verified + `Auth::login`).
- `cypress/support/commands.js`: `cy.createAdminSession(email?)` — `cy.session` + CSRF de `meta[name="csrf-token"]` (via `cy.request('/login')`) + POST + `cy.visit('/admin')`.

**Gate:** `php artisan test --filter=TestingCreateAdmin` verde.

### T6.2 — Cypress E2E admin (3 specs)

**Testes:**
- `cypress/e2e/admin/backoffice.cy.js` — smoke `/admin` no topo; login via `cy.createAdminSession()` → land em `/admin` → widget visível → sidebar navega (Dashboard/Usuários/Workspaces) → "Área Admin" ↔ "Voltar para o App".
- `cypress/e2e/admin/users.cy.js` — smoke; filtro "Não Verificados" → dropdown na linha → "Reenviar e-mail de confirmação" (toast + Mailpit captura 1 e-mail) → "Marcar como verificado manualmente" (toast + status atualiza); usuários verificados não exibem dropdown.
- `cypress/e2e/admin/cross-navigation.cy.js` — smoke; user detail → link workspace → workspace detail → link user (ida e volta).

**Gate:** Cypress E2E admin verde (demo server rodando).

### T6.3 — Gates finais + documentação

- `php artisan test` (suíte inteira) verde
- `composer quality` verde
- `npm run quality` verde
- `npm run build` verde
- Cypress completo verde
- ROADMAP/STATE atualizados (decisões D-86+)

---

## Dependências

```
T0.1 ─ T0.2 ─ T1.1
              │
        T2.1 ─ T2.2 ─ T2.3
              │       │
             T3.1     │
              │       │
        ┌─────┼───────┼─────────┐
       T4.1  T4.2 (após T4.1+T2.3)
             T4.3 (após T0.2)   T4.4 (após T4.1+T2.2)
             T5.1 (após T2.2)
              │
             T5.2
              │
        T6.1 ─ T6.2 ─ T6.3
```

- T4.1, T4.3, T5.1 são paralelizáveis entre si após T2.2/T2.3 (T4.3 só precisa de T0.2).
- T4.2 depende de T4.1 + T2.3; T4.4 depende de T4.1 + T2.2.
- T6.2 depende de todas as páginas + T6.1.

---

## Notas de implementação

- **`notifyNow`**: usar `Illuminate\Auth\Notifications\VerifyEmail` com `$user->notifyNow(...)` — garante envio síncrono independente de `QUEUE_CONNECTION` (AC ADMB-08.1).
- **Guard 422**: `ValidationException::withMessages(['user' => '...'])` → HTTP 422 automático; UI não oferece as ações para verificados (Q5).
- **Ordem de rotas**: `users.datatable` antes de `users/{user}`; `workspaces` nunca conflita.
- **Sem estado de sessão** para tabelas admin (v1) — não integrar `PersistsTableState` nem mexer em `DatatableStateController`.
- **`data-testid`**: todos os elementos navegáveis do admin (`admin-sidebar-*`, botões de ação, badges) seguem o padrão Cypress do projeto.
- **Smoke PHPUnit**: consolidar em `tests/Feature/Admin/` — 5 rotas GET com `assertOk()` + `assertInertia(component)`.
- **pt-BR na UI, inglês no código** (D-20): labels "Usuários", "Workspaces", "Verificado"/"Pendente", toasts conforme spec.
