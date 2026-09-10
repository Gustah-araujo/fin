# Recurrence Buffer — Design & Task Plan

## Overview

Transform the recurrence system from "rule + in-memory projection" to "generator + buffer of real transactions". This eliminates duplicate values in planning and simplifies the architecture.

## Current State Analysis

| Component | Current Behavior | Target Behavior |
|-----------|-----------------|-----------------|
| `RecurrenceService::create()` | Creates rule only, no transactions | Creates rule + generates buffer of N future transactions |
| `RecurrenceService::createWithFirstInstance()` | Creates rule + 1 transaction, advances next_date | Creates rule + retroactive instances + buffer |
| `ProcessRecurrencesJob` | Finds due recurrences via next_date, generates 1 instance | Counts future instances per recurrence, generates to fill buffer |
| `PlanningService` | Reads transactions + projects recurrences in-memory | Reads ONLY transactions table |
| `RecurrenceService::updateRule()` | Updates rule fields | Updates rule + optionally propagates to future instances |
| Edit form | No buffer field, no propagation | Has buffer field + propagate checkbox |

## Task Breakdown

---

### T1: Migration — Add buffer_ahead to recurrences

**File:** `database/migrations/2026_09_10_000000_add_buffer_ahead_to_recurrences_table.php`

```php
Schema::table('recurrences', function (Blueprint $table) {
    $table->unsignedTinyInteger('buffer_ahead')->default(12)->after('status');
});
```

**Test:** Assert column exists via `Schema::hasColumn`.

---

### T2: Update Recurrence Model

**File:** `app/Models/Recurrence.php`

- Add `'buffer_ahead'` to `$fillable`
- Add `'buffer_ahead' => 'integer'` to `$casts`

---

### T3: Update RecurrenceResource

**File:** `app/Http/Resources/RecurrenceResource.php`

- Add `'buffer_ahead' => (int) $this->buffer_ahead,`

---

### T4: Refactor RecurrenceService — Buffer Generation Core

**File:** `app/Services/RecurrenceService.php`

#### New method: `createWithBuffer()`

Replaces both `create()` and `createWithFirstInstance()`:

```
1. Resolve FKs (account, category)
2. Parse start_date, frequency, frequency_day
3. today = Carbon::today()
4. instances = []
5. 
6. IF start_date <= today:
   a. Generate retroactive instances from start_date up to (but not including) the first future occurrence
   b. First future occurrence = nextOccurrenceOnOrAfter(today)
   c. Generate buffer_ahead instances starting from first future occurrence
7. ELSE (start_date > today):
   a. Generate buffer_ahead instances starting from start_date
8.
9. Wrap everything in DB::transaction
10. Create recurrence with buffer_ahead value
11. Bulk insert all transactions with recurrence_id set
12. Sync tags to all instances
13. Return recurrence
```

#### New method: `countFutureInstances(Recurrence $recurrence): int`

```php
return Transaction::where('recurrence_id', $recurrence->id)
    ->whereNull('deleted_at')
    ->whereDate('date', '>=', today())
    ->count();
```

#### New method: `generateBufferInstances(Recurrence $recurrence): void`

```php
$currentCount = $this->countFutureInstances($recurrence);
$needed = $recurrence->buffer_ahead - $currentCount;

if ($needed <= 0) return;

// Find the latest future transaction date, or use today
$latestDate = Transaction::where('recurrence_id', $recurrence->id)
    ->whereNull('deleted_at')
    ->max('date');

$startDate = $latestDate 
    ? Carbon::parse($latestDate)->addDay() 
    : Carbon::today();

// Generate $needed instances starting from $startDate
for ($i = 0; $i < $needed; $i++) {
    $date = $this->nextOccurrenceOnOrAfter($startDate, ...);
    // create transaction
    $startDate = $date->copy()->addDay();
}
```

#### New method: `propagateToFuture(Recurrence $recurrence, array $data): void`

```php
DB::transaction(function () use ($recurrence, $data) {
    $futureTransactions = Transaction::where('recurrence_id', $recurrence->id)
        ->whereNull('deleted_at')
        ->whereDate('date', '>=', today())
        ->get();

    foreach ($futureTransactions as $transaction) {
        // Apply shared fields (description, value, account_id, category_id)
        // Sync tags if present
    }
});
```

---

### T5: Refactor ProcessRecurrencesJob

**File:** `app/Jobs/ProcessRecurrencesJob.php`

```php
public function handle(RecurrenceService $service): void
{
    $recurrences = Recurrence::whereNull('deleted_at')
        ->where('status', RecurrenceStatus::Active)
        ->get();

    foreach ($recurrences as $recurrence) {
        try {
            $service->generateBufferInstances($recurrence);
        } catch (Throwable $e) {
            Log::error("Buffer maintenance failed for {$recurrence->uuid}: " . $e->getMessage());
        }
    }
}
```

**Key change:** No more `next_date` filtering. Just iterate all active recurrences and fill their buffers.

---

### T6: Update StoreIncomeRequest

**File:** `app/Http/Requests/StoreIncomeRequest.php`

Add to rules:
```php
'buffer_ahead' => ['sometimes', 'integer', 'min:1', 'max:50'],
```

---

### T7: Update StoreTransactionRequest

**File:** `app/Http/Requests/StoreTransactionRequest.php`

Same as T6.

---

### T8: Update UpdateRecurrenceRequest

**File:** `app/Http/Requests/UpdateRecurrenceRequest.php`

Add to rules:
```php
'buffer_ahead' => ['sometimes', 'integer', 'min:1', 'max:50'],
'propagate_to_future' => ['sometimes', 'boolean'],
```

---

### T9: Update RecurrenceController::update()

**File:** `app/Http/Controllers/RecurrenceController.php`

```php
public function update(
    UpdateRecurrenceRequest $request, 
    Workspace $workspace, 
    Recurrence $recurrence, 
    RecurrenceService $recurrenceService
): RedirectResponse {
    abort_if($recurrence->workspace_id !== $workspace->id, 404);
    $this->authorize('update', [$recurrence, $workspace]);

    $data = $request->validated();
    $propagate = $data['propagate_to_future'] ?? false;
    unset($data['propagate_to_future']);

    // Update rule fields
    $recurrenceService->updateRule($recurrence, $data);

    // Propagate to future instances if requested
    if ($propagate) {
        $recurrenceService->propagateToFuture($recurrence, $data);
        Toast::success('Recorrência e instâncias futuras atualizadas.');
    } else {
        Toast::success('Recorrência atualizada com sucesso.');
    }

    return redirect()->route('recurrences.index', $workspace);
}
```

---

### T10: Refactor PlanningService

**File:** `app/Services/PlanningService.php`

Remove `projectActiveRecurrences()` method entirely.

`getProjection()` becomes:
```php
public function getProjection(Workspace $workspace, Carbon $dateStart, Carbon $dateEnd): array
{
    $transactions = Transaction::where('workspace_id', $workspace->id)
        ->whereIn('type', [TransactionType::Expense, TransactionType::Income])
        ->whereNull('deleted_at')
        ->whereBetween('date', [$dateStart->toDateString(), $dateEnd->toDateString()])
        ->get();

    return $this->summarizeByMonth($transactions, $dateStart, $dateEnd);
}
```

`getMonthDetail()` becomes:
```php
public function getMonthDetail(Workspace $workspace, Carbon $month): array
{
    $monthStart = $month->copy()->startOfMonth();
    $monthEnd = $month->copy()->endOfMonth();

    $transactions = Transaction::where('workspace_id', $workspace->id)
        ->whereIn('type', [TransactionType::Expense, TransactionType::Income])
        ->whereNull('deleted_at')
        ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
        ->get();

    // ... format as before
}
```

---

### T11: Update IncomeController::store()

**File:** `app/Http/Controllers/IncomeController.php`

Replace the `is_recurring` branch:
```php
if ($request->boolean('is_recurring')) {
    $data['start_date'] = $data['date'];
    $data['type'] = TransactionType::Income->value;
    $recurrenceService->createWithBuffer($workspace, $data, $request->user());
    Toast::success('Recorrência criada com sucesso.');
    return redirect()->route('incomes.index', $workspace);
}
```

---

### T12: Update TransactionController::store()

**File:** `app/Http/Controllers/TransactionController.php`

Same pattern as T11 but with `TransactionType::Expense`.

---

### T13: Frontend — Incomes/Create.tsx

**File:** `resources/js/Pages/Incomes/Create.tsx`

Add to form state:
```typescript
buffer_ahead: 12,
```

Add UI after frequency_day selection:
```tsx
<div className="space-y-2">
    <Label htmlFor="buffer_ahead">Quantidade de Buffer</Label>
    <Input
        id="buffer_ahead"
        type="number"
        min="1"
        max="50"
        value={data.buffer_ahead}
        onChange={(e) => setData('buffer_ahead', Number(e.target.value))}
    />
    <p className="text-xs text-muted-foreground">
        O sistema cria essas receitas/despesas automaticamente para você 
        poder usar em planejamentos e relatórios futuros.
    </p>
</div>
```

Add `buffer_ahead` to payload when `is_recurring`.

---

### T14: Frontend — Transactions/Create.tsx

**File:** `resources/js/Pages/Transactions/Create.tsx`

Same as T13.

---

### T15: Frontend — Recurrences/Edit.tsx

**File:** `resources/js/Pages/Recurrences/Edit.tsx`

Add:
1. `buffer_ahead` field (number input, min 1, max 50)
2. `propagate_to_future` checkbox with confirmation text

```tsx
<div className="space-y-2">
    <Label htmlFor="buffer_ahead">Quantidade de Buffer</Label>
    <Input
        id="buffer_ahead"
        type="number"
        min="1"
        max="50"
        value={data.buffer_ahead}
        onChange={(e) => setData('buffer_ahead', Number(e.target.value))}
    />
    <p className="text-xs text-muted-foreground">
        O sistema cria essas receitas/despesas automaticamente para você 
        poder usar em planejamentos e relatórios futuros.
    </p>
</div>

<div className="flex items-center gap-2 rounded-lg border p-3">
    <Checkbox
        id="propagate_to_future"
        checked={data.propagate_to_future}
        onCheckedChange={(checked) => setData('propagate_to_future', checked === true)}
    />
    <Label htmlFor="propagate_to_future" className="text-sm">
        Aplicar estas alterações para todas as instâncias futuras já cadastradas
    </Label>
</div>
```

---

### T16: Tests — Buffer Generation

**File:** `tests/Feature/Recurrences/RecurrenceBufferTest.php`

```php
test('creating recurrence with past start_date generates retroactive + buffer instances')
test('creating recurrence with future start_date generates only buffer instances')
test('buffer count excludes past instances')
test('buffer max is 50')
test('buffer min is 1')
```

---

### T17: Tests — Buffer Maintenance Job

**File:** `tests/Feature/Recurrences/RecurrenceBufferJobTest.php`

```php
test('daily job fills buffer to configured count')
test('job does not over-generate when buffer is full')
test('job handles multiple recurrences independently')
```

---

### T18: Tests — Propagate to Future

**File:** `tests/Feature/Recurrences/RecurrencePropagateTest.php`

```php
test('updating recurrence with propagate updates future instances')
test('updating recurrence without propagate leaves future instances unchanged')
test('propagated fields include description value account category tags')
```

---

### T19: Tests — Planning Refactor

**File:** Update `tests/Feature/Planning/PlanningServiceTest.php`

Remove tests for `projectActiveRecurrences`. Add:
```php
test('planning reads only from transactions table')
test('planning does not duplicate recurrence values')
```

---

### T20: Quality Gates

```bash
composer quality
npm run quality
php artisan test
npm run build
```

---

## Dependency Graph

```
T1 (migration) → T2 (model) → T3 (resource)
T2 + T3 → T4 (service refactor)
T4 → T5 (job refactor)
T4 → T8 (propagate)
T6 + T7 (requests) → T9 (controller update)
T10 (planning refactor)
T11 + T12 (controller store updates)
T13 + T14 + T15 (frontend)
T16 + T17 + T18 + T19 (tests)
T20 (quality gates)
```

## Files Modified Summary

| File | Change |
|------|--------|
| `database/migrations/2026_09_10_000000_add_buffer_ahead_to_recurrences_table.php` | NEW |
| `app/Models/Recurrence.php` | Add buffer_ahead to fillable/casts |
| `app/Http/Resources/RecurrenceResource.php` | Add buffer_ahead field |
| `app/Services/RecurrenceService.php` | Major refactor: createWithBuffer, countFutureInstances, generateBufferInstances, propagateToFuture |
| `app/Jobs/ProcessRecurrencesJob.php` | Simplify to buffer maintenance |
| `app/Http/Requests/StoreIncomeRequest.php` | Add buffer_ahead validation |
| `app/Http/Requests/StoreTransactionRequest.php` | Add buffer_ahead validation |
| `app/Http/Requests/UpdateRecurrenceRequest.php` | Add buffer_ahead + propagate_to_future |
| `app/Http/Controllers/RecurrenceController.php` | Handle propagation |
| `app/Http/Controllers/IncomeController.php` | Use createWithBuffer |
| `app/Http/Controllers/TransactionController.php` | Use createWithBuffer |
| `app/Services/PlanningService.php` | Remove projection, read only transactions |
| `resources/js/Pages/Incomes/Create.tsx` | Add buffer_ahead input |
| `resources/js/Pages/Transactions/Create.tsx` | Add buffer_ahead input |
| `resources/js/Pages/Recurrences/Edit.tsx` | Add buffer_ahead + propagate checkbox |
| `tests/Feature/Recurrences/RecurrenceBufferTest.php` | NEW |
| `tests/Feature/Recurrences/RecurrenceBufferJobTest.php` | NEW |
| `tests/Feature/Recurrences/RecurrencePropagateTest.php` | NEW |
| `tests/Feature/Planning/PlanningServiceTest.php` | Update |
