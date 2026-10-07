# Cartões de Crédito V2 — Tasks

**Design**: `.specs/features/cartoes-v2/design.md`
**Spec**: `.specs/features/cartoes-v2/spec.md`
**Status**: Approved

---

## Execution Plan

### Phase 1: Database Foundation (Sequential)

```
T1 → T2
```

### Phase 2: Backend Core — TransactionService (Sequential)

```
T3 → T4 → T5 → T6 → T7
```

### Phase 3: Backend Core — BillService + CreditCardService + Jobs (Sequential within, parallel across services)

```
T8 → T9 → T10 → T11
```

### Phase 4: Recurrence Card Support (Sequential)

```
T12 → T13
```

### Phase 5: Form Requests (Sequential)

```
T14 → T15
```

### Phase 6: Controllers (Sequential within controller, parallel across controllers)

```
T16 → T17 [P] → T18
                  T19 [P]
```

### Phase 7: Frontend Form Sub-components (Sequential)

```
T20 → T21 → T22
```

### Phase 8: Frontend Form Pages (Sequential)

```
T23 → T24
```

### Phase 9: Frontend — Card Hub + DataTables (Parallel OK)

```
T25 [P]
T26 [P]
```

### Phase 10: Decommission — Remove CardExpenses Silo (Sequential)

```
T27 → T28
```

### Phase 11: Tests — PHPUnit Feature + Smoke (Sequential)

```
T29 → T30 → T31
```

---

## Parallel Execution Map

```
Phase 1:  T1 → T2
Phase 2:  T3 → T4 → T5 → T6 → T7
Phase 3:  T8 → T9 → T10 → T11
Phase 4:  T12 → T13
Phase 5:  T14 → T15
Phase 6:  T16 → T17 [P] → T18
                  T19 [P] ↗
Phase 7:  T20 → T21 → T22
Phase 8:  T23 → T24
Phase 9:  T25 [P]
          T26 [P]
Phase 10: T27 → T28
Phase 11: T29 → T30 → T31
```

---

## Task Breakdown

---

### T1: Migration — Add credit_card_id to recurrences

**What:** Create migration to add nullable `credit_card_id` FK column to `recurrences` table
**Where:** `database/migrations/YYYY_MM_DD_HHMMSS_add_credit_card_id_to_recurrences_table.php`
**Depends on**: None
**Reuses:** Existing migration `2026_07_20_000001_create_recurrences_table.php` as structural reference
**Requirement**: CCV2-05

**Done when:**
- [ ] Migration adds `credit_card_id` (nullable, FK → `credit_cards.uuid`, cascade on delete)
- [ ] `php artisan test` passes with zero failures (no broken tests from migration)

**Tests**: none (schema-only change)
**Gate**: `php artisan test` — verify no test regressions

---

### T2: Migration — Make account_id nullable in recurrences

**What:** Create migration to make `account_id` nullable in `recurrences` table (mutual exclusivity with credit_card_id)
**Where:** `database/migrations/YYYY_MM_DD_HHMMSS_make_account_id_nullable_in_recurrences_table.php`
**Depends on**: T1
**Reuses:** Existing migration `2026_07_20_000001_create_recurrences_table.php`
**Requirement**: CCV2-05

**Done when:**
- [ ] Migration changes `account_id` from NOT NULL to nullable
- [ ] Existing data remains valid (NOT NULL rows unchanged)
- [ ] `php artisan test` passes with zero failures

**Tests**: none (schema-only change)
**Gate**: `php artisan test` — verify no test regressions

---

### T3: Recurrence model — Add credit_card_id to fillable

**What:** Add `credit_card_id` to `$fillable` array and add `creditCard()` belongs-to relationship in Recurrence model
**Where:** `app/Models/Recurrence.php`
**Depends on**: T2
**Reuses:** Existing `account()` relationship as pattern
**Requirement**: CCV2-05

**Done when:**
- [ ] `credit_card_id` in `$fillable`
- [ ] `creditCard()` belongs-to relationship defined
- [ ] `composer quality` passes

**Tests**: none (model-only, tested in T29)
**Gate**: `composer quality`

---

### T4: TransactionService — Extract shared helpers from CardExpenseService

**What:** Extract `resolveBill()`, `syncTags()`, and `ensureBillNotPaid()` as private methods in `TransactionService`, copied/adapted from `CardExpenseService`
**Where:** `app/Services/TransactionService.php`
**Depends on**: None
**Reuses:** `app/Services/CardExpenseService.php` (methods to extract)
**Requirement**: CCV2-01, CCV2-02

**Done when:**
- [ ] `resolveBill()` — resolves or creates bill for card + date via `BillService::findOrCreateBill()`
- [ ] `syncTags()` — syncs tags to transaction (handles null/empty)
- [ ] `ensureBillNotPaid()` — throws ValidationException if bill is Paid
- [ ] All existing tests still pass
- [ ] `composer quality` passes

**Tests**: feature (verified in T29)
**Gate:** `composer quality` + `php artisan test --filter=Transaction`

---

### T5: TransactionService — Card expense create methods

**What:** Add `createCardExpense()` and `createCardInstallment()` methods to `TransactionService`
**Where:** `app/Services/TransactionService.php`
**Depends on**: T4
**Reuses:** `CardExpenseService::createSingle()` and `createInstallment()` as reference implementations; `BillService::findOrCreateBill()`
**Requirement**: CCV2-01 AC3, AC4

**Done when:**
- [ ] `createCardExpense()` — creates single Transaction with `type=Expense`, `account_id=null`, `credit_card_id`, `paid_at=null`, linked to correct bill via `resolveBill()`
- [ ] `createCardInstallment()` — creates N transactions with `value=round(total/N, 2)`, last absorbs remainder, each linked to its period's bill via `resolveBill()`
- [ ] Both recalculate bill total and available limit
- [ ] `composer quality` passes

**Tests**: feature (verified in T29)
**Gate:** `composer quality`

---

### T6: TransactionService — Card expense update and delete methods

**What:** Add `updateCardSingle()`, `updateCardGroup()`, `deleteCardSingle()`, `deleteCardGroup()` methods with bill-not-paid guard and re-bucketing on date change
**Where:** `app/Services/TransactionService.php`
**Depends on**: T5
**Reuses:** `CardExpenseService::updateSingle()`, `updateGroup()`, `deleteSingle()`, `deleteGroup()` as reference
**Requirement**: CCV2-01 AC10

**Done when:**
- [ ] `updateCardSingle()` — updates one transaction, handles date change with re-bucketing
- [ ] `updateCardGroup()` — updates this and future installments
- [ ] `deleteCardSingle()` — deletes one, recalculates bill + limit, guarded by `ensureBillNotPaid()`
- [ ] `deleteCardGroup()` — deletes this and future, recalculates bills + limit
- [ ] All methods enforce bill-not-paid immutability
- [ ] `composer quality` passes

**Tests**: feature (verified in T29)
**Gate:** `composer quality`

---

### T7: TransactionService — Pay/unpay guard for card transactions

**What:** Add guard in `pay()` and `unpay()` methods to reject transactions with `credit_card_id` ≠ null
**Where:** `app/Services/TransactionService.php`
**Depends on**: T4
**Reuses:** Existing `pay()` and `unpay()` methods (modify)
**Requirement**: CCV2-02

**Done when:**
- [ ] `pay()` throws `ValidationException` with "Despesas de cartão são pagadas através da fatura do cartão" when `credit_card_id` is present
- [ ] `unpay()` throws same exception
- [ ] Existing account-based pay/unpay still works
- [ ] `composer quality` passes

**Tests**: feature (verified in T29)
**Gate:** `composer quality`

---

### T8: BillService — payBill marks all expenses paid

**What:** Modify `payBill()` to set `paid_at` on ALL non-deleted transactions linked to the bill within the same DB transaction; modify `undoPayment()` to revert `paid_at` to null
**Where:** `app/Services/BillService.php`
**Depends on**: None
**Reuses:** Existing `payBill()` and `undoPayment()` as base
**Requirement**: CCV2-03 AC1, AC2

**Done when:**
- [ ] `payBill()` sets `paid_at = now()` on all transactions of the bill within the DB transaction
- [ ] `undoPayment()` reverts `paid_at = null` on all transactions of the bill
- [ ] Existing payment mechanics preserved (debit Transaction, bill status=Paid, recalculations)
- [ ] `composer quality` passes

**Tests**: feature (verified in T29)
**Gate:** `composer quality`

---

### T9: BillService — closeBillsBefore closes empty bills

**What:** Modify `closeBillsBefore()` to close ALL Open bills with `closing_date < today`, including empty ones (removes lazy skip of empty bills)
**Where:** `app/Services/BillService.php`
**Depends on**: None
**Reuses:** Existing `closeBillsBefore()` and `closeBill()` as base
**Requirement**: CCV2-04 AC3, CCV2-06 AC4 (partial)

**Done when:**
- [ ] `closeBillsBefore()` no longer skips bills with zero expenses
- [ ] Empty bills close uniformly (status → Closed)
- [ ] Existing behavior for non-empty bills unchanged
- [ ] `composer quality` passes

**Tests**: feature (verified in T29)
**Gate:** `composer quality`

---

### T10: PreCreateBillsJob — New daily job for 13-month horizon

**What:** Create new `PreCreateBillsJob` that ensures every non-archived card has bills up to 13 months ahead, creating missing ones idempotently
**Where:** `app/Jobs/PreCreateBillsJob.php`
**Depends on**: T11 (depends on `CreditCardService::preCreateBills()`)
**Reuses:** `CloseBillsJob` as structural pattern; `BillService::findOrCreateBill()` for idempotency
**Requirement**: CCV2-04 AC2

**Done when:**
- [ ] Job iterates all non-archived cards in workspace
- [ ] Calls `CreditCardService::preCreateBills()` for each card
- [ ] Per-card try/catch resilience (logs error, continues)
- [ ] Scheduled daily at 00:00 in `routes/console.php`
- [ ] `composer quality` passes

**Tests**: feature (verified in T29)
**Gate:** `composer quality`

---

### T11: CreditCardService — preCreateBills method + call on create

**What:** Add `preCreateBills()` method to `CreditCardService` that creates 13 bills (current cycle + 12 future) synchronously; call it at the end of `create()`
**Where:** `app/Services/CreditCardService.php`
**Depends on**: None
**Reuses:** `BillService::computeBillPeriod()`, `computeClosingDate()`, `computeDueDate()` for period computation; `BillStatus::Open` enum
**Requirement**: CCV2-04 AC1

**Done when:**
- [ ] `preCreateBills()` creates 13 CreditCardBill rows with `status=Open`, `total_amount=0`, computed `closing_date`/`due_date`
- [ ] `create()` calls `preCreateBills()` after card creation
- [ ] Idempotent — safe to call twice (unique constraint protects)
- [ ] `composer quality` passes

**Tests**: feature (verified in T29)
**Gate:** `composer quality`

---

### T12: RecurrenceService — Constructor injection of BillService + CreditCardService

**What:** Add `BillService` and `CreditCardService` as constructor dependencies to `RecurrenceService`; add `validatePaidBillCollision()` and `resolveCardBill()` private methods
**Where:** `app/Services/RecurrenceService.php`
**Depends on**: T3
**Reuses:** Existing constructor pattern (already injects `AccountService`)
**Requirement**: CCV2-05

**Done when:**
- [ ] Constructor accepts `BillService` and `CreditCardService`
- [ ] `validatePaidBillCollision(card, start_date)` — checks if any monthly occurrence between start_date and today falls on a PAID bill of that card
- [ ] `resolveCardBill(card, date)` — finds or creates bill for the period
- [ ] `composer quality` passes

**Tests**: feature (verified in T29)
**Gate:** `composer quality`

---

### T13: RecurrenceService — createWithBuffer card support

**What:** Extend `createWithBuffer()` to accept `credit_card_id` as alternative to `account_id`; materialize occurrences as Transactions with card fields and bill links; validate paid bill collision
**Where:** `app/Services/RecurrenceService.php`
**Depends on**: T12
**Reuses:** Existing `createWithBuffer()` for account-based logic as structural base
**Requirement**: CCV2-05 AC1, AC2, AC3, AC4, AC5

**Done when:**
- [ ] `createWithBuffer()` accepts `credit_card_id` (nullable, mutually exclusive with `account_id`)
- [ ] Card occurrences created with `credit_card_id`, `paid_at=null`, linked to period's bill via `resolveCardBill()`
- [ ] Installments > 1 rejected with "Despesas recorrentes em cartão não podem ser parceladas"
- [ ] Paid bill collision validated before creation
- [ ] Retroactive occurrences on CLOSED (unpaid) bills allowed
- [ ] Future occurrences on OPEN bills allowed
- [ ] `composer quality` passes

**Tests**: feature (verified in T29)
**Gate:** `composer quality`

---

### T14: StoreTransactionRequest — Unified validation for card/account

**What:** Modify `StoreTransactionRequest` to accept `credit_card_id` OR `account_id` (XOR validation), add conditional rules for `installments`, `total_value`, and recurrence-with-card
**Where:** `app/Http/Requests/StoreTransactionRequest.php`
**Depends on**: None (but tested end-to-end in T29 after T16)
**Reuses:** `StoreCardExpenseRequest` rules as reference (to be deleted in T27)
**Requirement**: CCV2-01 AC2, AC5, AC6, AC7, AC8; CCV2-05 AC3

**Done when:**
- [ ] `account_id` no longer `required` — changed to `nullable`, validated only when present
- [ ] `credit_card_id` added: `nullable`, exists in credit_cards, belongs to workspace, not archived
- [ ] Mutual exclusivity: XOR validation — both present = error, neither present = error
- [ ] `installments`: integer 1-48, conditional on card
- [ ] `total_value`: required when installments > 1
- [ ] When recurrence is ON and card is selected, installments locked to 1 (validated)
- [ ] Error messages in pt-BR per spec
- [ ] `composer quality` passes

**Tests**: feature (verified in T29)
**Gate:** `composer quality`

---

### T15: UpdateTransactionRequest — Card scope support

**What:** Modify `UpdateTransactionRequest` to handle `scope` (single/group) for card installments and conditional card-specific rules
**Where:** `app/Http/Requests/UpdateTransactionRequest.php`
**Depends on**: None
**Reuses:** `UpdateCardExpenseRequest` rules as reference (to be deleted in T27)
**Requirement**: CCV2-01 AC10

**Done when:**
- [ ] `scope` field: `sometimes|in:single,group` for card installment editing
- [ ] Date change triggers re-bucketing (validated as date)
- [ ] `credit_card_id` and `account_id` immutable on update (not in rules)
- [ ] `composer quality` passes

**Tests**: feature (verified in T29)
**Gate:** `composer quality`

---

### T16: TransactionController — Store branches to card methods

**What:** Modify `store()` in `TransactionController` to branch: if `credit_card_id` present → `TransactionService::createCardExpense()` or `createCardInstallment()`; update `datatable()` to support `credit_card_id` and `credit_card_bill_id` filters
**Where:** `app/Http/Controllers/TransactionController.php`
**Depends on**: T5, T7, T14
**Reuses:** Existing `store()` and `datatable()` as base
**Requirement**: CCV2-01 AC3, AC4; CCV2-07 AC2, AC3, AC4

**Done when:**
- [ ] `store()` detects `credit_card_id` → routes to card methods
- [ ] `store()` detects `is_recurring` + `credit_card_id` → routes to `RecurrenceService::createWithBuffer()` with card
- [ ] `datatable()` eager-loads `creditCard` and `bill` relationships
- [ ] `datatableConfig()` has filters for `credit_card_id` and `credit_card_bill_id`
- [ ] Bill filter overrides month scoping when active
- [ ] `composer quality` passes

**Tests**: feature (verified in T29)
**Gate:** `composer quality`

---

### T17: CreditCardController — Pre-create bills on store + on-demand close on show

**What:** Modify `store()` in `CreditCardController` to call `CreditCardService::preCreateBills()` after card creation (already handled inside `CreditCardService::create()` after T11 — verify); modify `show()` to close any Open bill with `closing_date < today` on-demand before rendering
**Where:** `app/Http/Controllers/CreditCardController.php`
**Depends on**: T11
**Reuses:** Existing `store()` and `show()` as base
**Requirement**: CCV2-04 AC5; CCV2-06

**Done when:**
- [ ] `store()` works correctly (preCreateBills called inside `CreditCardService::create()` — verify no duplicate call needed)
- [ ] `show()` closes stale Open bills on-demand via `BillService::closeBill()` before loading data
- [ ] `composer quality` passes

**Tests**: feature (verified in T29)
**Gate:** `composer quality`

---

### T18: CreditCardBillController — Pay routes remain (verify)

**What:** Verify `pay()` and `unpay()` in `CreditCardBillController` work correctly with the updated `BillService` (paid_at marking added in T8). No code changes expected, just verification and updated tests
**Where:** `app/Http/Controllers/CreditCardBillController.php` (verify only)
**Depends on**: T8
**Reuses:** Existing controller unchanged
**Requirement**: CCV2-03

**Done when:**
- [ ] Controller unchanged (verified)
- [ ] `composer quality` passes

**Tests**: feature (verified in T29)
**Gate:** `composer quality`

---

### T19: RecurrenceController + RecurrenceResource — Card support

**What:** Update `RecurrenceResource` to include `credit_card` (CreditCardResource whenLoaded); update `datatableConfig()` in `RecurrenceController` to support `credit_card_id` filter; add eager loading for `creditCard`
**Where:** `app/Http/Resources/RecurrenceResource.php`, `app/Http/Controllers/RecurrenceController.php`
**Depends on**: T3
**Reuses:** Existing `account` field in RecurrenceResource as pattern
**Requirement**: CCV2-05 AC8

**Done when:**
- [ ] `RecurrenceResource` includes `credit_card` field (CreditCardResource whenLoaded)
- [ ] `RecurrenceController::datatable()` eager-loads `creditCard`
- [ ] `datatableConfig()` has `credit_card_id` filter
- [ ] `composer quality` passes

**Tests**: feature (verified in T29)
**Gate:** `composer quality`

---

### T20: Frontend — PaymentMethodSelector component

**What:** Create `PaymentMethodSelector` sub-component: toggle between "Conta" (default) and "Cartão de crédito", emits selection to parent form
**Where:** `resources/js/Components/Transactions/PaymentMethodSelector.tsx`
**Depends on**: None
**Reuses:** shadcn/ui `ToggleGroup` or `RadioGroup` primitives
**Requirement**: CCV2-01 AC1, AC2

**Done when:**
- [ ] Component renders toggle with "Conta" and "Cartão de crédito" options
- [ ] "Conta" selected by default
- [ ] Emits `onChange` with `account` or `card` value
- [ ] TypeScript strict mode compliant
- [ ] `npm run quality` passes

**Tests**: none (tested in T30/T31 via smoke + E2E)
**Gate:** `npm run quality`

---

### T21: Frontend — CardExpenseFields component

**What:** Create `CardExpenseFields` sub-component: card select (active cards of workspace), installments input (1-48, default 1), total_value input (shown when installments > 1)
**Where:** `resources/js/Components/Transactions/CardExpenseFields.tsx`
**Depends on**: None
**Reuses:** shadcn/ui `Select`, `Input`, `Label` primitives; existing card data from workspace
**Requirement**: CCV2-01 AC2, AC7

**Done when:**
- [ ] Card select lists active cards of workspace
- [ ] Installments input: integer 1-48, default 1
- [ ] When installments > 1, "Valor" label becomes "Valor total" and total_value input appears
- [ ] Props interface: `cards`, `form` (InertiaForm), `locked` (boolean for recurrence)
- [ ] TypeScript strict mode compliant
- [ ] `npm run quality` passes

**Tests**: none (tested in T30/T31 via smoke + E2E)
**Gate:** `npm run quality`

---

### T22: Frontend — AccountFields component (extract existing)

**What:** Extract the existing account `<Select>` from `Transactions/Create.tsx` into a standalone `AccountFields` sub-component
**Where:** `resources/js/Components/Transactions/AccountFields.tsx`
**Depends on**: None
**Reuses:** Existing account select code from `Transactions/Create.tsx`
**Requirement**: CCV2-01

**Done when:**
- [ ] Component renders account select (active accounts of workspace)
- [ ] Props interface: `accounts`, `form` (InertiaForm)
- [ ] Identical behavior to existing inline account select
- [ ] TypeScript strict mode compliant
- [ ] `npm run quality` passes

**Tests**: none (tested in T30/T31 via smoke + E2E)
**Gate:** `npm run quality`

---

### T23: Frontend — Transactions/Create.tsx unified form

**What:** Refactor `Transactions/Create.tsx` to use `PaymentMethodSelector`, `AccountFields`, `CardExpenseFields`, and `RecurrenceFields`; handle `?payment_method=card&card_id={uuid}` query params for pre-fill from card page
**Where:** `resources/js/Pages/Transactions/Create.tsx`
**Depends on**: T20, T21, T22
**Reuses:** Existing form as base, extracts sections into sub-components
**Requirement**: CCV2-01 AC1, AC2, AC8, AC9

**Done when:**
- [ ] Form starts with `PaymentMethodSelector` (Conta default)
- [ ] Selecting "Conta" → shows `AccountFields`
- [ ] Selecting "Cartão" → shows `CardExpenseFields`
- [ ] Recurrence locked to 1x when card selected (installments hidden/locked)
- [ ] Query params pre-fill: `?payment_method=card&card_id={uuid}` opens with card selected
- [ ] `RecurrenceFields` extracted as sub-component (if not already)
- [ ] TypeScript strict mode compliant
- [ ] `npm run quality` passes

**Tests**: none (tested in T30/T31 via smoke + E2E)
**Gate:** `npm run quality`

---

### T24: Frontend — Transactions/Edit.tsx card support

**What:** Refactor `Transactions/Edit.tsx` to support card expense editing: show `CardExpenseFields` when transaction has `credit_card_id`, show scope prompt (single/group) for installments, show pay-unavailable notice
**Where:** `resources/js/Pages/Transactions/Edit.tsx`
**Depends on**: T20, T21, T22
**Reuses:** Existing edit form as base
**Requirement**: CCV2-01 AC10; CCV2-02 AC4

**Done when:**
- [ ] Detects `transaction.credit_card_id` → shows `CardExpenseFields` instead of `AccountFields`
- [ ] Shows scope radio ("Apenas esta parcela" / "Esta e futuras") for installment groups
- [ ] Shows paid bill warning banner (existing, preserved)
- [ ] `composer quality` passes

**Tests**: none (tested in T30/T31 via smoke + E2E)
**Gate:** `npm run quality`

---

### T25: Frontend — Cards/Show.tsx hub redesign

**What:** Redesign `Cards/Show.tsx` as card hub: 4 limit cards (Limite total / Consumido / Disponível / Total da fatura), invoice selector (month-picker style), invoice expenses list, inline bill payment
**Where:** `resources/js/Pages/Cards/Show.tsx`
**Depends on**: T17 (controller data shape)
**Reuses:** shadcn/ui `Card` primitives; existing month-picker style from `Transactions/Index.tsx`; `Cards/Show.tsx` as base
**Requirement**: CCV2-06 (all ACs)

**Done when:**
- [ ] 4 limit cards displayed: Limite total, Consumido, Disponível, Total da fatura em vista
- [ ] Invoice selector lists all bills (past, current, future pre-created) in month-picker style
- [ ] Default selected bill = current cycle
- [ ] Bill details shown: MM/YYYY, closing date, due date, status, total
- [ ] Expenses of selected bill listed with installment badges
- [ ] CLOSED bill shows "Marcar fatura como paga" with account select + confirm
- [ ] OPEN bill shows hint "Aguardando fechamento em {closing_date}"
- [ ] PAID bill shows payment info + "Desfazer pagamento"
- [ ] Empty bill shows empty state
- [ ] "Nova despesa neste cartão" redirects to form with card pre-filled
- [ ] TypeScript strict mode compliant
- [ ] `npm run quality` passes

**Tests**: none (tested in T30/T31 via smoke + E2E)
**Gate:** `npm run quality`

---

### T26: Frontend — Transactions/Index.tsx card column + filters

**What:** Add "Cartão" column to expenses DataTable; add `credit_card_id` and `credit_card_bill_id` filters; hide pay button for card expenses; status column shows bill-derived status
**Where:** `resources/js/Pages/Transactions/Index.tsx`, `resources/js/Components/DataTable/` (if needed)
**Depends on**: T16 (backend filter support)
**Reuses**: Existing DataTable infrastructure, existing filter patterns
**Requirement**: CCV2-07 (all ACs)

**Done when:**
- [ ] "Cartão" column shows card name (empty for account expenses)
- [ ] Card filter: select of workspace's cards
- [ ] Bill filter: select of bills labeled "Cartão — MM/YYYY"
- [ ] Bill filter overrides month scoping when active
- [ ] Pay/unpay buttons hidden for card expense rows
- [ ] Status column shows "Na fatura (Aberta)", "Na fatura (Fechada)", or "Paga"
- [ ] TypeScript strict mode compliant
- [ ] `npm run quality` passes

**Tests**: none (tested in T30/T31 via smoke + E2E)
**Gate:** `npm run quality`

---

### T27: Decommission — Remove CardExpenses backend

**What:** Delete `CardExpenseController`, `CardExpenseService`, `StoreCardExpenseRequest`, `UpdateCardExpenseRequest`; remove `cards/{card}/expenses/*` routes
**Where:**
- Delete: `app/Http/Controllers/CardExpenseController.php`
- Delete: `app/Services/CardExpenseService.php`
- Delete: `app/Http/Requests/StoreCardExpenseRequest.php`
- Delete: `app/Http/Requests/UpdateCardExpenseRequest.php`
- Edit: `routes/web.php` (remove card expense routes)
**Depends on**: T16 (controller routing verified), T15 (card edit in unified form)
**Reuses**: N/A (deletions only)
**Requirement**: CCV2-01 AC11

**Done when:**
- [ ] All 4 files deleted
- [ ] Routes removed from `routes/web.php`
- [ ] `cards/{card}/expenses/*` returns 404
- [ ] `composer quality` passes
- [ ] `php artisan test` passes with zero failures

**Tests**: none (deletions + test rewrite in T29)
**Gate:** `composer quality` + `php artisan test`

---

### T28: Decommission — Remove CardExpenses frontend

**What:** Delete `CardExpenses/Create.tsx` and `CardExpenses/Edit.tsx` pages
**Where:**
- Delete: `resources/js/Pages/CardExpenses/Create.tsx`
- Delete: `resources/js/Pages/CardExpenses/Edit.tsx`
- Delete: `resources/js/Pages/CardExpenses/` directory if empty
**Depends on**: T23, T24 (unified form handles card creation/editing)
**Reuses**: N/A (deletions only)
**Requirement**: CCV2-01 AC11

**Done when:**
- [ ] Both files deleted
- [ ] No broken imports anywhere
- [ ] `npm run quality` passes

**Tests**: none (deletions)
**Gate:** `npm run quality`

---

### T29: Tests — PHPUnit feature tests (card expenses via unified flow)

**What:** Write PHPUnit feature tests for card expense creation/editing via unified form, pay/unpay guard, bill payment marking expenses, recurrence card support, paid bill collision, pre-create bills, and on-demand closing. Rewrite/remove CardExpenses tests.
**Where:** `tests/Feature/`
**Depends on**: T27 (CardExpenses tests removed/rewritten), all backend tasks
**Reuses:** `CardExpenseTestCase.php` helpers as reference; existing transaction test patterns
**Requirement**: All CCV2 ACs

**Done when:**
- [ ] Card expense creation: single + installment (via unified form POST)
- [ ] Card expense editing: single + group scope + re-bucketing
- [ ] Card expense deletion: single + group + paid bill guard
- [ ] Pay guard: `POST transactions.pay` on card transaction returns 422
- [ ] Bill payment: all linked expenses get `paid_at`; undo reverts
- [ ] Pre-create bills: card creation produces 13 bills
- [ ] CloseBillsJob: closes empty bills uniformly
- [ ] PreCreateBillsJob: maintains 13-month horizon
- [ ] Recurrence card: create with buffer + bill collision validation
- [ ] Form validation: XOR mutual exclusivity, installments rules, recurrence lock
- [ ] Old CardExpenses tests removed or rewritten
- [ ] `php artisan test` passes with all new tests green

**Tests**: feature
**Gate:** `php artisan test` — verify pass count matches expected new tests, zero failures

---

### T30: Tests — PHPUnit smoke tests (new/modified GET routes)

**What:** Write PHPUnit smoke tests for modified GET routes: card show (hub), transactions index (card filters), recurrences index (card filter). Verify `assertOk` + `assertInertia`.
**Where:** `tests/Feature/Cards/CardShowSmokeTest.php` (new), update existing smoke tests
**Depends on**: T25, T26
**Reuses:** Existing smoke test pattern from `tests/Feature/Cards/CardSmokeTest.php` or similar
**Requirement**: CCV2-06, CCV2-07

**Done when:**
- [ ] `cards/{card}` returns 200 + correct Inertia component (hub)
- [ ] `transactions` (expenses index) returns 200 with card column data
- [ ] `recurrences` returns 200 with card data when present
- [ ] `php artisan test --filter=Smoke` passes

**Tests**: smoke
**Gate:** `php artisan test --filter=Smoke` — verify all smoke tests pass

---

### T31: Tests — Cypress E2E card journeys

**What:** Write Cypress E2E tests for critical card journeys: create card → see 13 bills → create card expense (single + installment) → pay bill → verify expenses paid → undo → create card recurrence → verify buffer
**Where:** `cypress/e2e/cards/`
**Depends on**: T29, T30 (all feature/smoke tests green first)
**Reuses:** Existing Cypress patterns, login helpers
**Requirement**: All CCV2 ACs (end-to-end)

**Done when:**
- [ ] E2E: create card → verify 13 bills in DB/API
- [ ] E2E: create single card expense → appears in card hub
- [ ] E2E: create 12x installment → 12 transactions, correct bills
- [ ] E2E: pay closed bill → all expenses marked paid
- [ ] E2E: undo payment → expenses back to unpaid
- [ ] E2E: card recurrence → buffer materialized in bills
- [ ] E2E: paid bill collision blocks recurrence creation
- [ ] `cypress run` passes for card test files

**Tests**: e2e
**Gate:** `npx cypress run --spec "cypress/e2e/cards/**"` — verify all E2E tests pass

---

## Test Coverage Summary

| Task | Tests | Gate |
|------|-------|------|
| T1–T3 | none (migrations + model) | `php artisan test` (regression) |
| T4–T19 | feature (verified in T29) | `composer quality` |
| T20–T26 | none (verified in T30/T31) | `npm run quality` |
| T27–T28 | none (deletions) | `composer quality` + `npm run quality` |
| T29 | feature (PHPUnit) | `php artisan test` |
| T30 | smoke (PHPUnit) | `php artisan test --filter=Smoke` |
| T31 | e2e (Cypress) | `npx cypress run` |

---

## Requirement Traceability

| Req ID | Story | Tasks |
|--------|-------|-------|
| CCV2-01 | Formulário Unificado | T4, T5, T6, T7, T14, T15, T16, T20, T21, T22, T23, T24, T27, T28 |
| CCV2-02 | Despesa Paga via Fatura | T7, T16, T24 |
| CCV2-03 | Fatura Paga Marca Despesas | T8, T18 |
| CCV2-04 | Faturas Pré-criadas | T9, T10, T11, T17 |
| CCV2-05 | Recorrências em Cartão | T1, T2, T3, T12, T13, T14, T16, T19 |
| CCV2-06 | UI do Cartão Hub | T17, T25 |
| CCV2-07 | Despesas na Listagem Geral | T16, T26 |
