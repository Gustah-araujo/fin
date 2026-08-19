# Receitas — Design

**Story ID:** INCM-01  
**Phase:** P1 — MVP Core  
**Parent Spec:** `.specs/features/workspace-financeiro/spec.md`  
**Spec:** `.specs/features/receitas/spec.md`  

## Scope

This design covers the unified income CRUD (single and recurring), automatic recurrence generation, confirmation of receipt, recurrence instance scope edit/delete, and the dedicated recurrence management page.

P2 filters (INCM-06) are included as a design surface but can be deferred to a follow-up task if needed.

---

## 1. Backend Architecture

### 1.1 Data Model

#### `recurrences` table

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigInt PK | internal |
| `uuid` | uuid unique | route binding / frontend |
| `workspace_id` | FK workspaces | cascade on delete |
| `account_id` | FK accounts | NOT NULL; income always lands in a bank account |
| `category_id` | FK categories | NOT NULL |
| `type` | string | `income` for now; enum `TransactionType` |
| `description` | string 255 | copied to generated transactions |
| `value` | decimal 15,2 | copied to generated transactions |
| `frequency` | string | enum `RecurrenceFrequency::Weekly` / `Monthly` |
| `frequency_day` | int | 0–6 Weekly (0=Sun), 1–31 Monthly |
| `start_date` | date | the original start date (read-only once transactions exist) |
| `until_date` | date nullable | null = infinite; inclusive last occurrence |
| `next_date` | date nullable | next occurrence to generate; null = exhausted |
| `status` | string | `active` or `paused`; separate from soft-delete semantics |
| `created_by` | FK users | who created the rule |
| `timestamps` | | |
| `softDeletes` | | real deletion / "Esta e parar futuras" archive only |

Indexes: `(workspace_id, next_date)`, `(workspace_id, deleted_at, next_date)` for the job.

#### `transactions` table change

Add `recurrence_id` nullable FK to `recurrences` (uuid column, foreign keyed to `recurrences.uuid` or `id` depending on project convention). The project uses route binding on `uuid`, but FKs in migrations reference `id` (see existing migrations). We follow the existing pattern: store `recurrence_id` as `foreignId` referencing `recurrences.id` (not `uuid`). Wait — existing migrations use `foreignId` and reference `id` even though models bind on `uuid`. For consistency, the `recurrence_id` FK column references `recurrences.id`.

> Decision: The `recurrence_id` column is a `foreignId` to `recurrences.id`, consistent with existing FK columns. The frontend only ever sees `uuid`.

`Transaction` model `$fillable` updated to include `recurrence_id` and the relationship `recurrence()`.

`Transaction` model cast `type` is already `TransactionType::class`.

#### `Recurrence` model

- `HasUuids`, `SoftDeletes`, `HasFactory`
- `$fillable` includes all columns except internal `id`
- Casts: `type => TransactionType::class`, `frequency => RecurrenceFrequency::class`, `value => decimal:2`, `start_date => date`, `until_date => date`, `next_date => date`, `status => RecurrenceStatus::class`
- Route binding via `uuid`
- Relations: `workspace()`, `account()`, `category()`, `creator()`, `transactions()` (HasMany), `tags()` (MorphToMany)

#### `RecurrenceFrequency` enum

```php
enum RecurrenceFrequency: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string { ... }
    public function dayLabel(int $day): string { ... }
}
```

#### `RecurrenceStatus` enum

```php
enum RecurrenceStatus: string
{
    case Active = 'active';
    case Paused = 'paused';

    public function label(): string { ... }
}
```

### 1.2 Services

#### `RecurrenceService`

Responsibilities: create recurrence, generate next instance, update recurrence rule, recompute `next_date`, scope edit/delete of generated instances.

```php
class RecurrenceService
{
    public function create(Workspace $workspace, array $data, User $user): Recurrence;
    public function createWithFirstInstance(Workspace $workspace, array $data, User $user): array { recurrence, transaction };
    public function generateNextInstance(Recurrence $recurrence): ?Transaction; // idempotent, optimistic lock
    public function recomputeNextDate(Recurrence $recurrence): ?Carbon;
    public function updateRule(Recurrence $recurrence, array $data): Recurrence;
    public function pause(Recurrence $recurrence): void;   // sets status = Paused
    public function restore(Recurrence $recurrence): void;  // sets status = Active + recompute next_date
    public function updateThisAndFuture(Transaction $transaction, array $data, User $user): void; // dispatches ApplyRecurrenceScopeChangeJob
    public function deleteThisAndFuture(Transaction $transaction): void; // dispatches ApplyRecurrenceScopeChangeJob
    public function applyUpdateThisAndFuture(Transaction $transaction, array $data, User $user): void; // synchronous worker
    public function applyDeleteThisAndFuture(Transaction $transaction): void; // synchronous worker
    public function syncTags(Recurrence|Transaction $model, Workspace $workspace, ?array $tagUuids): void;
}
```

Generation rules (used by both scheduled job and manual "Gerar agora"):

1. If `recurrence->status != active` or `recurrence->deleted_at` or `account->deleted_at` → skip + log.
2. If `next_date` is null → skip.
3. If `until_date` set and `next_date > until_date` → set `next_date = null` and skip.
4. Compute `generationDate`:
   - If `next_date <= today` → use `next_date` (most recent due date, not backfill).
   - If `next_date` is multiple cycles in the past → generate one transaction for the most recent due date relative to today (or `until_date` if earlier), then advance `next_date` to the next future cycle. v1: no backfill.
5. Inside `DB::transaction`:
   - Create `Transaction` with copied fields from `Recurrence` + `date = generationDate`, `paid_at = null`, `recurrence_id = recurrence.id`.
   - Sync tags from recurrence to transaction via `taggable`.
   - Compute `nextNextDate` from `generationDate` using `frequency` + `frequency_day`.
   - If `until_date` and `nextNextDate > until_date` → `newNextDate = null`, else `newNextDate = nextNextDate`.
   - Optimistic lock: `UPDATE recurrences SET next_date = ? WHERE id = ? AND next_date = ?`. If affected rows = 0 → skip.

Date arithmetic:

- Weekly: `nextDate->next(Carbon::SUNDAY + frequency_day)` or `->addWeek()->dayOfWeek = frequency_day`.
- Monthly: `nextDate->addMonthNoOverflow()->day = min(frequency_day, daysInMonth)`.

#### `TransactionService` extension

`TransactionService::create()` currently hardcodes `type => 'expense'`. Refactor to accept a `type` parameter or split into `createIncome()` and `createExpense()`.

Decision: keep `create()` as generic, accepting `type` in `$data`. The caller (`IncomeController`) passes `type => Income`. Existing `TransactionController` for expenses will pass `type => Expense` (or keep default if unchanged). The current `create()` method signature is changed to be driven by `$data['type']`.

`pay()` / `unpay()` / `archive()` are already type-agnostic. Only add a check for archived account on `pay()`:

```php
if ($transaction->account && $transaction->account->trashed()) {
    throw ValidationException::withMessages(['account_id' => 'A conta vinculada foi arquivada. Restaure a conta antes de confirmar o recebimento.']);
}
```

Add a new method `recalculateForAccountMove(Transaction $transaction, ?int $oldAccountId, int $newAccountId)` or similar, or extend existing `recalculateAfterUpdate` to handle both. The existing `recalculateAfterUpdate` already handles value/account changes for paid transactions.

#### `AccountService`

No change required. `recalculateBalance()` already sums paid income and subtracts paid expenses.

### 1.3 Controllers

#### `IncomeController`

Resource controller, workspace scoped, uses `TransactionPolicy`.

Routes:

```php
Route::resource('incomes', IncomeController::class);
Route::post('incomes/{transaction}/pay', [IncomeController::class, 'pay'])->name('incomes.pay');
Route::post('incomes/{transaction}/unpay', [IncomeController::class, 'unpay'])->name('incomes.unpay');
```

Methods:

- `index(Workspace $workspace, Request $request)` — list `type=Income` transactions (not soft-deleted), filters, pagination.
- `create(Workspace $workspace)` — return Inertia page with accounts (not archived), categories (`type IN (income,both)`), tags, empty form.
- `store(StoreIncomeRequest $request, Workspace $workspace)` — if `is_recurring` false → `TransactionService::create()` with type=Income; if true → `RecurrenceService::createWithFirstInstance()` when `start_date <= today`, else `RecurrenceService::create()`.
- `edit(Workspace $workspace, Transaction $transaction)` — return Inertia page with transaction, accounts, categories, tags, recurrence info (read-only), scope selector if `recurrence_id != null`.
- `update(UpdateIncomeRequest $request, Workspace $workspace, Transaction $transaction)` — if `recurrence_id` null or scope == 'single' → `TransactionService::update()`; if scope == 'future' → `RecurrenceService::updateThisAndFuture()`.
- `destroy(Workspace $workspace, Transaction $transaction)` — if `recurrence_id` null → `TransactionService::archive()`; if not null → prompt/scope handling. The destroy method receives `scope` query/body param. `scope=single` → `TransactionService::archive()`; `scope=future` → `RecurrenceService::deleteThisAndFuture()`.
- `pay(Request $request, Workspace $workspace, Transaction $transaction)` — `TransactionService::pay()` then redirect back.
- `unpay(Request $request, Workspace $workspace, Transaction $transaction)` — `TransactionService::unpay()` then redirect back.

#### `RecurrenceController`

Routes:

```php
Route::resource('recurrences', RecurrenceController::class)->only(['index', 'edit', 'update', 'destroy']);
Route::post('recurrences/{recurrence}/pause', [RecurrenceController::class, 'pause'])->name('recurrences.pause');
Route::post('recurrences/{recurrence}/restore', [RecurrenceController::class, 'restore'])->name('recurrences.restore');
Route::post('recurrences/{recurrence}/generate', [RecurrenceController::class, 'generateNow'])->name('recurrences.generate');
```

Methods:

- `index(Workspace $workspace, Request $request)` — list all recurrences (active, paused, exhausted); order by `next_date ASC NULLS LAST`; status badges distinguish them.
- `edit(Workspace $workspace, Recurrence $recurrence)` — return form with all fields editable except `start_date` is read-only if any transaction generated.
- `update(UpdateRecurrenceRequest $request, Workspace $workspace, Recurrence $recurrence)` — `RecurrenceService::updateRule()`; recompute next_date if frequency/frequency_day changed; exhaust if until_date < next_date.
- `pause(Workspace $workspace, Recurrence $recurrence)` — set `status = paused`; already-generated transactions remain.
- `restore(Workspace $workspace, Recurrence $recurrence)` — `RecurrenceService::restore()` (set `status = active` + recompute next_date); reject if linked account archived or until_date < today.
- `destroy(Workspace $workspace, Recurrence $recurrence)` — real soft-delete; used only for "Esta e parar futuras" flow or explicit delete action. Already-generated past transactions remain.
- `generateNow(Workspace $workspace, Recurrence $recurrence)` — `RecurrenceService::generateNextInstance()`; 403 for viewer; reject if `status != active`; message if next_date > today.

### 1.4 FormRequests

#### `StoreIncomeRequest`

Base transaction rules:

```php
'description' => ['required', 'string', 'max:255'],
'value' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
'date' => ['required', 'date'],
'account_id' => ['required', 'exists:accounts,uuid'],
'category_id' => ['required', 'exists:categories,uuid'],
'tags' => ['sometimes', 'array'],
'tags.*' => ['string', 'exists:tags,uuid'],
'is_recurring' => ['boolean'],
```

When `is_recurring` is true, add:

```php
'frequency' => ['required', new Enum(RecurrenceFrequency::class)],
'frequency_day' => ['required', 'integer'],
'until_date' => ['nullable', 'date', 'after_or_equal:date'],
```

`withValidator()`:

- Resolve account/category/tag UUIDs to models and verify workspace_id.
- For income: reject if `category->type === TransactionType::Expense` with message "Esta categoria não aceita receitas".
- Reject if account is trashed: "A conta selecionada foi arquivada".
- For recurring: validate `frequency_day` range (0–6 for Weekly, 1–31 for Monthly) with custom messages.
- Reject `credit_card_id` if present: "Uma receita não pode estar vinculada a um cartão de crédito".

#### `UpdateIncomeRequest`

Same as Store but fields `sometimes` required. Add `scope` field: `['sometimes', 'in:single,future']`. When `recurrence_id` present and `scope=future`, validate that frequency/start_date are not changed (they are not submitted from the form; if they are, reject with "Para alterar a frequência, edite a recorrência na página de gestão.").

#### `StoreRecurrenceRequest`

Not needed directly because recurring income is created via the unified income form. However, `RecurrenceController::update` needs `UpdateRecurrenceRequest`.

#### `UpdateRecurrenceRequest`

Rules:

```php
'description' => ['sometimes', 'string', 'max:255'],
'value' => ['sometimes', 'numeric', 'gt:0', 'max:999999999.99'],
'account_id' => ['sometimes', 'exists:accounts,uuid'],
'category_id' => ['sometimes', 'exists:categories,uuid'],
'frequency' => ['sometimes', new Enum(RecurrenceFrequency::class)],
'frequency_day' => ['sometimes', 'integer'],
'start_date' => ['sometimes', 'date'],
'until_date' => ['nullable', 'date', 'after_or_equal:start_date'],
'tags' => ['sometimes', 'array'],
'tags.*' => ['string', 'exists:tags,uuid'],
```

`withValidator()`:

- Workspace/category/account/tag validation.
- Category type guard.
- Account not archived.
- `frequency_day` range based on frequency.
- On `frequency`/`frequency_day` change: recompute next_date. If transactions exist and `start_date` changed, reject.
- If `until_date` set and < today on reactivate path, handled in controller.

### 1.5 Policies

#### `RecurrencePolicy`

Same pattern as `TransactionPolicy`:

- `viewAny(User $user, Workspace $workspace)` → workspace member
- `create` → not used (recurrences are created via income form)
- `update(User $user, Workspace $workspace, Recurrence $recurrence)` → admin/editor
- `delete(User $user, Workspace $workspace, Recurrence $recurrence)` → admin/editor (real soft-delete)
- `pause(User $user, Workspace $workspace, Recurrence $recurrence)` → admin/editor
- `restore(User $user, Workspace $workspace, Recurrence $recurrence)` → admin/editor
- `generateNow(User $user, Workspace $workspace, Recurrence $recurrence)` → admin/editor
- `view(User $user, Workspace $workspace, Recurrence $recurrence)` → workspace member

`TransactionPolicy` is reused for incomes. No new policy needed.

### 1.6 Job

#### `ProcessRecurrencesJob`

```php
class ProcessRecurrencesJob implements ShouldQueue
{
    use Queueable;

    public function handle(RecurrenceService $service): void
    {
        $recurrences = Recurrence::with('account')
            ->whereNull('deleted_at')
            ->where('status', RecurrenceStatus::Active)
            ->whereNotNull('next_date')
            ->whereDate('next_date', '<=', today())
            ->where(function ($q) {
                $q->whereNull('until_date')->orWhereColumn('next_date', '<=', 'until_date');
            })
            ->get();

        foreach ($recurrences as $recurrence) {
            try {
                $service->generateNextInstance($recurrence);
            } catch (Throwable $e) {
                Log::error("ProcessRecurrencesJob recurrence {$recurrence->uuid}: " . $e->getMessage());
            }
        }
    }
}
```

Schedule in `routes/console.php`:

```php
Schedule::job(new ProcessRecurrencesJob)->dailyAt('00:00');
```

### 1.7 API Resources

#### `RecurrenceResource`

```php
[
    'id' => $this->uuid,
    'description' => $this->description,
    'value' => (float) $this->value,
    'frequency' => $this->frequency,
    'frequency_day' => $this->frequency_day,
    'start_date' => $this->start_date->toDateString(),
    'until_date' => $this->until_date?->toDateString(),
    'next_date' => $this->next_date?->toDateString(),
    'status' => $this->status->value, // active|paused|exhausted
    'account' => new AccountResource($this->whenLoaded('account')),
    'category' => new CategoryResource($this->whenLoaded('category')),
    'tags' => TagResource::collection($this->whenLoaded('tags')),
    'created_at' => ...,
]
```

#### `TransactionResource`

Add `recurrence_id` (uuid) and `recurrence` (when loaded). Add `is_paid` boolean.

---

## 2. Frontend Architecture

### 2.1 Page Inventory

| Page | Route | Purpose |
|------|-------|---------|
| `Incomes/Index.tsx` | `incomes.index` | List incomes (avulsas + generated instances) with filters |
| `Incomes/Create.tsx` | `incomes.create` | Unified income form with recurrence toggle |
| `Incomes/Edit.tsx` | `incomes.edit` | Edit income; shows scope selector if recurrent |
| `Recurrences/Index.tsx` | `recurrences.index` | Manage recurrence rules |
| `Recurrences/Edit.tsx` | `recurrences.edit` | Edit recurrence rule (frequency, end date, etc.) |

### 2.2 Form State (Create)

```typescript
interface IncomeFormData {
  description: string;
  value: string;
  date: string; // today default
  account_id: string;
  category_id: string;
  tags: string[];
  is_recurring: boolean;
  frequency: 'weekly' | 'monthly';
  frequency_day: number;
  until_date: string;
}
```

`is_recurring` toggle reveals recurrence panel. When toggled OFF, recurrence fields are not sent or ignored. When toggled ON, `date` becomes the `start_date` label.

### 2.3 Recurrence Panel UI

- Frequency selector (Weekly / Monthly)
- `frequency_day` input:
  - Weekly: weekday selector (Dom–Sáb) returning 0–6.
  - Monthly: day-of-month input (1–31).
- `until_date` optional date input with checkbox "Definir data final".

### 2.4 Income List

- Columns: description, value (green `text-emerald-600`), date, account, category (color dot + name), tags, status badge (Confirmada / Prevista), actions (Confirmar/Desmarcar, Editar, Excluir).
- Recurring badge with link to `recurrences.show` (or `recurrences.index` filtered by id).
- Empty state CTA.
- Filters (P2): search, category, account, date range, status, origin (recurring/single), recurrence id param.

### 2.5 Scope Prompts

#### Edit scope

In `Incomes/Edit.tsx`, when `transaction.recurrence_id != null` show a radio group:

- "Apenas esta" (default)
- "Esta e futuras"

Recurrence panel is read-only (display only).

#### Delete scope

A confirmation modal triggered by delete button:

- "Apenas esta"
- "Esta e parar futuras"

If parent recurrence is already soft-deleted, only "Apenas esta" is available.

### 2.6 Recurrences Page

- List all recurrences (active, paused, exhausted) in one view; status badge indicates state.
- Status badges: Ativa (green), Esgotada (gray), Pausada (amber).
- Frequency label helper: "Toda Seg" / "Todo dia 5".
- Actions per row: Editar, Pausar/Reativar, Gerar agora, Ver instâncias.
- Empty state CTA to income create form.
- Soft-deleted recurrences (from "Esta e parar futuras") are visible only via the income list's historical "Recorrente" badge; the `/recurrences` index does not show soft-deleted rules.

### 2.7 Reused Components

- `AuthenticatedLayout`, `AppSidebar`, `AppHeader` — layout.
- shadcn/ui: `Card`, `Button`, `Input`, `Label`, `Select`, `Checkbox`, `Dialog`, `RadioGroup`, `Badge`.
- Existing `formatCurrency`.

---

## 3. Data Flows

### 3.1 Create Avulsa Income

```
Browser → POST /w/{workspace}/incomes
StoreIncomeRequest validates
IncomeController::store
  is_recurring = false
  TransactionService::create(workspace, data + type=Income, user)
  syncTags
  redirect to incomes.index
```

### 3.2 Create Recurring Income (start_date <= today)

```
Browser → POST /w/{workspace}/incomes
StoreIncomeRequest validates
IncomeController::store
  is_recurring = true
  RecurrenceService::createWithFirstInstance(workspace, data, user)
    DB::transaction
      create Recurrence
      create Transaction (date = start_date, recurrence_id set)
      sync tags to both
      advance next_date
      optimistic lock update
  redirect to incomes.index
```

### 3.3 Create Recurring Income (start_date > today)

```
Browser → POST /w/{workspace}/incomes
IncomeController::store
  RecurrenceService::create(workspace, data, user)
    create Recurrence with next_date = start_date
  redirect to incomes.index
```

### 3.4 Daily Job Generation

```
Schedule::job(ProcessRecurrencesJob) @ 00:00
  query recurrences with next_date <= today
  for each recurrence
    RecurrenceService::generateNextInstance
      skip if account archived
      compute generationDate
      DB::transaction
        create Transaction
        sync tags
        compute nextNextDate
        optimistic lock update
      log warning if account archived
      log error on exception (continue)
```

### 3.5 Confirm Receipt

```
Browser → POST /w/{workspace}/incomes/{transaction}/pay
IncomeController::pay
  TransactionService::pay(transaction)
    if account archived → reject
    DB::transaction
      paid_at = now
      recalculateBalance(account)
  redirect back
```

### 3.6 Edit Instance Scope "Esta e futuras"

```
Browser → PUT /w/{workspace}/incomes/{transaction}
UpdateIncomeRequest validates scope=future
IncomeController::update
  RecurrenceService::updateThisAndFuture(transaction, data, user)
    dispatches ApplyRecurrenceScopeChangeJob(operation='update', transactionUuid, payload, userId)
  redirect to incomes.index with flash "Alteração em processamento."

ApplyRecurrenceScopeChangeJob (async)
  RecurrenceService::applyUpdateThisAndFuture(transaction, data, user)
    DB::transaction
      update current Transaction
      update parent Recurrence
      update future Transactions (date >= current.date) with new description/value/account/category
      sync tags on all touched
      recalculateBalance on affected accounts for confirmed transactions
```

### 3.7 Delete Instance Scope "Esta e parar futuras"

```
Browser → DELETE /w/{workspace}/incomes/{transaction}?scope=future
IncomeController::destroy
  RecurrenceService::deleteThisAndFuture(transaction)
    dispatches ApplyRecurrenceScopeChangeJob(operation='delete', transactionUuid)
  redirect to incomes.index with flash "Alteração em processamento."

ApplyRecurrenceScopeChangeJob (async)
  RecurrenceService::applyDeleteThisAndFuture(transaction)
    DB::transaction
      soft-delete current Transaction (recalculate balance first if paid)
      soft-delete future Transactions (date > current.date) with recalculate balance if paid
      soft-delete parent Recurrence (status is irrelevant after soft-delete)
```

### 3.8 Pause / Restore Recurrence

```
Browser → POST /w/{workspace}/recurrences/{recurrence}/pause
RecurrenceController::pause
  RecurrenceService::pause(recurrence)
    status = paused
  redirect to recurrences.index

Browser → POST /w/{workspace}/recurrences/{recurrence}/restore
RecurrenceController::restore
  RecurrenceService::restore(recurrence)
    validate account not archived
    validate until_date >= today
    status = active
    recompute next_date from today
  redirect to recurrences.index
```

---

## 4. Test Strategy

TDD-first. Tests BEFORE implementation.

### 4.1 PHPUnit Feature Tests

Mirror existing `tests/Feature/Transactions/` structure:

```
tests/Feature/Incomes/
  IncomeCreationTest.php
  IncomeValidationTest.php
  IncomeConfirmationTest.php
  IncomeUpdateTest.php
  IncomeDeletionTest.php
  IncomeAuthorizationTest.php
  IncomeFilteringTest.php
tests/Feature/Recurrences/
  RecurrenceCreationTest.php
  RecurrenceGenerationTest.php
  RecurrenceJobTest.php
  RecurrenceManagementTest.php
  RecurrenceAuthorizationTest.php
```

Key test scenarios:

- Create avulsa income → single transaction, balance unchanged until confirmed.
- Create recurring income with start_date <= today → recurrence + transaction created, next_date advanced.
- Create recurring income with start_date > today → only recurrence, no transaction.
- Category type guard rejects Expense categories.
- Account required for income.
- Confirm receipt increases balance; unconfirm restores.
- Edit value/account on confirmed income recalculates balance.
- Scope "Apenas esta" only touches current transaction.
- Scope "Esta e futuras" updates current + future + parent; past unchanged.
- Scope "Esta e parar futuras" soft-deletes current + future + parent; past remains.
- Job generates monthly on due date; no duplicate with optimistic lock.
- Monthly day 31 → Feb 28/29 fallback.
- Job with next_date in past generates ONE transaction for today and advances.
- Recurrence management: pause (status=paused), restore (status=active), edit frequency, exhausted state, manual generate now.
- Viewer denied all mutating routes.
- Cross-workspace access returns 404.

### 4.2 Cypress E2E

**E2E tests are a hard gate for this feature.** They must pass before the feature is considered complete.

Required E2E journeys:

1. **CRUD unificado de receitas**
   - Register workspace → create account → navigate to `/incomes/create` → create avulsa income → see it in `/incomes`.
   - Toggle "É recorrente?" → fill weekly recurrence → submit → see recurrence in `/recurrences` and first instance in `/incomes`.

2. **Confirmação de recebimento**
   - Create income → account balance unchanged → click "Confirmar recebimento" → balance increases → click "Desmarcar" → balance restores.

3. **Geração de instâncias recorrentes**
   - Create recurring income with future start_date → only recurrence visible → trigger job or wait/generate → instance appears in income list.

4. **Edição/exclusão com escopo**
   - Generate multiple instances → edit with "Esta e futuras" → verify future instances update.
   - Delete with "Esta e parar futuras" → verify past remains, future stops.

5. **Gestão de recorrências**
   - Navigate `/recurrences` → pause recurrence → verify status "Pausada" → reactivate → verify status "Ativa" and next_date recomputed.

All E2E tests use `data-testid` attributes for selectors and run against the Docker app service.

---

## 5. Files to Create / Modify

### 5.1 Create

| File | Purpose |
|------|---------|
| `app/Models/Recurrence.php` | Recurrence model |
| `app/Enums/RecurrenceFrequency.php` | Frequency enum |
| `app/Enums/RecurrenceStatus.php` | Status enum (active/paused) |
| `app/Services/RecurrenceService.php` | Recurrence business logic |
| `app/Jobs/ProcessRecurrencesJob.php` | Scheduled generation job |
| `app/Policies/RecurrencePolicy.php` | Recurrence authorization |
| `app/Http/Controllers/IncomeController.php` | Income CRUD + pay/unpay |
| `app/Http/Controllers/RecurrenceController.php` | Recurrence management |
| `app/Http/Requests/StoreIncomeRequest.php` | Unified income create validation |
| `app/Http/Requests/UpdateIncomeRequest.php` | Income update + scope validation |
| `app/Http/Requests/UpdateRecurrenceRequest.php` | Recurrence rule update validation |
| `app/Http/Resources/RecurrenceResource.php` | Recurrence API resource |
| `database/migrations/2026_07_20_000001_create_recurrences_table.php` | Recurrences table |
| `database/migrations/2026_07_20_000002_add_recurrence_id_to_transactions.php` | FK link |
| `resources/js/Pages/Incomes/Index.tsx` | Income list |
| `resources/js/Pages/Incomes/Create.tsx` | Unified income form |
| `resources/js/Pages/Incomes/Edit.tsx` | Income edit with scope |
| `resources/js/Pages/Recurrences/Index.tsx` | Recurrence list |
| `resources/js/Pages/Recurrences/Edit.tsx` | Recurrence edit form |
| `tests/Feature/Incomes/*` | PHPUnit feature tests |
| `tests/Feature/Recurrences/*` | PHPUnit feature tests |

### 5.2 Modify

| File | Purpose |
|------|---------|
| `app/Models/Transaction.php` | Add `recurrence_id` fillable + relation |
| `app/Services/TransactionService.php` | Make `create()` type-aware, guard archived account on pay |
| `app/Http/Resources/TransactionResource.php` | Add recurrence info + is_paid |
| `app/Providers/AuthServiceProvider.php` | Register `RecurrencePolicy` |
| `routes/web.php` | Add incomes + recurrences routes |
| `routes/console.php` | Schedule `ProcessRecurrencesJob` |
| `resources/js/Components/AppSidebar.tsx` | Fix "Receitas" link to `route('incomes.index', ...)` |
| `resources/js/Components/AppHeader.tsx` | Update breadcrumb if needed |
| `database/factories/RecurrenceFactory.php` | Factory for tests |

### 5.3 Optional / P2

- `resources/js/Pages/Incomes/Index.tsx` filters: fully implement in P1 or stub and complete in P2.

---

## 6. Risks & Decisions

| Risk | Mitigation |
|------|------------|
| `TransactionService::create()` currently hardcoded for expense | Refactor to accept type; keep existing expense tests green |
| Scope "Esta e futuras" can recalculate many accounts on bulk update | Moved to `ApplyRecurrenceScopeChangeJob` running asynchronously; underlying `apply*` methods use `DB::transaction` + collect unique affected accounts |
| Optimistic lock with `next_date` may race with manual "Gerar agora" | Both use same `generateNextInstance` method; lock guarantees idempotency |
| `start_date` read-only when transactions exist | Enforce in `UpdateRecurrenceRequest` and UI |
| Monthly day 31 fallback needs Carbon `addMonthNoOverflow` | Encapsulate in `RecurrenceService::nextOccurrenceFrom()` helper |
| Paused recurrence is now a status, not soft-delete | Update job query, index query, and routes to use `status` |
| Historical "Recorrente" badge linking to soft-deleted recurrence | Use `withTrashed()` only for rules deleted via "Esta e parar futuras"; paused rules remain accessible normally |
| UI toggle state is lost if user toggles OFF then ON | Accept that recurrence fields are discarded on submit when OFF (per spec) |

---

## 7. Verification Gates

- `php artisan test --filter=Income` passes
- `php artisan test --filter=Recurrence` passes
- `php artisan migrate:fresh --seed` succeeds
- `npm run build` (or `npm run dev` compile) succeeds
- `php artisan route:list` shows new routes
- Manual smoke: create avulsa, create recurring, confirm, edit scope, delete scope, pause/restore, job generation

---

End of design.
