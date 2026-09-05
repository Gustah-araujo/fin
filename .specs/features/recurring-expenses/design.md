# Recurring Expenses — Design

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                        Frontend (React)                         │
├─────────────────────────────────────────────────────────────────┤
│  Transactions/Create.tsx  ──→  + recurrence toggle panel       │
│  Recurrences/Index.tsx    ──→  + type column, type filter       │
│                                     value color by type         │
│                                     "Ver instâncias" routing    │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                      Backend (Laravel)                          │
├─────────────────────────────────────────────────────────────────┤
│  TransactionController::store  ──→  + recurrence branch        │
│  StoreTransactionRequest       ──→  + recurrence rules          │
│  RecurrenceController::edit    ──→  category filter by type     │
│  UpdateRecurrenceRequest       ──→  category guard by type      │
│  RecurrenceResource            ──→  + type field                │
│  RecurrenceService             ──→  type from data (no hardcode)│
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Unchanged (already generic)                  │
├─────────────────────────────────────────────────────────────────┤
│  PlanningService        uses $recurrence->type                  │
│  ProcessRecurrencesJob  type-agnostic                           │
│  AccountService         handles both types                      │
│  Migration schema       type column exists                      │
└─────────────────────────────────────────────────────────────────┘
```

## Backend Changes

### 1. RecurrenceService (3 hardcode removals)

**File:** `app/Services/RecurrenceService.php`

| Method | Line | Current | Change |
|--------|------|---------|--------|
| `create()` | 57 | `'type' => TransactionType::Income` | `'type' => $data['type'] ?? TransactionType::Income` |
| `createWithFirstInstance()` | 99 | `'type' => TransactionType::Income` (recurrence) | `'type' => $data['type'] ?? TransactionType::Income` |
| `createWithFirstInstance()` | 117 | `'type' => TransactionType::Income` (transaction) | `'type' => $data['type'] ?? TransactionType::Income` |
| `buildGeneratedTransaction()` | 486 | `'type' => TransactionType::Income` | `'type' => $recurrence->type->value` |

**Rationale:** Type is set at creation time (from controller passing `$data['type']`). For generated instances, read from parent `$recurrence->type` (already cast to enum).

### 2. StoreTransactionRequest (add recurrence rules)

**File:** `app/Http/Requests/StoreTransactionRequest.php`

Add to `rules()`:
```php
'is_recurring' => ['sometimes', 'boolean'],
'frequency' => ['required_if:is_recurring,true', new Enum(RecurrenceFrequency::class)],
'frequency_day' => ['required_if:is_recurring,true', 'integer'],
'until_date' => ['nullable', 'date', 'after_or_equal:date'],
```

Add to `withValidator()`:
```php
$this->validateFrequencyDay($validator);
```

Add private method (mirror `StoreIncomeRequest::validateFrequencyDay`):
```php
private function validateFrequencyDay($validator): void
{
    if (! $this->boolean('is_recurring')) return;
    if (! $this->filled('frequency') || ! $this->filled('frequency_day')) return;

    $frequency = RecurrenceFrequency::tryFrom((string) $this->input('frequency'));
    $day = (int) $this->input('frequency_day');

    if ($frequency === RecurrenceFrequency::Weekly && ($day < 0 || $day > 6)) {
        $validator->errors()->add('frequency_day', 'Dia da semana inválido.');
    }
    if ($frequency === RecurrenceFrequency::Monthly && ($day < 1 || $day > 31)) {
        $validator->errors()->add('frequency_day', 'Dia do mês inválido.');
    }
}
```

Add messages:
```php
'frequency.required_if' => 'A frequência é obrigatória para despesas recorrentes.',
'frequency_day.required_if' => 'O dia da recorrência é obrigatório.',
'until_date.after_or_equal' => 'A data final deve ser maior ou igual à data inicial.',
```

### 3. TransactionController::store (add recurrence branch)

**File:** `app/Http/Controllers/TransactionController.php`

```php
public function store(
    StoreTransactionRequest $request,
    Workspace $workspace,
    TransactionService $transactionService,
    RecurrenceService $recurrenceService,
): RedirectResponse {
    $this->authorize('create', [Transaction::class, $workspace]);

    $data = $request->validated();

    if ($request->boolean('is_recurring')) {
        $data['type'] = TransactionType::Expense->value;
        $data['start_date'] = $data['date'];

        if (Carbon::parse($data['date'])->lte(Carbon::today())) {
            $recurrenceService->createWithFirstInstance($workspace, $data, $request->user());
        } else {
            $recurrenceService->create($workspace, $data, $request->user());
        }
    } else {
        $data['type'] = TransactionType::Expense->value;
        $transactionService->create($workspace, $request->user(), $data);
    }

    return redirect()->route('transactions.index', $workspace);
}
```

### 4. RecurrenceController::edit (category filter by type)

**File:** `app/Http/Controllers/RecurrenceController.php`

Change line 79-83 from:
```php
'categories' => CategoryResource::collection(
    $workspace->categories()
        ->whereIn('type', [TransactionType::Income->value, TransactionType::Both->value])
        ->orderBy('name')
        ->get()
),
```

To:
```php
'categories' => CategoryResource::collection(
    $workspace->categories()
        ->whereIn('type', $recurrence->type === TransactionType::Expense
            ? [TransactionType::Expense->value, TransactionType::Both->value]
            : [TransactionType::Income->value, TransactionType::Both->value])
        ->orderBy('name')
        ->get()
),
```

### 5. UpdateRecurrenceRequest (category guard by type)

**File:** `app/Http/Requests/UpdateRecurrenceRequest.php`

Change `validateCategory()` line 93-95 from:
```php
if ($category->type === TransactionType::Expense) {
    $validator->errors()->add('category_id', 'Esta categoria não aceita receitas.');
}
```

To:
```php
$recurrence = $this->route('recurrence');

if ($recurrence->type === TransactionType::Expense && $category->type === TransactionType::Income) {
    $validator->errors()->add('category_id', 'Esta categoria não aceita despesas.');
}

if ($recurrence->type === TransactionType::Income && $category->type === TransactionType::Expense) {
    $validator->errors()->add('category_id', 'Esta categoria não aceita receitas.');
}
```

### 6. RecurrenceResource (add type field)

**File:** `app/Http/Resources/RecurrenceResource.php`

Add to `toArray()`:
```php
'type' => $this->type->value,
```

### 7. RecurrenceFactory (add expense state)

**File:** `database/factories/RecurrenceFactory.php`

Add:
```php
public function expense(): static
{
    return $this->state(fn (array $attributes) => [
        'type' => TransactionType::Expense->value,
    ]);
}
```

## Frontend Changes

### 8. Transactions/Create.tsx (add recurrence panel)

**File:** `resources/js/Pages/Transactions/Create.tsx`

- Import `Switch`, `Checkbox` from shadcn
- Add to `useForm`: `is_recurring`, `frequency`, `frequency_day`, `until_date`, `has_until_date`
- Add `handleSubmit` payload logic (mirror Incomes/Create.tsx)
- Add recurrence toggle UI (Switch + conditional panel with frequency/day/until_date)
- Add `WEEKDAYS` constant

### 9. Recurrences/Index.tsx (type column + filter + routing)

**File:** `resources/js/Pages/Recurrences/Index.tsx`

Changes:
- Add `type` to `RecurrenceItem` interface
- Add `TYPE_OPTIONS` constant: `[{label: 'Receita', value: 'income'}, {label: 'Despesa', value: 'expense'}]`
- Add type column (badge: "Receita" emerald / "Despesa" red)
- Add type filter (select)
- Value column: color by type (`text-emerald-600` for income, `text-red-600` for expense)
- "Ver instâncias" link: route to `transactions.index` for expense, `incomes.index` for income
- Page copy: "Regras de receitas recorrentes" → "Regras de recorrências"
- CTA "Nova recorrência" → split or keep generic (links to incomes.create by default, or add dropdown)
- Empty state copy: "Nenhuma recorrência cadastrada" / "Criar recorrência"

## Test Strategy

### PHPUnit Feature Tests

| Test File | Tests | Coverage |
|-----------|-------|----------|
| `RecurrenceServiceTest` | +4 tests | create expense recurrence, createWithFirstInstance expense, generate expense instance, buildGeneratedTransaction reads type |
| `StoreTransactionRequestTest` | +3 tests | rejects income-only category for expense recurrence, validates frequency_day, accepts valid recurrence payload |
| `TransactionControllerTest` | +3 tests | store recurring expense creates recurrence + transaction, store future-dated creates recurrence only, store avulsed unchanged |
| `RecurrenceControllerTest` | +2 tests | edit filters categories by expense type, edit filters categories by income type |
| `UpdateRecurrenceRequestTest` | +2 tests | rejects income category on expense recurrence, accepts expense category on expense recurrence |
| `PlanningServiceTest` | +2 test | expense recurrence projects as expense, mixed income+expense recurrences balance correctly |

**Total new tests:** ~16 PHPUnit tests

### Cypress E2E

| Test | Flow |
|------|------|
| Create recurring expense | Toggle ON → fill frequency → submit → see in recurrence list |
| Recurrence list type filter | Filter by "Despesa" → see only expense recurrences |
| Planning shows expense projection | Create expense recurrence → open planning → see in expenses |

**Total new E2E:** ~3 Cypress tests

## TDD-First Task Order

```
Phase 1: Service layer (type from data)
  T1: RecurrenceService tests (type from $data / $recurrence->type)
  T2: RecurrenceService implementation (remove hardcodes)

Phase 2: Expense creation flow
  T3: StoreTransactionRequest tests (recurrence rules)
  T4: StoreTransactionRequest implementation
  T5: TransactionController tests (recurrence branch)
  T6: TransactionController implementation

Phase 3: Recurrence management
  T7: UpdateRecurrenceRequest tests (category guard by type)
  T8: UpdateRecurrenceRequest implementation
  T9: RecurrenceController tests (category filter by type)
  T10: RecurrenceController implementation
  T11: RecurrenceResource + RecurrenceFactory

Phase 4: Frontend
  T12: Transactions/Create.tsx recurrence panel
  T13: Recurrences/Index.tsx type column + filter + routing

Phase 5: Integration
  T14: PlanningService tests (expense recurrences)
  T15: Cypress E2E (create, filter, planning)
  T16: Quality gate (composer quality + npm run quality + full test suite)
```

## Files Changed Summary

| File | Type | Change |
|------|------|--------|
| `app/Services/RecurrenceService.php` | Modify | 3 hardcode removals |
| `app/Http/Requests/StoreTransactionRequest.php` | Modify | + recurrence rules |
| `app/Http/Controllers/TransactionController.php` | Modify | + recurrence branch |
| `app/Http/Controllers/RecurrenceController.php` | Modify | category filter by type |
| `app/Http/Requests/UpdateRecurrenceRequest.php` | Modify | category guard by type |
| `app/Http/Resources/RecurrenceResource.php` | Modify | + type field |
| `database/factories/RecurrenceFactory.php` | Modify | + expense() state |
| `resources/js/Pages/Transactions/Create.tsx` | Modify | + recurrence panel |
| `resources/js/Pages/Recurrences/Index.tsx` | Modify | + type column/filter |
| `tests/Feature/RecurrenceServiceTest.php` | Modify | +4 tests |
| `tests/Feature/StoreTransactionRequestTest.php` | Modify | +3 tests |
| `tests/Feature/TransactionControllerTest.php` | Modify | +3 tests |
| `tests/Feature/RecurrenceControllerTest.php` | Modify | +2 tests |
| `tests/Feature/UpdateRecurrenceRequestTest.php` | Modify | +2 tests |
| `tests/Feature/PlanningServiceTest.php` | Modify | +1 test |
| `tests/Feature/RecurrenceFactoryTest.php` | Modify | +1 test (if exists) |
| `cypress/e2e/recurring-expenses.cy.js` | Create | 3 E2E tests |

**Total:** 9 backend + 2 frontend modified, 1 new test file, ~16 PHPUnit + 3 Cypress tests
