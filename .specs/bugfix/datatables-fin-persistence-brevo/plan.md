# Plan: [Bugfix] DataTables, Persistência de Conversas do Fin e Mail Transport Brevo

Issue: [Bugfix] Ajustes em DataTables, Persistência de Conversas do Fin e Mail Transport Brevo

## Diagnoses

1. **DataTable "Limpar Filtros" → modal JSON cru**
   - Pages (`Transactions/Incomes/Recurrences Index.tsx`) call `clearState.delete(route('datatable.state.destroy', ...))` via Inertia `useForm()`.
   - `app/Http/Controllers/DatatableStateController.php::destroy` returns `response()->json(['message' => 'Estado limpo.'])` → no `X-Inertia` header → Inertia core `handleNonInertiaResponse()` shows its debug dialog with the raw JSON body.
   - Fix: controller must return a valid Inertia response (`redirect()->back()`), keeping the Inertia flow.

2. **Fin "Nova conversa" persists empty conversation**
   - `resources/js/Pages/Fin/Index.tsx::handleCreate` POSTs `{}` to `fin.conversations.store` immediately → `FinAssistantService::createConversation` persists an empty "Nova conversa" row.
   - Fix: click is a pure local state reset (no-op if already in draft mode); conversation is created by the first message.

3. **Brevo mail transport regression**
   - `config/mail.php` has the `brevo` mailer entry; `symfony/brevo-mailer` ^7.4 is installed, but there is NO `Mail::extend('brevo', ...)` anywhere and `symfony/http-client` is not installed → `MailManager` throws `Unsupported mail transport [brevo]`.

## Tasks

### A. DataTable — Inertia-valid response on state clear
- `DatatableStateController::destroy`: return `redirect()->back()` instead of JSON.
- Feature test: DELETE `datatable/{entity}/state` asserts redirect (not JSON); invalid entity 404 unchanged.
- Cypress: scenario in `cypress/e2e/datatable/` — apply filter, click "Limpar filtros", assert no inertia error dialog and table resets.

### B. Fin — lazy conversation persistence
- Backend: `StoreFinConversationRequest` requires `content` (+ optional `attachments`, same rules as `StoreFinMessageRequest`); `FinConversationController::store` creates conversation + first message + run atomically, returns `{ conversation, run }` (201). Workspace scoping preserved via participant workspace. Keep 403 for non-members.
- Frontend: `handleCreate` = local-only reset (`activeId=null`, clear messages), no-op when `activeId === null` (draft mode guard); `handleSend` branches to `fin.conversations.store` when `activeId === null` (FormData with content/attachments), prepends returned conversation, sets run for polling. Verify `ChatArea` renders composer when `conversation` is null (empty state) so first message can be typed.
- Tests: update `FinConversationControllerTest` (store with content → conversation+message+run created scoped to workspace; without content → 422; non-member 403). Update `cypress/e2e/fin.cy.js` (multiple "Nova conversa" clicks → no sidebar items; conversation appears only after first message).

### C. Brevo — restore transport
- `composer require symfony/http-client` (^7.4).
- `AppServiceProvider::boot`: `Mail::extend('brevo', ...)` → `(new BrevoTransportFactory())->create(new Dsn('brevo+api', 'default', $config['api_key']))`.
- Feature test: resolving `Mail::mailer('brevo')->getSymfonyTransport()` returns `BrevoApiTransport` without `InvalidArgumentException` (with api key configured via `config()` in test).

## Quality gates
- `php artisan test`, `composer quality`, `npm run quality`
