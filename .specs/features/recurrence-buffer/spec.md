# Recurrence Buffer — Spec

## 📝 Description

Refactor the recurrence architecture to use a **buffer of pre-generated future instances** instead of in-memory projection. Recurrences act strictly as generators — all reports, dashboards, and planning read exclusively from the `transactions` table.

## 🎯 Requirements

### R1: Buffer Field & Migration
- Add `buffer_ahead` (unsignedTinyInteger, default 12) to `recurrences` table
- Validation: min 1, max 50

### R2: Instance Generation on Creation
- When creating a recurrence with `start_date` in the past:
  - Generate all retroactive instances from `start_date` to today
  - Generate `buffer_ahead` future instances starting from the next occurrence after today
- When `start_date` is today or future:
  - Generate `buffer_ahead` future instances starting from `start_date`
- Past instances do NOT consume buffer quota

### R3: Buffer Maintenance Job (Daily)
- Existing `ProcessRecurrencesJob` is repurposed to maintain buffer
- For each active recurrence: count future instances (date >= today), generate enough to reach `buffer_ahead`
- Job no longer uses `next_date` for generation — it counts existing future transactions

### R4: Edit Recurrence with Propagation
- Edit form includes `buffer_ahead` field
- On update, user can choose to propagate changes to all future instances
- Propagation updates: description, value, account_id, category_id, tags on future transactions

### R5: Planning Reads Only Transactions
- `PlanningService` removes `projectActiveRecurrences()` entirely
- All projection comes from real transactions in the date range
- No duplicate values (recurrence rule + generated instance)

### R6: Frontend Forms
- Add `buffer_ahead` input with hint tooltip to income/expense creation forms
- Add `buffer_ahead` field to recurrence edit form
- Add propagation checkbox/modal to recurrence edit form
- Sonner notifications for all actions

### R7: Workspace Isolation
- All queries scoped by `workspace_id`
- All policies enforce workspace membership

## ✅ Acceptance Criteria

| ID | Criteria |
|----|----------|
| AC1 | Creating a monthly recurrence with start_date 3 months ago and buffer=12 generates 3 past + 12 future transactions |
| AC2 | Buffer count (max 50) only considers future instances (date >= today) |
| AC3 | Editing a recurrence with "propagate" updates all future instances |
| AC4 | Daily job replenishes buffer to exact configured count |
| AC5 | Planning screen shows no duplicate values and reads only from transactions |
| AC6 | All recurrence actions show Sonner notifications |
| AC7 | All data isolated by workspace_id |

## 🏗️ Architecture Decisions

| ID | Decision |
|----|----------|
| D-66 | Buffer instances are real Transaction rows (not in-memory projection) |
| D-67 | `next_date` column is deprecated but kept for migration compatibility; buffer job uses COUNT of future transactions |
| D-68 | Retroactive generation on creation is synchronous (within DB transaction) |
| D-69 | Propagate-to-future on edit is synchronous (within DB transaction) |
| D-70 | PlanningService reads only from transactions table |

## 📋 Implementation Tasks

### Phase 1: Database & Model
- [ ] T1: Migration to add `buffer_ahead` to recurrences
- [ ] T2: Update Recurrence model (fillable, casts)
- [ ] T3: Update RecurrenceResource to include `buffer_ahead`

### Phase 2: Service Layer
- [ ] T4: Refactor RecurrenceService::create() to generate buffer
- [ ] T5: Refactor RecurrenceService::createWithFirstInstance() for retroactive + buffer
- [ ] T6: Add RecurrenceService::countFutureInstances()
- [ ] T7: Add RecurrenceService::generateBufferInstances()
- [ ] T8: Add RecurrenceService::propagateToFuture() (replaces async scope job for edit)
- [ ] T9: Refactor ProcessRecurrencesJob to maintain buffer

### Phase 3: Controllers & Requests
- [ ] T10: Update StoreIncomeRequest — add `buffer_ahead` validation
- [ ] T11: Update StoreTransactionRequest — add `buffer_ahead` validation
- [ ] T12: Update UpdateRecurrenceRequest — add `buffer_ahead`, `propagate_to_future`
- [ ] T13: Update RecurrenceController::update() — handle propagation

### Phase 4: Planning Refactor
- [ ] T14: Refactor PlanningService — remove projectActiveRecurrences, read only transactions
- [ ] T15: Update PlanningController if needed

### Phase 5: Frontend
- [ ] T16: Add buffer_ahead input + hint to Incomes/Create.tsx
- [ ] T17: Add buffer_ahead input + hint to Transactions/Create.tsx
- [ ] T18: Add buffer_ahead field to Recurrences/Edit.tsx
- [ ] T19: Add propagate_to_future modal/checkbox to Recurrences/Edit.tsx
- [ ] T20: Ensure Sonner notifications on all recurrence actions

### Phase 6: Tests & Quality
- [ ] T21: Feature tests for buffer generation (creation with past start_date)
- [ ] T22: Feature tests for buffer maintenance job
- [ ] T23: Feature tests for propagate-to-future on edit
- [ ] T24: Update PlanningService tests
- [ ] T25: Smoke tests for modified routes
- [ ] T26: Quality gates (composer quality, npm run quality)
