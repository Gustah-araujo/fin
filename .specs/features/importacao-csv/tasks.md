# Importação CSV com IA — Tasks (Processamento Assíncrono + Polling)

**Spec**: `.specs/features/workspace-financeiro/spec.md` (P2: linhas 231–251)
**Design**: `.specs/features/importacao-csv/design.md`
**Status**: Pending

---

## Execution Plan

### Phase 1: AI Infrastructure (Sequential)

Foundation — Facade + Service para integração com LLM.

```
T1 → T2 → T3
```

### Phase 2: Import Service + Async Infrastructure (Sequential)

Core import logic + async job infrastructure.

```
T3 → T4 (service) → T4b (job + model) → T5
```

### Phase 3: Controller + Routes (Sequential)

HTTP layer on top of services.

```
T5 → T6 → T7 → T8
```

### Phase 4: Frontend (Sequential → Parallel)

UI components — pages + interactive table + polling.

```
T8 → T9 → T10
T10 complete, then:
  ├── T11 [P]
  └── T12 [P]
```

### Phase 5: E2E + Quality Gate (Sequential)

Integration verification.

```
T11, T12 complete, then:
  T13 → T14 → T15
```

---

## Task Breakdown

### T1: Config + AiService with tests

**What:** Create `config/ai.php`, `AiService` class with `parse()` method + comprehensive feature tests (mocking HTTP)
**Where:** `config/ai.php`, `app/Services/AiService.php`, `tests/Feature/Import/AiServiceTest.php`
**Depends on:** None
**Reuses:** Guzzle HTTP (já incluso no Laravel), `config()` helper
**Requirement:** AC-2 (Agnosticismo de LLM)

**Tools:**
- MCP: NONE
- Skill: NONE

**Done when:**
- `config/ai.php` exposes: `deepseek_key`, `model`, `base_url`, `timeout` (120s)
- `AiService::parse(string $csvContent, string $type): array` sends prompt to DeepSeek API and returns structured transactions
- Prompt includes: instruções de output JSON, tipo esperado (expense/income), conteúdo CSV
- Response parsing: extrai JSON da resposta, valida estrutura, normaliza campos (description, value, date, type, category_name)
- Tests mock HTTP client (não faz chamadas reais)
- Tests cover: valid CSV → structured output, invalid JSON → exception, API error → exception, empty CSV → empty array, timeout → exception
- Gate check passes: `phpunit --filter=AiServiceTest`
- Test count: ≥5 tests pass

**Verify:**
```bash
docker compose exec app php artisan test --filter=AiServiceTest
```
Expected: ≥5 tests, all passing

---

### T2: Ai Facade + ServiceProvider registration

**What:** Create `Ai` Facade class + register singleton in AppServiceProvider
**Where:** `app/Facades/Ai.php`, `app/Providers/AppServiceProvider.php`
**Depends on:** T1
**Reuses:** Laravel Facade pattern, `App::singleton()`
**Requirement:** AC-2 (Agnosticismo de LLM)

**Tools:**
- MCP: NONE
- Skill: NONE

**Done when:**
- `app/Facades/Ai.php` extends `Illuminate\Support\Facades\Facade` with `getFacadeAccessor()` returning `'ai'`
- `AppServiceProvider::boot()` registers: `$this->app->singleton('ai', fn () => new AiService(...))`
- `Ai::parse()` callable from controller/service (testar com teste rápido)
- Gate check passes: `phpunit --filter=AiServiceTest` (still green)

**Verify:**
```bash
docker compose exec app php artisan test --filter=AiServiceTest
```
Expected: all tests still passing

---

### T3: "Sem Categoria" category auto-creation

**What:** Ensure every workspace has a "Sem Categoria" system category (for import fallback)
**Where:** `app/Observers/WorkspaceObserver.php` (novo) ou `app/Providers/AppServiceProvider.php` (boot), `database/migrations/` (se necessário)
**Depends on:** T2
**Reuses:** D-31 pattern ("Pagamento de Cartão" auto-criada), `Category` model
**Requirement:** AC-4 (UX — categoria fallback)

**Tools:**
- MCP: NONE
- Skill: NONE

**Done when:**
- Ao criar workspace, categoria "Sem Categoria" (is_system=true, type=Both) é auto-criada
- Categoria é criada apenas se não existir (idempotent)
- Test: criar workspace → categoria "Sem Categoria" existe no DB
- Test: criar workspace com "Sem Categoria" já existente → não duplica
- Gate check passes: `phpunit --filter=WorkspaceCreation` (existing tests still green)

**Verify:**
```bash
docker compose exec app php artisan test --filter=Workspace
```
Expected: all workspace tests passing + new assertion

---

### T4: ImportService + ImportJob model/migration with feature tests

**What:** Create `ImportService` with `parseCsv()` and `confirm()`, plus `ImportJob` model, migration, and `ImportJobStatus` enum. Comprehensive feature tests.
**Where:** `app/Services/ImportService.php`, `app/Models/ImportJob.php`, `app/Enums/ImportJobStatus.php`, `database/migrations/2026_09_10_000002_create_import_jobs_table.php`, `tests/Feature/Import/ImportServiceTest.php`
**Depends on:** T3
**Reuses:** `AiService` (via Facade), `TransactionService`, `Category` model, `Transaction` model
**Requirement:** AC-3 (Duplicatas), AC-5 (Workspace), AC-6 (Async), AC-7 (Resiliência)

**Tools:**
- MCP: NONE
- Skill: NONE

**Done when:**

**ImportService:**
- `parseCsv(string $csvContent, string $type, Workspace $workspace): array`:
  1. Recebe conteúdo CSV como string
  2. Chama `Ai::parse($content, $type)`
  3. Para cada transação: busca categoria por nome no workspace (match exato case-insensitive, depois parcial)
  4. Se não encontra: usa "Sem Categoria"
  5. Detecta duplicatas (valor ±0.01 + data ±1 dia + descrição similar ≥80%)
  6. Retorna array com transações + summary
- `confirm(Workspace $workspace, User $creator, string $type, array $transactions): int`:
  1. Filtra apenas transações com `is_checked = true`
  2. Para cada uma: chama `TransactionService::create($workspace, $creator, $data)`
  3. Retorna quantidade criada

**ImportJob model:**
- Migration: `import_jobs` table com id (uuid), workspace_id, user_id, type, file_path, original_filename, status, result (json nullable), error_message, started_at, completed_at, timestamps
- Enum: `ImportJobStatus` (Pending, Processing, Completed, Failed)
- Model: com relationships (workspace, user), casts (status enum, result array, datetime), helper methods (isPending, isProcessing, isCompleted, isFailed, isFinished)

**Tests cover:**
- parseCsv returns correct structure
- duplicate detection flags matches
- category matching works
- "Sem Categoria" fallback
- confirm persists only checked
- confirm recalculates balance
- confirm with empty selection returns 0
- ImportJob model: status transitions, helper methods, workspace relationship
- Gate check passes: `phpunit --filter=ImportServiceTest`
- Test count: ≥10 tests pass

**Verify:**
```bash
docker compose exec app php artisan test --filter=ImportServiceTest
```
Expected: ≥10 tests, all passing

---

### T4b: ProcessImportCsvJob with tests

**What:** Create `ProcessImportCsvJob` that processes CSV in background and comprehensive tests
**Where:** `app/Jobs/ProcessImportCsvJob.php`, add tests to `tests/Feature/Import/ImportServiceTest.php` or new `tests/Feature/Import/ProcessImportCsvJobTest.php`
**Depends on:** T4
**Reuses:** `ProcessRecurrencesJob` pattern (ShouldQueue, Queueable, try/catch, Log::error), `ImportService`, `Storage`
**Requirement:** AC-6 (Async), AC-7 (Resiliência)

**Tools:**
- MCP: NONE
- Skill: NONE

**Done when:**

**Job implementation:**
- `ProcessImportCsvJob` implements `ShouldQueue` + `Queueable`
- Constructor: recebe `importJobUuid` (string)
- `$tries = 3`, `$timeout = 300` (5 minutos)
- `handle()`:
  1. Busca ImportJob por UUID
  2. Atualiza status para Processing + started_at
  3. Lê arquivo CSV do Storage (disk: local)
  4. Chama `ImportService::parseCsv(content, type, workspace)`
  5. Atualiza ImportJob: status=Completed, result=preview, completed_at=now
  6. Em caso de erro: catch → Log::error → status=Failed, error_message=user-friendly, completed_at=now → re-throw
- `failed()` method: garante status=Failed mesmo em crash
- User-friendly error messages para timeout, JSON inválido, erros genéricos

**Tests cover:**
- Job updates ImportJob from pending → processing → completed on success
- Job stores preview result in ImportJob on success
- Job updates ImportJob to failed when AI throws exception
- Job stores error_message on failure
- Job calls ImportService::parseCsv with correct parameters
- Job reads file from Storage with correct path
- Job has correct retry/timeout configuration
- Gate check passes: `phpunit --filter=ProcessImportCsvJobTest` (or test count includes job tests)

**Verify:**
```bash
docker compose exec app php artisan test --filter=ProcessImportCsvJob
```
Expected: ≥6 tests, all passing

*Nota: Como `QUEUE_CONNECTION=sync` nos testes, o job roda sincronamente ao ser despachado, permitindo teste direto sem mock de fila.*

---

### T5: FormRequests

**What:** Create `UploadCsvRequest` and `ConfirmImportRequest` with validation rules
**Where:** `app/Http/Requests/UploadCsvRequest.php`, `app/Http/Requests/ConfirmImportRequest.php`
**Depends on:** T4b
**Reuses:** `StoreTransactionRequest` pattern, workspace validation via `withValidator`
**Requirement:** AC-1 (Isolamento), AC-5 (Workspace)

**Tools:**
- MCP: NONE
- Skill: NONE

**Done when:**
- `UploadCsvRequest`: `file` (required, file, mimes:csv,txt, max:10240), `account_id` (required, exists:accounts,uuid)
- `UploadCsvRequest::withValidator`: valida que account pertence ao workspace
- `ConfirmImportRequest`: `type` (required, in:expense,income), `transactions` (required, array), `transactions.*.description` (required, string, max:255), `transactions.*.value` (required, numeric, gt:0), `transactions.*.date` (required, date), `transactions.*.category_id` (required, exists:categories,uuid), `transactions.*.is_checked` (required, boolean)
- `ConfirmImportRequest::withValidator`: valida que categorias pertencem ao workspace
- Messages em pt-BR
- Gate check passes: `phpunit` (no new failures)

**Verify:**
```bash
docker compose exec app php artisan test --filter=Import
```
Expected: all import tests passing

---

### T6: ImportControllerTest (TDD red)

**What:** Write controller feature tests FIRST (TDD red phase) — including store + status + confirm
**Where:** `tests/Feature/Import/ImportControllerTest.php`
**Depends on:** T5
**Reuses:** `IncomeController` test pattern (actingAs, authorize, inertia assertions, Toast assertions)
**Requirement:** AC-1, AC-2, AC-3, AC-4, AC-5, AC-6, AC-7

**Tools:**
- MCP: NONE
- Skill: NONE

**Done when:**

**Tests for `store` (POST):**
- Test: accepts valid CSV file → returns 201 with `{job_uuid, status: "pending"}`
- Test: creates ImportJob record in database with correct workspace/user/type
- Test: stores file in storage
- Test: dispatches ProcessImportCsvJob
- Test: rejects invalid file (not CSV) → 422
- Test: rejects non-workspace account → 422
- Test: rejects file > 10MB → 422

**Tests for `status` (GET):**
- Test: returns status=pending for new job
- Test: returns status=processing while job runs
- Test: returns status=result with preview when completed (queue=sync)
- Test: returns error_message when failed
- Test: rejects non-workspace member → 403
- Test: rejects job from different workspace → 404

**Tests for `confirm` (POST):**
- Test: persists checked transactions → redirect + toast success
- Test: rejects invalid category → 422
- Test: with all unchecked → no transactions created
- Test: type isolation: expense route creates only expenses

**Tests for `create` (GET):**
- Test: returns Inertia page with accounts + categories + type
- Test: non-member → 403

- Gate check shows tests FAIL (red phase — expected, controller doesn't exist yet)
- Test count: ≥15 tests exist (all red)

**Verify:**
```bash
docker compose exec app php artisan test --filter=ImportControllerTest
```
Expected: ≥15 tests, all FAIL (controller not yet created)

---

### T7: ImportController + Resources

**What:** Implement controller, ImportPreviewResource, and ImportJobResource to make T6 tests green
**Where:** `app/Http/Controllers/ImportController.php`, `app/Http/Resources/ImportPreviewResource.php`, `app/Http/Resources/ImportJobResource.php`
**Depends on:** T6
**Reuses:** `IncomeController` structure (authorize, inertia, props, Toast), `TransactionResource` pattern
**Requirement:** AC-1, AC-2, AC-3, AC-4, AC-5, AC-6, AC-7

**Tools:**
- MCP: NONE
- Skill: NONE

**Done when:**
- `create(Workspace $workspace)` returns Inertia with: type (from route), accounts, categories (filtered by type)
- `store(UploadCsvRequest $request, Workspace $workspace)`:
  1. Valida upload
  2. Armazena arquivo em `imports/{uuid}/{filename}`
  3. Cria ImportJob (status=pending)
  4. Despacha ProcessImportCsvJob
  5. Retorna `{job_uuid, status}` com HTTP 201
- `status(Workspace $workspace, ImportJob $importJob)`:
  1. Verifica autorização + pertencimento ao workspace
  2. Retorna `{uuid, status, result?, error_message?}`
- `confirm(ConfirmImportRequest $request, Workspace $workspace)`:
  1. Chama `$service->confirm()`
  2. Redirect com Toast de sucesso
- Controller usa Policy authorization (`viewAny`/`create` on Transaction with workspace)
- Type determined from route name (transactions.import.* → expense, incomes.import.* → income)
- `ImportPreviewResource` formats: transactions array + summary
- `ImportJobResource` formats: uuid, status, result, error_message
- Gate check passes: `phpunit --filter=ImportControllerTest`
- Test count: ≥15 tests pass (T6 tests now green)

**Verify:**
```bash
docker compose exec app php artisan test --filter=ImportControllerTest
```
Expected: ≥15 tests passing

---

### T8: Routes + Import buttons on listing pages

**What:** Register import routes (8 total, incl. status) and add import buttons to Transactions/Index and Incomes/Index
**Where:** `routes/web.php`, `resources/js/Pages/Transactions/Index.tsx`, `resources/js/Pages/Incomes/Index.tsx`
**Depends on:** T7
**Reuses:** Existing route patterns, existing page structure
**Requirement:** AC-1 (Isolamento de tipos)

**Tools:**
- MCP: NONE
- Skill: NONE

**Done when:**
- 8 routes registered:
  - `transactions.import.create` (GET), `.store` (POST), `.status` (GET), `.confirm` (POST)
  - `incomes.import.create` (GET), `.store` (POST), `.status` (GET), `.confirm` (POST)
- All routes inside `auth` + `verified` + `prefix('w/{workspace}')` middleware group
- `Transactions/Index.tsx` has "Importar Despesas" button → links to `transactions.import.create`
- `Incomes/Index.tsx` has "Importar Receitas" button → links to `incomes.import.create`
- Buttons use `variant="outline"` (secondary to primary create button)
- `route:list` shows all 8 import routes
- Gate check passes: `php artisan route:list | grep import`

**Verify:**
```bash
docker compose exec app php artisan route:list --name=import
```
Expected: 8 routes listed with correct controller bindings

---

### T9: TypeScript types

**What:** Define import TypeScript interfaces (including async/polling types)
**Where:** `resources/js/types/import.ts`
**Depends on:** T8
**Reuses:** Existing type patterns (`resources/js/types/`)
**Requirement:** AC-4 (UX), AC-6 (Async)

**Tools:**
- MCP: NONE
- Skill: NONE

**Done when:**
- `ImportType`: 'expense' | 'income'
- `ImportJobStatus`: 'pending' | 'processing' | 'completed' | 'failed'
- `ImportTransaction`: id, description, value, date, type, category_name, category_uuid, is_duplicate, duplicate_uuid, is_checked
- `ImportSummary`: total, total_value, duplicates, checked
- `ImportPreviewResponse`: type, transactions, summary
- `ImportJobStartResponse`: job_uuid, status
- `ImportJobStatusResponse`: uuid, status, result?, error_message?
- `CsvUploadFormData`: file, account_id
- All types exported
- No TypeScript errors
- Gate check passes: `npm run build` (no new TS errors)

**Verify:**
```bash
npx tsc --noEmit --skipLibCheck 2>&1 | grep -i import || echo "OK"
```
Expected: "OK" (no import-related errors)

---

### T10: CsvUploadForm + ImportProcessing + useImportPolling hook

**What:** Create upload form component, processing indicator component, and polling hook
**Where:** `resources/js/Components/Import/CsvUploadForm.tsx`, `resources/js/Components/Import/ImportProcessing.tsx`, `resources/js/hooks/use-import-polling.ts`
**Depends on:** T9
**Reuses:** shadcn Button/Input/Label/Select/Progress, `useForm`, `useWorkspace`, axios (datatable pattern)
**Requirement:** AC-4 (UX), AC-6 (Async)

**Tools:**
- MCP: NONE
- Skill: NONE

**Done when:**

**CsvUploadForm:**
- Props: `type`, `accounts`, `onJobStarted` (callback com job_uuid)
- File input accepts `.csv` (accept=".csv,text/csv")
- Select de conta (obrigatório, lista accounts do workspace)
- Botão "Processar" desabilitado sem arquivo selecionado
- POST para store → sucesso → chama `onJobStarted(job_uuid)`
- Erro de validação exibido via toast (sonner)
- Validação client-side: arquivo selecionado antes de enviar

**ImportProcessing:**
- Props: `status`, `errorMessage`, `onRetry`, `onCancel`
- Spinner/animation durante pending/processing
- Progress bar indeterminada
- Mensagem: "Processando arquivo..." durante pending/processing
- Estado de erro: mostra mensagem + botão "Tentar novamente"
- Mensagens em pt-BR

**useImportPolling hook:**
- Params: `jobUuid`, `workspaceId`, `onCompleted`, `onError`, `intervalMs` (default 2000), `timeoutMs` (default 300000)
- Returns: `{ status, result, error, isLoading }`
- Polls GET status endpoint a cada `intervalMs`
- Para polling quando status=completed/failed
- Timeout após `timeoutMs` com mensagem apropriada
- Cleanup de interval/timeout no unmount

- Gate check passes: `npm run quality` (ESLint + Prettier clean)

**Verify:**
```bash
npm run lint -- --max-warnings 0 resources/js/Components/Import/CsvUploadForm.tsx resources/js/Components/Import/ImportProcessing.tsx resources/js/hooks/use-import-polling.ts
```
Expected: No errors

---

### T11: ImportPreviewTable component [P]

**What:** Create interactive preview table with inline editing, checkboxes, and duplicate alerts
**Where:** `resources/js/Components/Import/ImportPreviewTable.tsx`
**Depends on:** T9
**Reuses:** shadcn Table/Checkbox/Input/Select/Alert/Badge, `formatCurrency`, `useWorkspace`
**Requirement:** AC-3 (Duplicatas), AC-4 (UX)

**Tools:**
- MCP: NONE
- Skill: NONE

**Done when:**
- Props: `transactions`, `categories`, `onTransactionChange`, `onToggle`, `summary`
- Colunas: checkbox (is_checked), descrição (editável inline), valor (editável inline), data (editável inline), categoria (select), alerta duplicata
- Linhas duplicata: highlight (bg-destructive/10) + pré-desmarcada (is_checked=false) + badge "Possível duplicata"
- Edição inline: Input para descrição/valor/data, Select para categoria
- Summary footer: "X transações, R$ Y, Z possíveis duplicatas, W selecionadas"
- Botão "Importar W transações" habilitado apenas se W > 0
- Botão "Cancelar" descarta tudo (volta para upload)
- Gate check passes: `npm run quality` (ESLint + Prettier clean)

**Verify:**
```bash
npm run lint -- --max-warnings 0 resources/js/Components/Import/ImportPreviewTable.tsx
```
Expected: No errors

---

### T12: Imports/Index.tsx page [P]

**What:** Main import page orchestrating upload → processing → preview → confirm flow
**Where:** `resources/js/Pages/Imports/Index.tsx`
**Depends on:** T9
**Reuses:** AuthenticatedLayout, `CsvUploadForm`, `ImportProcessing`, `ImportPreviewTable`, `useWorkspace`, `useForm`, `useImportPolling`, sonner
**Requirement:** AC-1, AC-3, AC-4, AC-5, AC-6

**Tools:**
- MCP: NONE
- Skill: NONE

**Done when:**
- Props: `type`, `accounts`, `categories` (from controller)
- Estado: `stage` ('upload' | 'processing' | 'preview'), `jobUuid`, `previewData`
- Stage 'upload': renderiza `CsvUploadForm` → onJobStarted → muda para 'processing'
- Stage 'processing': renderiza `ImportProcessing` com `useImportPolling` → onCompleted → muda para 'preview' / onError → mostra erro com retry
- Stage 'preview': renderiza `ImportPreviewTable` → onConfirm → POST para confirm
- Confirmação: toast sucesso → redirect para listagem (transactions.index ou incomes.index)
- Cancelamento: volta para 'upload' (descarta preview)
- Título dinâmico: "Importar Despesas" ou "Importar Receitas" baseado no tipo
- Gate check passes: `npm run quality` (ESLint + Prettier clean)

**Verify:**
```bash
npm run lint -- --max-warnings 0 resources/js/Pages/Imports/Index.tsx
```
Expected: No errors

---

### T13: Smoke tests

**What:** Create PHPUnit smoke tests for import GET routes
**Where:** `tests/Feature/Import/ImportSmokeTest.php`
**Depends on:** T12
**Reuses:** Existing smoke test pattern (`WorkspaceSmokeTest`)
**Requirement:** AC-1, AC-5

**Tools:**
- MCP: NONE
- Skill: NONE

**Done when:**
- Test: GET /transactions/import → 200 + Inertia component 'Imports/Index'
- Test: GET /incomes/import → 200 + Inertia component 'Imports/Index'
- Test: non-member → 403
- Gate check passes: `phpunit --filter=ImportSmokeTest`

**Verify:**
```bash
docker compose exec app php artisan test --filter=ImportSmokeTest
```
Expected: ≥3 tests passing

---

### T14: Cypress E2E test

**What:** End-to-end test covering critical import user journey (including async processing)
**Where:** `cypress/e2e/import.cy.ts`
**Depends on:** T13
**Reuses:** Existing Cypress patterns (`cy.loginViaSession`, `cy.assertToast`, `cy.intercept`)
**Requirement:** AC-1, AC-3, AC-4, AC-6

**Tools:**
- MCP: NONE
- Skill: NONE

**Done when:**
- Test: Full journey — login → access import page → upload CSV (fixture) → see processing indicator → wait for preview → edit one field → confirm → redirect + toast → transactions in DB
- Test: Processing state visible — upload valid CSV → "Processando..." visible → eventually preview appears
- Test: Duplicate detection — upload with duplicates → see flagged rows pre-unchecked
- Test: Cancel — upload → cancel → no transactions created
- Test: Error — upload invalid file → see error toast
- Test: Type isolation — expense import creates only expenses
- Test: Polling recovery — simulate slow processing → preview eventually appears
- Gate check passes: `cypress run --spec cypress/e2e/import.cy.ts`

**Verify:**
```bash
npx cypress run --spec cypress/e2e/import.cy.ts
```
Expected: All scenarios pass

---

### T15: Quality gate + final verification

**What:** Run all quality gates and verify traceability
**Where:** N/A (verification only)
**Depends on:** T14
**Reuses:** Quality commands from D-51
**Requirement:** All

**Tools:**
- MCP: NONE
- Skill: NONE

**Done when:**
- `composer quality` passes (Pint format check + PHPMD)
- `npm run quality` passes (ESLint complexity + Prettier)
- `phpunit --filter=Import` passes (≥48 tests across all import test files)
- `npm run build` passes
- `cypress run --spec cypress/e2e/import.cy.ts` passes
- spec.md traceability updated: IMPT-01 mapped to tasks
- ROADMAP.md updated: IMPT-01 status → 🟢
- STATE.md updated: decisões D-66, D-67, D-68, D-69, D-70 registradas

**Verify:**
```bash
composer quality && npm run quality
docker compose exec app php artisan test --filter=Import
npm run build
npx cypress run --spec cypress/e2e/import.cy.ts
```
Expected: All gates green

---

## Parallel Execution Map

```
Phase 1 (Sequential):
  T1 ──→ T2 ──→ T3

Phase 2 (Sequential):
  T3 ──→ T4 (ImportService + model/migration) ──→ T4b (ProcessImportCsvJob) ──→ T5

Phase 3 (Sequential):
  T5 ──→ T6 ──→ T7 ──→ T8

Phase 4 (Sequential → Parallel):
  T8 ──→ T9 ──→ T10
  T10 complete, then:
    ├── T11 [P] (ImportPreviewTable)
    └── T12 [P] (Imports/Index.tsx)

Phase 5 (Sequential):
  T11, T12 complete, then:
    T13 ──→ T14 ──→ T15
```

---

## Task Granularity Check

| Task | Scope | Status |
|------|-------|--------|
| T1: Config + AiService + tests | 2 files + 1 test file | ⚠️ OK — cohesive (service + config + tests) |
| T2: Ai Facade + registration | 2 small changes | ✅ Granular |
| T3: "Sem Categoria" auto-creation | 1 observer + logic | ✅ Granular |
| T4: ImportService + ImportJob model + migration + tests | 4 files + 1 test file | ⚠️ OK — cohesive (service layer + infra) |
| T4b: ProcessImportCsvJob + tests | 1 job file + tests | ✅ Granular |
| T5: FormRequests | 2 files | ✅ Granular |
| T6: Controller tests (red) | 1 test file | ✅ Granular |
| T7: Controller + Resources | 3 related files | ⚠️ OK — cohesive (HTTP layer) |
| T8: Routes + buttons | 3 small changes | ⚠️ OK — wiring task |
| T9: TypeScript types | 1 file | ✅ Granular |
| T10: CsvUploadForm + ImportProcessing + polling hook | 3 files | ⚠️ OK — cohesive (upload flow) |
| T11: ImportPreviewTable | 1 component | ✅ Granular |
| T12: Imports/Index page | 1 page | ✅ Granular |
| T13: Smoke tests | 1 test file | ✅ Granular |
| T14: Cypress E2E | 1 test file | ✅ Granular |
| T15: Quality gate | Verification only | ✅ Granular |

---

## Diagram-Definition Cross-Check

| Task | Depends On (task body) | Diagram Shows | Status |
|------|------------------------|---------------|--------|
| T1 | None | No incoming arrows | ✅ Match |
| T2 | T1 | T1 ──→ T2 | ✅ Match |
| T3 | T2 | T2 ──→ T3 | ✅ Match |
| T4 | T3 | T3 ──→ T4 | ✅ Match |
| T4b | T4 | T4 ──→ T4b | ✅ Match |
| T5 | T4b | T4b ──→ T5 | ✅ Match |
| T6 | T5 | T5 ──→ T6 | ✅ Match |
| T7 | T6 | T6 ──→ T7 | ✅ Match |
| T8 | T7 | T7 ──→ T8 | ✅ Match |
| T9 | T8 | T8 ──→ T9 | ✅ Match |
| T10 | T9 | T9 ──→ T10 | ✅ Match |
| T11 [P] | T10 | T10 ──→ T11 | ✅ Match |
| T12 [P] | T10 | T10 ──→ T12 | ✅ Match |
| T13 | T12 | T12 ──→ T13 | ✅ Match |
| T14 | T13 | T13 ──→ T14 | ✅ Match |
| T15 | T14 | T14 ──→ T15 | ✅ Match |

**Parallel check:** T11 and T12 both depend on T10 only — no mutual dependencies → `[P]` valid ✅

---

## Test Co-location Validation

| Task | Code Layer | Project Convention | Task Says | Status |
|------|------------|-------------------|-----------|--------|
| T1: AiService | Service | Feature test (D-10) | Feature tests included | ✅ OK |
| T2: Ai Facade | Facade | No test required | No tests | ✅ OK |
| T3: Sem Categoria | Observer/Model | Feature test (D-10) | Tests included | ✅ OK |
| T4: ImportService + ImportJob | Service/Model | Feature test (D-10) | Feature tests included | ✅ OK |
| T4b: ProcessImportCsvJob | Job | Feature test (D-10) | Tests included | ✅ OK |
| T5: FormRequests | Request | No test required | No tests | ✅ OK |
| T6: ControllerTest | Test | Feature test (D-10) | Tests written (TDD red) | ✅ OK |
| T7: Controller + Resources | Controller/Resource | Feature test (D-10) | Tests from T6 green | ✅ OK |
| T8: Routes + Buttons | Routes/Component | No test required | No tests | ✅ OK |
| T9: Types | Type definitions | No test required | No tests | ✅ OK |
| T10: Components + Hook | React component/hook | Cypress E2E (D-21) | E2E in T14 (deferred) | ⚠️ DEFERRED — acceptable |
| T11: ImportPreviewTable | React component | Cypress E2E (D-21) | E2E in T14 (deferred) | ⚠️ DEFERRED — acceptable |
| T12: Imports/Index | React page | Cypress E2E (D-21) | E2E in T14 (deferred) | ⚠️ DEFERRED — acceptable |
| T13: Smoke tests | Test | Smoke test (mandatory) | Smoke tests included | ✅ OK |
| T14: Cypress | E2E test | Cypress E2E (D-21) | E2E tests included | ✅ OK |
| T15: Quality gate | Verification | N/A | Verification only | ✅ OK |

**Note on T10/T11/T12 test deferral:** Per project convention (D-10, D-21), React components are verified via Cypress E2E (T14), which covers the full journey including upload form, processing state, preview table, and confirmation flow.

---

## Requirement Traceability

| Requirement | Task(s) | Status |
|-------------|---------|--------|
| AC-1: Isolamento de tipos | T5, T6, T7, T8, T12 | Pending |
| AC-2: Agnosticismo de LLM (Facade) | T1, T2, T7 | Pending |
| AC-3: Detecção de duplicatas | T4, T6, T7, T11, T12 | Pending |
| AC-4: UX (loading, editar, desmarcar, confirmar, toasts) | T3, T6, T7, T10, T11, T12 | Pending |
| AC-5: Contexto de workspace | T4, T5, T6, T7, T12, T13 | Pending |
| AC-6: Processamento assíncrono (store + status + polling) | T4, T4b, T6, T7, T9, T10, T12 | Pending |
| AC-7: Resiliência (retry + erros amigáveis) | T4, T4b, T6, T7 | Pending |

**Coverage:** 7 mapped to tasks, 0 unmapped ✅

---

## Test Count Summary

| Test File | Tests | Gate |
|-----------|-------|------|
| AiServiceTest | ≥5 | `phpunit --filter=AiServiceTest` |
| ImportServiceTest (incl. ImportJob) | ≥12 | `phpunit --filter=ImportServiceTest` |
| ProcessImportCsvJobTest | ≥8 | `phpunit --filter=ProcessImportCsvJob` |
| ImportControllerTest | ≥20 | `phpunit --filter=ImportControllerTest` |
| ImportSmokeTest | ≥3 | `phpunit --filter=ImportSmokeTest` |
| **Total PHPUnit** | **≥48** | `phpunit --filter=Import` |
| Cypress E2E | ≥7 scenarios | `cypress run --spec cypress/e2e/import.cy.ts` |

---

## Key Changes from Previous Plan (Síncrono → Assíncrono)

| Aspecto | Antes (Síncrono) | Agora (Assíncrono + Polling) |
|---------|-------------------|------------------------------|
| Endpoint `store` | Processa CSV e retorna preview diretamente | Cria ImportJob, despacha Job, retorna `job_uuid` |
| Processamento | No request HTTP (controller) | Em background (ProcessImportCsvJob) |
| Aguardo do usuário | Requisição HTTP longa (risco timeout) | Resposta imediata + loading state na UI |
| Frontend | Upload → espera → preview | Upload → loading → polling → preview |
| Novos componentes | — | ImportJob model, ProcessImportCsvJob, ImportProcessing, useImportPolling |
| Novos endpoints | — | GET /{type}/import/{job} (status/polling) |
| Novas rotas | 6 rotas | 8 rotas (+2 status) |
| Timeout config | PHP max_execution_time (30s) | Job timeout (300s) + polling timeout (300s) |
| Retry | Sem | 3 tentativas automáticas |
| Testes controller | ≥12 | ≥20 (+status endpoint tests) |
| Testes job | — | ≥8 (ProcessImportCsvJob) |
| Total testes | ≥25 | ≥48 |
