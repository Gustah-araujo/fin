# Despesas de Cartão de Crédito — Tasks

**Design:** `.specs/features/despesas-cartao/design.md`
**Spec:** `.specs/features/despesas-cartao/spec.md`
**Status:** Draft

---

## TDD-First Ordering

Every behavioral task follows strict red-green-refactor:

1. **RED**: Write the PHPUnit test file — run it, confirm it FAILS for the right reason (class not found, route 404, etc.)
2. **GREEN**: Implement the minimum code (service, controller, routes, FormRequest, policy, resource) to make ALL tests in that file pass
3. **REFACTOR**: Clean up, re-run tests, confirm still green

Structural tasks (T1) are not TDD — they are verified by `migrate:fresh` + `optimize:clear`.

---

## Execution Plan

```
Phase 1: Structural Foundation (sequential)
  T1

Phase 2: Core Service — Available Limit (sequential, TDD)
  T1 ──→ T2

Phase 3: Card Expense Creation — Full Stack (sequential, TDD)
  T2 ──→ T3

Phase 4: Installments (sequential, TDD)
  T3 ──→ T4

Phase 5: Bill Operations (parallel, TDD — after T2+T3)
             ┌→ T5  [P]  (Bill payment)
  T2,T3 ────┼→ T6  [P]  (Bill closure)
             └→ T7  [P]  (Bill view)

Phase 6: Update & Delete (parallel, TDD — after T4)
             ┌→ T8  [P]  (Update + scope)
  T4 ───────┼→ T9  [P]  (Delete + scope)
             └→ T10 [P]  (Authorization)

Phase 7: Frontend (parallel — after T3+T5+T7)
             ┌→ T11 [P]  (Cards/Show + components)
  T3,T5,T7 ─┼→ T12 [P]  (CardExpenses/Create)
             └→ T13 [P]  (CardExpenses/Edit + Bills/Show + PaymentModal)

Phase 8: E2E (sequential — after all)
  T5-T13 ──→ T14
```

---

## Task Breakdown

### T1: Migrations, BillStatus Enum, CreditCardBill Model, Factories, Model Relationships

**What:** Create all structural foundation: 3 migrations, BillStatus enum, CreditCardBill model, CreditCardBillFactory, CreditCardBillPolicy skeleton, CardExpenseTestCase base class, and extend existing models (Transaction, CreditCard, Workspace, Category, TransactionFactory) with new fields and relationships.

**Where:**
- `database/migrations/2026_07_15_000001_add_credit_card_columns_to_transactions.php` (new)
- `database/migrations/2026_07_15_000002_create_credit_card_bills_table.php` (new)
- `database/migrations/2026_07_15_000003_add_is_system_to_categories_table.php` (new)
- `app/Enums/BillStatus.php` (new)
- `app/Models/CreditCardBill.php` (new)
- `database/factories/CreditCardBillFactory.php` (new)
- `app/Policies/CreditCardBillPolicy.php` (new — skeleton with viewAny, view, pay, unpay)
- `tests/Feature/CardExpenses/CardExpenseTestCase.php` (new — base class with helpers)
- `app/Models/Transaction.php` (modify — add credit_card_id, credit_card_bill_id, installment_number, installments_total, installment_group_id to fillable + casts; add creditCard() and bill() relationships)
- `app/Models/CreditCard.php` (modify — add transactions() and bills() relationships)
- `app/Models/Workspace.php` (modify — add creditCardBills() relationship)
- `app/Models/Category.php` (modify — add is_system to fillable + casts)
- `database/factories/TransactionFactory.php` (modify — add credit card nullable fields to definition)

**Depends on:** None
**Reuses:** Transaction/CreditCard model patterns (HasUuids via uuid, SoftDeletes, getRouteKeyName, casts), existing TransactionFactory, BillStatus enum pattern from TransactionType
**Requirement:** CCXP-01 (schema), CCXP-02 (schema), CCXP-03 (schema), CCXP-04 (schema)

**Done when:**
- [ ] Migration `add_credit_card_columns_to_transactions` adds: `credit_card_id` (foreignId nullable → credit_cards), `credit_card_bill_id` (foreignId nullable → credit_card_bills), `installment_number` (integer nullable), `installments_total` (integer nullable), `installment_group_id` (uuid nullable)
- [ ] Migration `create_credit_card_bills_table` creates table with: id, uuid (unique), credit_card_id (FK → credit_cards cascade), workspace_id (FK → workspaces cascade), period_year (year), period_month (tinyInteger), closing_date (date), due_date (date), status (string default 'open'), total_amount (decimal 15,2 default 0), closed_at (timestamp nullable), paid_at (timestamp nullable), paid_to_account_id (foreignId nullable → accounts), payment_transaction_id (foreignId nullable → transactions), created_by (FK → users), timestamps, softDeletes, unique(`credit_card_id`, `period_year`, `period_month`), index(`credit_card_id`, `status`)
- [ ] Migration `add_is_system_to_categories_table` adds: `is_system` (boolean default false)
- [ ] BillStatus enum has: Open='open', Closed='closed', Paid='paid' with label() method (Aberta/Fechada/Paga)
- [ ] CreditCardBill model: $fillable (all columns), casts (period_year→integer, period_month→integer, closing_date→date, due_date→date, status→BillStatus, total_amount→decimal:2, closed_at→datetime, paid_at→datetime), getRouteKeyName→uuid, HasFactory + SoftDeletes, relationships (creditCard, workspace, transactions, paymentAccount, paymentTransaction, creator)
- [ ] CreditCardBillFactory: generates valid data (uuid, period_year/month from now, closing_date, due_date, status=open, total_amount=0, created_by→User::factory)
- [ ] CreditCardBillPolicy: viewAny (member), view (member), pay (admin/editor), unpay (admin/editor) — same pattern as TransactionPolicy
- [ ] Transaction model: new fields in $fillable, new casts (installment_number→integer, installments_total→integer), creditCard() and bill() relationships
- [ ] CreditCard model: transactions() and bills() HasMany relationships
- [ ] Workspace model: creditCardBills() HasMany relationship
- [ ] Category model: is_system in $fillable + casts (boolean)
- [ ] TransactionFactory: credit card fields default to null in definition
- [ ] CardExpenseTestCase: abstract class extends TestCase, uses RefreshDatabase, has createWorkspaceWithMember(role), createCard(workspace), createExpenseCategory(workspace), createAccount(workspace, balance) helpers
- [ ] Gate check: `php artisan migrate:fresh --seed` succeeds
- [ ] Gate check: `php artisan optimize:clear` succeeds (no syntax errors)
- [ ] Existing tests still pass: `php artisan test --filter=Card` — all CARD-01 tests still green (no regression)

**Tests:** None (structural — tested implicitly by subsequent TDD tasks)
**Gate:** `php artisan migrate:fresh --seed && php artisan optimize:clear && php artisan test --filter=Card`

**Commit:** `feat(ccxp): add migrations, BillStatus enum, CreditCardBill model and model relationships`

---

### T2: Upgrade CreditCardService — AvailableLimitTest (TDD)

**What:** Write AvailableLimitTest (RED), then upgrade CreditCardService::recalculateAvailableLimit from stub to real implementation, add ensurePaymentCategory method, and add is_system deletion guard in CategoryController.

**Where:**
- `tests/Feature/CardExpenses/AvailableLimitTest.php` (new — TDD: written first)
- `app/Services/CreditCardService.php` (modify — upgrade recalculateAvailableLimit, add ensurePaymentCategory)
- `app/Http/Controllers/CategoryController.php` (modify — reject deletion of is_system categories)

**Depends on:** T1 (Transaction model has credit_card_id, CreditCardBill model exists)
**Reuses:** CreditCardService existing class, CreditCardFactory, TransactionFactory, CategoryFactory, CardExpenseTestCase base
**Requirement:** D-29 (available_limit formula), CCXP-04 (available_limit restoration after payment)

**TDD steps:**
1. Write `AvailableLimitTest.php` with all 6 test methods
2. Run `php artisan test --filter=AvailableLimitTest` — confirm RED (6 failures: recalculateAvailableLimit still stub, returns credit_limit regardless of expenses)
3. Upgrade `recalculateAvailableLimit` to query real card expenses on non-paid bills
4. Add `ensurePaymentCategory` method
5. Add is_system guard to CategoryController@destroy
6. Run `php artisan test --filter=AvailableLimitTest` — confirm GREEN (6 passes)

**Done when:**
- [ ] AvailableLimitTest: `test_available_limit_decreases_on_expense_create` — create card (limit 5000), insert Transaction with credit_card_id directly via factory, call recalculateAvailableLimit, assert available_limit = 5000 - expense_value
- [ ] AvailableLimitTest: `test_available_limit_increases_on_expense_delete` — create expense, recalc (available drops), soft-delete expense, recalc, assert available restored to credit_limit
- [ ] AvailableLimitTest: `test_available_limit_ignores_paid_bill_expenses` — create expense on a bill, mark bill as Paid (factory), recalc, assert available_limit = credit_limit (paid expenses don't count)
- [ ] AvailableLimitTest: `test_available_limit_sums_multiple_expenses` — 3 expenses (100+200+50), recalc, assert available = credit_limit - 350
- [ ] AvailableLimitTest: `test_available_limit_correct_for_installment_partial` — create 3 installment rows directly via factory with same group_id, recalc, assert available = credit_limit - sum(all 3)
- [ ] AvailableLimitTest: `test_recalculate_available_limit_after_credit_limit_change` — update credit_limit, recalc, assert available = new_limit - open expenses
- [ ] `recalculateAvailableLimit` implementation: sums `transactions.value` where `credit_card_id = card->id` AND (bill.status != 'paid' OR bill_id is null) AND transaction not soft-deleted
- [ ] `ensurePaymentCategory(Workspace)`: finds or creates Category with name='Pagamento de Cartão', type=Expense, color='#6B7280', is_system=true
- [ ] CategoryController@destroy: rejects deletion when `is_system = true` with 403 "Esta categoria é gerenciada pelo sistema e não pode ser excluída."
- [ ] Gate check: `php artisan test --filter=AvailableLimitTest` — 6 tests pass
- [ ] Gate check: `php artisan test --filter=Card` — existing CARD-01 tests still pass (no regression from recalculateAvailableLimit upgrade)

**Tests:** Feature (PHPUnit)
**Gate:** `php artisan test --filter="AvailableLimitTest|Card"`

**Commit:** `feat(ccxp): upgrade recalculateAvailableLimit with real expense queries + ensurePaymentCategory`

---

### T3: Card Expense Creation — Full Stack — CardExpenseCreationTest (TDD)

**What:** Write CardExpenseCreationTest (RED), then implement the full creation stack: BillService (findOrCreateBill, computeBillPeriod, computeClosingDate, computeDueDate), CardExpenseService::createSingle, CardExpenseController (create, store), StoreCardExpenseRequest, CardExpensePolicy, routes, CreditCardBillResource, TransactionResource update, CreditCardResource update.

**Where:**
- `tests/Feature/CardExpenses/CardExpenseCreationTest.php` (new — TDD: written first)
- `app/Services/BillService.php` (new — findOrCreateBill, computeBillPeriod, computeClosingDate, computeDueDate, recalculateBillTotal)
- `app/Services/CardExpenseService.php` (new — createSingle, resolveCategoryId, syncTags)
- `app/Http/Controllers/CardExpenseController.php` (new — create, store)
- `app/Http/Requests/StoreCardExpenseRequest.php` (new)
- `app/Policies/CardExpensePolicy.php` (new — or reuse TransactionPolicy pattern registered for CreditCardBill)
- `app/Http/Resources/CreditCardBillResource.php` (new)
- `app/Http/Resources/TransactionResource.php` (modify — add credit_card, installment_number, installments_total, is_installment, installment_label fields)
- `app/Http/Resources/CreditCardResource.php` (modify — add bills relation when loaded)
- `routes/web.php` (modify — add card-expenses routes + cards.show)

**Depends on:** T2 (CreditCardService::recalculateAvailableLimit upgraded)
**Reuses:** TransactionService patterns (DB::transaction, syncTags, resolveCategoryId), TransactionController pattern (authorize → service → redirect), StoreTransactionRequest pattern (withValidator after hooks, pt-BR messages), TransactionResource pattern, BillService computation logic from design
**Requirement:** CCXP-01 (AC1-AC8)

**TDD steps:**
1. Write `CardExpenseCreationTest.php` with all 10 test methods
2. Run `php artisan test --filter=CardExpenseCreationTest` — confirm RED (10 failures: routes 404, classes not found)
3. Implement BillService (compute methods + findOrCreateBill)
4. Implement CardExpenseService::createSingle
5. Create StoreCardExpenseRequest, CardExpenseController, CardExpensePolicy
6. Create CreditCardBillResource, update TransactionResource + CreditCardResource
7. Register routes
8. Run `php artisan test --filter=CardExpenseCreationTest` — confirm GREEN (10 passes)

**Done when:**
- [ ] CardExpenseCreationTest: `test_user_can_create_single_card_expense` — POST store → redirect, DB has Transaction with credit_card_id set, account_id=null, paid_at=null
- [ ] CardExpenseCreationTest: `test_single_expense_associated_to_correct_bill` — POST with date 15/02 on card closing_day=1 → Transaction.credit_card_bill_id matches bill for period 2026/02 (closes 01/03... actually period computed from date ≤ closing_date). Test verifies bill period matches computeBillPeriod logic
- [ ] CardExpenseCreationTest: `test_validation_errors_on_create` — empty POST → assertSessionHasErrors for description, value, date, credit_card_id, category_id
- [ ] CardExpenseCreationTest: `test_card_expense_with_zero_value_rejected` — value=0 → session error
- [ ] CardExpenseCreationTest: `test_card_expense_on_archived_card_rejected` — soft-delete card, POST expense → session error for credit_card_id
- [ ] CardExpenseCreationTest: `test_card_expense_with_income_category_rejected` — category type=Income → session error
- [ ] CardExpenseCreationTest: `test_both_account_and_card_set_rejected` — POST with both account_id and credit_card_id → session error "Uma transação deve ter conta OU cartão"
- [ ] CardExpenseCreationTest: `test_viewer_cannot_create_card_expense` — POST as viewer → 403
- [ ] CardExpenseCreationTest: `test_card_expense_tags_synced` — POST with tags → taggables records exist
- [ ] CardExpenseCreationTest: `test_card_from_other_workspace_404` — POST with card UUID from workspace B via workspace A URL → 404
- [ ] BillService::computeBillPeriod(card, date) returns correct [year, month] per closing_day semantics
- [ ] BillService::computeClosingDate(card, year, month) handles month overflow (day 31 in Feb → 28/29)
- [ ] BillService::computeDueDate(card, year, month) handles month overflow
- [ ] BillService::findOrCreateBill(card, date) finds existing bill or creates new with computed period/closing_date/due_date
- [ ] BillService::recalculateBillTotal(bill) sums non-deleted transactions.value where bill_id = bill.id
- [ ] CardExpenseService::createSingle(workspace, user, card, data) validates card not archived, findOrCreateBill, creates Transaction(credit_card_id, bill_id, paid_at=null), syncs tags, calls recalculateAvailableLimit
- [ ] StoreCardExpenseRequest: validates description, value (or total_value), date, credit_card_id, category_id, installments (optional, 1-48), tags; after() hooks for workspace ownership, card not archived, category type, mutual exclusivity
- [ ] CardExpenseController: create (authorize, load categories/tags, Inertia), store (authorize, service.createSingle, redirect to cards.show)
- [ ] CardExpensePolicy: viewAny (member), create (admin/editor), update (admin/editor), delete (admin only)
- [ ] CreditCardBillResource: uuid, period_year, period_month, period_label, closing_date, due_date, status, status_label, total_amount, closed_at, paid_at, payment_account (whenLoaded), expenses (whenLoaded collection)
- [ ] TransactionResource: adds credit_card (whenLoaded), installment_number, installments_total, is_installment, installment_label fields
- [ ] CreditCardResource: adds bills (whenLoaded collection)
- [ ] Routes: `cards/{card}/expenses/create` (GET), `cards/{card}/expenses` (POST), `cards/{card}` (GET show)
- [ ] All creation operations wrapped in DB::transaction()
- [ ] Gate check: `php artisan test --filter=CardExpenseCreationTest` — 10 tests pass
- [ ] Gate check: `php artisan test --filter=AvailableLimitTest` — still 6 passes (no regression)

**Tests:** Feature (PHPUnit)
**Gate:** `php artisan test --filter="CardExpenseCreationTest|AvailableLimitTest"`

**Commit:** `feat(ccxp): card expense creation — BillService, CardExpenseService, controller, routes, resources`

---

### T4: Installment Purchases — CardExpenseInstallmentTest (TDD)

**What:** Write CardExpenseInstallmentTest (RED), then implement CardExpenseService::createInstallment with installment generation, value rounding, bill bucketing per installment, and update StoreCardExpenseRequest to handle installments > 1.

**Where:**
- `tests/Feature/CardExpenses/CardExpenseInstallmentTest.php` (new — TDD: written first)
- `app/Services/CardExpenseService.php` (modify — add createInstallment method)
- `app/Http/Requests/StoreCardExpenseRequest.php` (modify — add total_value validation when installments > 1)
- `app/Http/Controllers/CardExpenseController.php` (modify — store method branches: installments=1 → createSingle, installments>1 → createInstallment)

**Depends on:** T3 (CardExpenseService::createSingle exists, BillService::findOrCreateBill exists, controller + routes exist)
**Reuses:** BillService::findOrCreateBill (called per installment), CardExpenseService::syncTags, DB::transaction, Str::uuid for installment_group_id
**Requirement:** CCXP-02 (AC1-AC8)

**TDD steps:**
1. Write `CardExpenseInstallmentTest.php` with all 10 test methods
2. Run `php artisan test --filter=CardExpenseInstallmentTest` — confirm RED (10 failures: createInstallment not implemented)
3. Implement `createInstallment` — generate N rows, round values, bucket each into correct bill
4. Update StoreCardExpenseRequest + controller store method for installment path
5. Run `php artisan test --filter=CardExpenseInstallmentTest` — confirm GREEN (10 passes)

**Done when:**
- [ ] CardExpenseInstallmentTest: `test_can_create_installment_purchase` — POST with installments=3, total_value=300 → 3 Transaction rows, same installment_group_id, installment_number 1/2/3, installments_total=3
- [ ] CardExpenseInstallmentTest: `test_installment_values_sum_to_total` — total_value=1000, installments=3 → sum of values == 1000.00 exactly
- [ ] CardExpenseInstallmentTest: `test_installment_last_absorbs_remainder` — total=1000, count=3 → values: 333.33, 333.33, 333.34
- [ ] CardExpenseInstallmentTest: `test_installments_count_one_treated_as_single` — installments=1 → installment_number=null, installments_total=null, installment_group_id=null (delegates to createSingle)
- [ ] CardExpenseInstallmentTest: `test_installments_span_year_boundary` — first date 15/12/2026, count=12 → last installment date 15/11/2027, all dates correct
- [ ] CardExpenseInstallmentTest: `test_installments_spread_across_bills` — card closing_day=1, first date 15/02/2026, count=3 → installment 1 in bill period 2026/02, installment 2 in 2026/03, installment 3 in 2026/04
- [ ] CardExpenseInstallmentTest: `test_installment_count_below_1_rejected` — installments=0 → session error
- [ ] CardExpenseInstallmentTest: `test_installment_count_above_48_rejected` — installments=49 → session error
- [ ] CardExpenseInstallmentTest: `test_installment_total_zero_rejected` — total_value=0 → session error
- [ ] CardExpenseInstallmentTest: `test_installment_indicator_visible_on_bill` — create 3x purchase, GET cards.show → Inertia has expenses with installment_label "1/3", "2/3", "3/3"
- [ ] `createInstallment`: generates installment_group_id (Str::uuid), computes per-installment value = round(total/count, 2), last installment = total - sum(others), creates N Transaction rows with correct installment_number/installments_total/group_id, each bucketed to correct bill via findOrCreateBill, syncs tags on each, calls recalculateAvailableLimit once at end
- [ ] StoreCardExpenseRequest: when installments > 1, requires total_value (gt:0), value becomes optional/ignored; when installments=1 or absent, requires value
- [ ] Controller store: branches on installments > 1 → createInstallment, else → createSingle
- [ ] All operations wrapped in DB::transaction()
- [ ] Gate check: `php artisan test --filter=CardExpenseInstallmentTest` — 10 tests pass
- [ ] Gate check: `php artisan test --filter=CardExpenseCreationTest` — still 10 passes (no regression)

**Tests:** Feature (PHPUnit)
**Gate:** `php artisan test --filter="CardExpenseInstallmentTest|CardExpenseCreationTest"`

**Commit:** `feat(ccxp): installment purchases — N rows, rounding, per-installment bill bucketing`

---

### T5: Bill Payment — BillPaymentTest (TDD) [P]

**What:** Write BillPaymentTest (RED), then implement BillService::payBill + undoPayment, CreditCardBillController (show, pay, unpay), PayBillRequest, bill routes, and bill payment transaction creation.

**Where:**
- `tests/Feature/Bills/BillPaymentTest.php` (new — TDD: written first)
- `app/Services/BillService.php` (modify — add payBill, undoPayment methods)
- `app/Http/Controllers/CreditCardBillController.php` (new — show, pay, unpay)
- `app/Http/Requests/PayBillRequest.php` (new)
- `routes/web.php` (modify — add bills.show, bills.pay, bills.unpay routes)

**Depends on:** T2 (CreditCardService::recalculateAvailableLimit upgraded, ensurePaymentCategory), T3 (BillService exists with findOrCreateBill, CreditCardBillResource exists)
**Reuses:** AccountService::recalculateBalance (for account deduction), CreditCardService::ensurePaymentCategory (for payment category), CreditCardService::recalculateAvailableLimit (for limit restoration), Transaction model (for payment transaction), DB::transaction, TransactionPolicy pattern for CreditCardBillPolicy
**Requirement:** CCXP-04 (AC1-AC7)

**TDD steps:**
1. Write `BillPaymentTest.php` with all 10 test methods
2. Run `php artisan test --filter=BillPaymentTest` — confirm RED (10 failures: bill pay route 404, payBill not implemented)
3. Implement BillService::payBill + undoPayment
4. Create CreditCardBillController, PayBillRequest
5. Register bill routes
6. Run `php artisan test --filter=BillPaymentTest` — confirm GREEN (10 passes)

**Done when:**
- [ ] BillPaymentTest: `test_can_pay_closed_bill` — create bill (status=closed), POST bills.pay with account_id → bill.status=paid, paid_at set, payment_transaction_id set
- [ ] BillPaymentTest: `test_bill_payment_creates_debit_transaction` — after pay → Transaction exists with type=expense, account_id=set, paid_at=now, value=bill.total, description="Pagamento Fatura {card.name} {mm}/{yy}"
- [ ] BillPaymentTest: `test_bill_payment_deducts_account_balance` — account initial 5000, bill total 1000 → after pay, account.current_balance = 4000
- [ ] BillPaymentTest: `test_bill_payment_restores_available_limit` — card limit 5000, expense 1000 on bill → after pay, available_limit = 5000
- [ ] BillPaymentTest: `test_cannot_pay_open_bill` — bill status=open, POST pay → validation error "A fatura ainda está aberta"
- [ ] BillPaymentTest: `test_cannot_pay_already_paid_bill` — bill status=paid, POST pay → validation error "Esta fatura já foi paga"
- [ ] BillPaymentTest: `test_viewer_cannot_pay_bill` — POST pay as viewer → 403
- [ ] BillPaymentTest: `test_payment_category_auto_created_if_missing` — workspace has no "Pagamento de Cartão" category → after pay, category exists with is_system=true
- [ ] BillPaymentTest: `test_insufficient_balance_allows_payment` — account balance 100, bill total 500 → payment succeeds, account goes to -400
- [ ] BillPaymentTest: `test_can_undo_bill_payment` — pay bill, then POST bills.unpay → bill.status=closed, payment Transaction soft-deleted, account.current_balance restored, available_limit recalculated
- [ ] `payBill(bill, account, user)`: DB::transaction → ensurePaymentCategory → create Transaction(expense, account_id, paid_at=now, value=bill.total) → mark bill paid → recalculateBalance(account) → recalculateAvailableLimit(card)
- [ ] `undoPayment(bill)`: DB::transaction → soft-delete payment Transaction → mark bill closed (paid_at=null, paid_to_account_id=null, payment_transaction_id=null) → recalculateBalance(account) → recalculateAvailableLimit(card)
- [ ] CreditCardBillController: show (authorize, load bill + expenses + paymentAccount, Inertia), pay (authorize, validate PayBillRequest, service.payBill, redirect back), unpay (authorize, service.undoPayment, redirect back)
- [ ] PayBillRequest: account_id required + exists + workspace ownership + account not archived + bill status=closed (after hook)
- [ ] Routes: `bills/{bill}` (GET show), `bills/{bill}/pay` (POST), `bills/{bill}/unpay` (POST)
- [ ] Cross-workspace guard: `abort_if(bill->workspace_id !== workspace->id, 404)` in all controller methods
- [ ] Gate check: `php artisan test --filter=BillPaymentTest` — 10 tests pass

**Tests:** Feature (PHPUnit)
**Gate:** `php artisan test --filter=BillPaymentTest`

**Commit:** `feat(ccxp): bill payment — payBill, undoPayment, BillController, PayBillRequest`

---

### T6: Bill Closure — BillClosureTest (TDD) [P]

**What:** Write BillClosureTest (RED), then implement BillService::closeBill + closeBillsBefore, CloseBillsJob, and schedule registration.

**Where:**
- `tests/Feature/Bills/BillClosureTest.php` (new — TDD: written first)
- `app/Services/BillService.php` (modify — add closeBill, closeBillsBefore methods)
- `app/Jobs/CloseBillsJob.php` (new)
- `routes/console.php` (new or modify — register daily schedule)

**Depends on:** T3 (BillService exists, CreditCardBill model exists, bills can be created via findOrCreateBill)
**Reuses:** CreditCardBill model, BillStatus enum, Laravel Schedule facade, ShouldQueue pattern
**Requirement:** CCXP-06 (AC1-AC5)

**TDD steps:**
1. Write `BillClosureTest.php` with all 6 test methods
2. Run `php artisan test --filter=BillClosureTest` — confirm RED (6 failures: closeBillsBefore not implemented)
3. Implement BillService::closeBill + closeBillsBefore
4. Create CloseBillsJob
5. Register schedule in console.php
6. Run `php artisan test --filter=BillClosureTest` — confirm GREEN (6 passes)

**Done when:**
- [ ] BillClosureTest: `test_close_bills_before_closes_eligible_bills` — create open bill with closing_date yesterday, call closeBillsBefore(today) → bill.status=closed, closed_at set
- [ ] BillClosureTest: `test_close_bills_does_not_close_future_bills` — create open bill with closing_date tomorrow, call closeBillsBefore(today) → bill stays open
- [ ] BillClosureTest: `test_close_bills_does_not_close_paid_bills` — create paid bill with closing_date yesterday → stays paid, no change
- [ ] BillClosureTest: `test_open_bill_cannot_receive_expenses_after_closing` — close bill, then create expense with date > closing_date → expense. credit_card_bill_id is a DIFFERENT (new) bill
- [ ] BillClosureTest: `test_job_continues_on_individual_failure` — mock one bill to throw → other bills still closed, no exception propagated
- [ ] BillClosureTest: `test_close_bills_does_not_create_empty_bills` — card with no expenses → no bills created by closeBillsBefore
- [ ] `closeBill(bill)`: set status=Closed, closed_at=now(), save
- [ ] `closeBillsBefore(date)`: query Open bills with closing_date < date → close each in try/catch (log errors, continue) → return count closed
- [ ] CloseBillsJob: implements ShouldQueue, handle() calls BillService::closeBillsBefore(now()), catches exceptions and logs
- [ ] Schedule: `Schedule::call(fn () => CloseBillsJob::dispatch())->dailyAt('00:00')` registered in routes/console.php
- [ ] Gate check: `php artisan test --filter=BillClosureTest` — 6 tests pass

**Tests:** Feature (PHPUnit)
**Gate:** `php artisan test --filter=BillClosureTest`

**Commit:** `feat(ccxp): bill closure — closeBillsBefore, CloseBillsJob, daily schedule`

---

### T7: Bill View — BillViewTest (TDD) [P]

**What:** Write BillViewTest (RED), then implement CreditCardController@show (card detail with open bill + historical bills) and ensure CreditCardBillResource renders correctly.

**Where:**
- `tests/Feature/Bills/BillViewTest.php` (new — TDD: written first)
- `app/Http/Controllers/CreditCardController.php` (modify — add show method)

**Depends on:** T3 (CreditCardBillResource exists, CardExpenseService creates expenses that appear on bills, routes for cards.show registered)
**Reuses:** CreditCardResource (already extended in T3 with bills whenLoaded), CreditCardBillResource (created in T3), CreditCardController existing class
**Requirement:** CCXP-03 (AC1-AC6)

**TDD steps:**
1. Write `BillViewTest.php` with all 7 test methods
2. Run `php artisan test --filter=BillViewTest` — confirm RED (7 failures: show method may be missing or incomplete)
3. Implement CreditCardController@show with eager loading (card + bills + open bill + open bill expenses)
4. Run `php artisan test --filter=BillViewTest` — confirm GREEN (7 passes)

**Done when:**
- [ ] BillViewTest: `test_card_show_displays_open_bill` — create card + expense → GET cards.show → Inertia has openBill with expenses array
- [ ] BillViewTest: `test_card_show_displays_card_info` — GET cards.show → Inertia has card with name, credit_limit, available_limit, closing_day, due_day
- [ ] BillViewTest: `test_empty_open_bill_shows_empty_state` — card with no expenses → Inertia openBill is null OR openBill.expenses = []
- [ ] BillViewTest: `test_card_show_lists_previous_bills` — create bill for previous month (factory), mark closed → GET cards.show → Inertia has bills collection with the historical bill
- [ ] BillViewTest: `test_card_show_available_limit_from_persisted_column` — set card.available_limit to specific value in DB → GET cards.show → Inertia card.available_limit matches DB value (not computed on-the-fly)
- [ ] BillViewTest: `test_bill_show_displays_paid_bill_info` — create paid bill (factory with status=paid, paid_at, paid_to_account_id) → GET bills.show → Inertia has bill with paid_at, payment_account loaded
- [ ] BillViewTest: `test_bill_show_displays_closed_bill_info` — create closed bill → GET bills.show → Inertia has bill with status=closed, closing_date, due_date
- [ ] CreditCardController@show: abort_if workspace mismatch 404, authorize viewAny, eager-load card.bills (latest 12), find open bill + load expenses (with category, tags, ordered by date + installment_number), return Inertia with card (CreditCardResource), openBill (CreditCardBillResource or null), bills (CreditCardBillResource collection)
- [ ] CreditCardBillController@show: abort_if workspace mismatch 404, authorize view, load bill + expenses + paymentAccount, return Inertia with bill (CreditCardBillResource)
- [ ] Gate check: `php artisan test --filter=BillViewTest` — 7 tests pass

**Tests:** Feature (PHPUnit)
**Gate:** `php artisan test --filter=BillViewTest`

**Commit:** `feat(ccxp): bill view — CreditCardController@show, CreditCardBillController@show`

---

### T8: Installment Update with Scope — CardExpenseUpdateTest (TDD) [P]

**What:** Write CardExpenseUpdateTest (RED), then implement CardExpenseService::updateSingle + updateGroup, UpdateCardExpenseRequest (with scope field), and CardExpenseController (edit, update).

**Where:**
- `tests/Feature/CardExpenses/CardExpenseUpdateTest.php` (new — TDD: written first)
- `app/Services/CardExpenseService.php` (modify — add updateSingle, updateGroup methods)
- `app/Http/Requests/UpdateCardExpenseRequest.php` (new)
- `app/Http/Controllers/CardExpenseController.php` (modify — add edit, update methods)

**Depends on:** T4 (installments exist, createInstallment works, controller + routes exist)
**Reuses:** CardExpenseService::syncTags, BillService::findOrCreateBill (for re-bucketing on date change), BillService::recalculateBillTotal, CreditCardService::recalculateAvailableLimit, DB::transaction
**Requirement:** CCXP-05 (AC1-AC3, AC8)

**TDD steps:**
1. Write `CardExpenseUpdateTest.php` with all 5 test methods
2. Run `php artisan test --filter=CardExpenseUpdateTest` — confirm RED (5 failures: update methods not implemented)
3. Implement updateSingle + updateGroup in CardExpenseService
4. Create UpdateCardExpenseRequest, add edit/update to controller
5. Run `php artisan test --filter=CardExpenseUpdateTest` — confirm GREEN (5 passes)

**Done when:**
- [ ] CardExpenseUpdateTest: `test_edit_single_scope_updates_one_row` — create 3x installment, PUT update installment 2 with scope=single, change description → only installment 2 changed, 1 and 3 unchanged
- [ ] CardExpenseUpdateTest: `test_edit_group_scope_updates_future_installments` — create 3x installment, PUT update installment 2 with scope=group, change description → installments 2 and 3 changed, 1 unchanged
- [ ] CardExpenseUpdateTest: `test_edit_installment_re_buckets_on_date_change` — create expense, PUT with new date in different bill cycle → transaction.credit_card_bill_id changes to correct bill
- [ ] CardExpenseUpdateTest: `test_cannot_edit_installment_on_paid_bill` — create expense on paid bill, PUT update → validation error "Não é possível editar parcelas de faturas já pagas"
- [ ] CardExpenseUpdateTest: `test_edit_updates_tags` — PUT with new tags → tags synced on updated row(s)
- [ ] `updateSingle(transaction, data)`: update fields on one row, if date changed → re-bucket to correct bill via findOrCreateBill, sync tags, recalculateBillTotal for old + new bill, recalculateAvailableLimit. Reject if bill is paid.
- [ ] `updateGroup(installment, data)`: update all rows with same installment_group_id AND installment_number >= current, sync tags on each, recalculateBillTotal for affected bills, recalculateAvailableLimit. Reject if any affected bill is paid.
- [ ] UpdateCardExpenseRequest: same rules as Store but all `sometimes`, plus `scope` => required|in:single,group
- [ ] CardExpenseController: edit (authorize, load transaction + categories + tags + card, Inertia), update (authorize, branch on scope → updateSingle/updateGroup, redirect to cards.show)
- [ ] All operations wrapped in DB::transaction()
- [ ] Gate check: `php artisan test --filter=CardExpenseUpdateTest` — 5 tests pass

**Tests:** Feature (PHPUnit)
**Gate:** `php artisan test --filter=CardExpenseUpdateTest`

**Commit:** `feat(ccxp): installment update with single/group scope — updateSingle, updateGroup`

---

### T9: Installment Deletion with Scope — CardExpenseDeletionTest (TDD) [P]

**What:** Write CardExpenseDeletionTest (RED), then implement CardExpenseService::deleteSingle + deleteGroup and CardExpenseController@destroy.

**Where:**
- `tests/Feature/CardExpenses/CardExpenseDeletionTest.php` (new — TDD: written first)
- `app/Services/CardExpenseService.php` (modify — add deleteSingle, deleteGroup methods)
- `app/Http/Controllers/CardExpenseController.php` (modify — add destroy method)

**Depends on:** T4 (installments exist, createInstallment works, controller + routes exist)
**Reuses:** BillService::recalculateBillTotal, CreditCardService::recalculateAvailableLimit, DB::transaction, SoftDeletes
**Requirement:** CCXP-05 (AC4-AC8)

**TDD steps:**
1. Write `CardExpenseDeletionTest.php` with all 5 test methods
2. Run `php artisan test --filter=CardExpenseDeletionTest` — confirm RED (5 failures: delete methods not implemented)
3. Implement deleteSingle + deleteGroup in CardExpenseService
4. Add destroy method to CardExpenseController (with scope param from request)
5. Run `php artisan test --filter=CardExpenseDeletionTest` — confirm GREEN (5 passes)

**Done when:**
- [ ] CardExpenseDeletionTest: `test_delete_single_scope_soft_deletes_one` — create 3x installment, DELETE installment 2 with scope=single → only installment 2 soft-deleted, 1 and 3 remain, assertSoftDeleted on row 2
- [ ] CardExpenseDeletionTest: `test_delete_group_scope_soft_deletes_future` — create 3x installment, DELETE installment 2 with scope=group → installments 2 and 3 soft-deleted, 1 remains
- [ ] CardExpenseDeletionTest: `test_delete_all_installments_no_orphan_group` — create 3x, delete all 3 (group scope from installment 1) → all soft-deleted, no orphaned group_id in DB
- [ ] CardExpenseDeletionTest: `test_cannot_delete_installment_on_paid_bill` — create expense on paid bill, DELETE → validation error "Não é possível excluir parcelas de faturas já pagas"
- [ ] CardExpenseDeletionTest: `test_delete_restores_available_limit` — card limit 5000, expense 1000, delete it → available_limit = 5000 (restored)
- [ ] `deleteSingle(transaction)`: soft-delete one row, recalculateBillTotal for its bill, recalculateAvailableLimit. Reject if bill is paid.
- [ ] `deleteGroup(installment)`: soft-delete all rows with same group_id AND installment_number >= current, recalculateBillTotal for affected bills, recalculateAvailableLimit. Reject if any affected bill is paid.
- [ ] CardExpenseController@destroy: authorize delete, branch on scope (from request param or query string) → deleteSingle/deleteGroup, redirect to cards.show
- [ ] All operations wrapped in DB::transaction()
- [ ] Gate check: `php artisan test --filter=CardExpenseDeletionTest` — 5 tests pass

**Tests:** Feature (PHPUnit)
**Gate:** `php artisan test --filter=CardExpenseDeletionTest`

**Commit:** `feat(ccxp): installment deletion with single/group scope — deleteSingle, deleteGroup`

---

### T10: Authorization Tests — CardExpenseAuthorizationTest (TDD) [P]

**What:** Write CardExpenseAuthorizationTest (RED), then verify and fix any policy enforcement gaps across all card expense + bill operations.

**Where:**
- `tests/Feature/CardExpenses/CardExpenseAuthorizationTest.php` (new — TDD: written first)
- `app/Policies/CardExpensePolicy.php` (modify — if gaps found)
- `app/Policies/CreditCardBillPolicy.php` (modify — if gaps found)
- `app/Http/Controllers/CardExpenseController.php` (modify — if cross-workspace guards missing)
- `app/Http/Controllers/CreditCardBillController.php` (modify — if cross-workspace guards missing)

**Depends on:** T3 (create endpoint), T5 (pay endpoint), T8 (update endpoint), T9 (delete endpoint)
**Reuses:** TransactionAuthorizationTest pattern (viewer 403, editor OK, cross-workspace 404), CardExpenseTestCase base
**Requirement:** CCXP-01 (AC6, AC7)

**TDD steps:**
1. Write `CardExpenseAuthorizationTest.php` with all 5 test methods
2. Run `php artisan test --filter=CardExpenseAuthorizationTest` — confirm RED or GREEN (some may already pass if policies were correctly wired in T3/T5/T8/T9)
3. Fix any failing tests (missing policy methods, missing abort_if guards)
4. Run `php artisan test --filter=CardExpenseAuthorizationTest` — confirm GREEN (5 passes)

**Done when:**
- [ ] CardExpenseAuthorizationTest: `test_viewer_cannot_create_card_expense` — POST card-expenses.store as viewer → 403
- [ ] CardExpenseAuthorizationTest: `test_viewer_cannot_edit_card_expense` — PUT card-expenses.update as viewer → 403
- [ ] CardExpenseAuthorizationTest: `test_editor_cannot_delete_card_expense` — DELETE card-expenses.destroy as editor → 403 (delete is admin only)
- [ ] CardExpenseAuthorizationTest: `test_cannot_access_card_expense_from_different_card` — transaction belongs to card A, accessed via card B URL → 404
- [ ] CardExpenseAuthorizationTest: `test_cannot_access_bill_from_other_workspace` — GET bills.show with bill from workspace B via workspace A URL → 404
- [ ] All policies correctly enforce: viewAny (member), create/update (admin/editor), delete (admin only)
- [ ] All controllers have cross-workspace guards (abort_if 404)
- [ ] Gate check: `php artisan test --filter=CardExpenseAuthorizationTest` — 5 tests pass

**Tests:** Feature (PHPUnit)
**Gate:** `php artisan test --filter=CardExpenseAuthorizationTest`

**Commit:** `feat(ccxp): authorization tests — verify policy enforcement across all card expense + bill operations`

---

### T11: Cards/Show.tsx + Shared Components [P]

**What:** Create the card detail page showing current open bill + historical bills, plus reusable ExpenseRow, InstallmentBadge components, and modify Cards/Index.tsx to add "Ver fatura" link.

**Where:**
- `resources/js/Pages/Cards/Show.tsx` (new)
- `resources/js/Components/CardExpenses/ExpenseRow.tsx` (new)
- `resources/js/Components/CardExpenses/InstallmentBadge.tsx` (new)
- `resources/js/Pages/Cards/Index.tsx` (modify — add "Ver fatura" link per card)

**Depends on:** T3 (cards.show route + CreditCardBillResource + TransactionResource with installment fields), T7 (CreditCardController@show fully implemented)
**Reuses:** AuthenticatedLayout, Card/CardContent/CardHeader/CardTitle, Badge, Button, formatCurrency, DynamicIcon, existing Cards/Index.tsx pattern, Transactions/Index.tsx card layout pattern
**Requirement:** CCXP-03 (AC1-AC6, frontend)

**Done when:**
- [ ] Cards/Show.tsx renders inside AuthenticatedLayout
- [ ] Header shows card name, credit_limit, available_limit (BRL format), closing_day, due_day badges
- [ ] Open bill section: title "Fatura Atual — {month_label}", total (BRL, bold), status "Aberta" badge, closing_date, due_date
- [ ] Expense list: each expense rendered via ExpenseRow component (description, value, date, category color dot + name, tag chips, installment badge if applicable)
- [ ] InstallmentBadge: shows "N/M" format (e.g., "3/12") as a Badge
- [ ] Actions per expense: "Editar" (Link to card-expenses.edit), "Excluir" (Button destructive)
- [ ] Empty state when no expenses: "Nenhuma compra neste ciclo" with "Nova Compra" CTA
- [ ] "Nova Compra" button → Link to route('card-expenses.create', { workspace, card })
- [ ] Previous bills section: collapsible list with month_label, total, status badge, click → navigate to bills.show
- [ ] If open bill status is closed: "Pagar Fatura" button visible
- [ ] Cards/Index.tsx: each card card has "Ver fatura" link → route('cards.show', { workspace, card })
- [ ] TypeScript strict: all interfaces defined (CardDetail, Bill, BillExpense), no `any`
- [ ] pt-BR labels
- [ ] Gate check: `npm run build` succeeds (TS compilation + no import errors)

**Tests:** None (tested by Cypress in T14)
**Gate:** `npm run build`

**Commit:** `feat(ccxp): Cards/Show page — bill view, ExpenseRow, InstallmentBadge components`

---

### T12: CardExpenses/Create.tsx [P]

**What:** Create the card expense creation form page supporting both single purchases and installment purchases.

**Where:**
- `resources/js/Pages/CardExpenses/Create.tsx` (new)

**Depends on:** T3 (card-expenses.create route + StoreCardExpenseRequest validation rules)
**Reuses:** Transactions/Create.tsx pattern (AuthenticatedLayout, Card form wrapper, useForm, Label+Input+Select, button row), formatCurrency for per-installment preview, Select/Badge components
**Requirement:** CCXP-01 (AC1, frontend), CCXP-02 (AC1, frontend)

**Done when:**
- [ ] Page renders inside AuthenticatedLayout with header "Nova Compra no Cartão"
- [ ] Form fields: description (Input text, required), value (Input number step=0.01), date (Input date, default today), installments (Input number, default 1, min 1, max 48), category (Select filtered to Expense/Both), tags (multi-select)
- [ ] When installments=1: value label is "Valor", subtitle "Compra Única"
- [ ] When installments>1: value label changes to "Valor Total", date label changes to "Data da primeira parcela", per-installment preview shows "R$ {value / installments}" as read-only text
- [ ] Validation errors displayed below each field (red text from form.errors)
- [ ] Submit via `useForm().post(route('card-expenses.store', { workspace, card }))` → redirect to cards.show
- [ ] "Cancelar" button → Link to cards.show
- [ ] TypeScript strict, no `any`
- [ ] pt-BR labels and placeholders
- [ ] Gate check: `npm run build` succeeds

**Tests:** None (tested by Cypress in T14)
**Gate:** `npm run build`

**Commit:** `feat(ccxp): CardExpenses/Create page — single + installment purchase form`

---

### T13: CardExpenses/Edit.tsx + Bills/Show.tsx + BillPaymentModal [P]

**What:** Create the card expense edit form (with scope selector for installments), bill detail page, and bill payment modal component.

**Where:**
- `resources/js/Pages/CardExpenses/Edit.tsx` (new)
- `resources/js/Pages/Bills/Show.tsx` (new)
- `resources/js/Components/CardExpenses/BillPaymentModal.tsx` (new)

**Depends on:** T3 (card-expenses.edit route), T5 (bills.show/pay/unpay routes), T8 (UpdateCardExpenseRequest with scope field)
**Reuses:** Transactions/Edit.tsx pattern, Cards/Show.tsx bill display pattern (from T11), shadcn Dialog/Modal component, Select for account
**Requirement:** CCXP-04 (AC1, frontend), CCXP-05 (AC1, frontend), CCXP-03 (AC3-AC4, frontend)

**Done when:**
- [ ] CardExpenses/Edit.tsx: renders inside AuthenticatedLayout with header "Editar Compra"
- [ ] Edit form: same fields as Create, pre-populated from transaction prop
- [ ] Edit form: when installments_total > 1, shows RadioGroup at top: "Apenas esta parcela" (value=single), "Esta e futuras" (value=group) — `scope` field sent with form data
- [ ] Edit form: if transaction belongs to paid bill, shows error banner "Esta despesa pertence a uma fatura já paga e não pode ser editada"
- [ ] Edit form: submit via `useForm().put(route('card-expenses.update', { workspace, card, transaction }))` → redirect to cards.show
- [ ] Bills/Show.tsx: renders bill detail — header (card name, period_label, status badge, total), expense list (via ExpenseRow), if closed → "Pagar Fatura" button, if paid → "Desfazer Pagamento" button + payment info
- [ ] BillPaymentModal: shows bill total, Account Select (active accounts only), warning if insufficient balance, confirm button → POST bills.pay
- [ ] "Desfazer Pagamento": POST bills.unpay → on success, page reloads
- [ ] TypeScript strict, no `any`
- [ ] pt-BR labels
- [ ] Gate check: `npm run build` succeeds

**Tests:** None (tested by Cypress in T14)
**Gate:** `npm run build`

**Commit:** `feat(ccxp): CardExpenses/Edit + Bills/Show + BillPaymentModal`

---

### T14: Cypress E2E — Card Expenses + Bill Payment

**What:** Write and run the full Cypress E2E spec for card expense CRUD, installment creation, edit/delete with scope, bill payment, and undo payment.

**Where:**
- `cypress/e2e/card-expenses/crud.cy.js` (new)

**Depends on:** T5, T6, T7, T8, T9, T10 (all backend tests pass), T11, T12, T13 (all frontend pages rendered)
**Reuses:** `cypress/e2e/accounts/crud.cy.js` pattern (cy.loginViaSession, workspace creation in before(), card selectors), `cypress/e2e/transactions/crud.cy.js` pattern
**Requirement:** CCXP-01 through CCXP-06 (E2E coverage)

**Done when:**
- [ ] `before()` hook: creates workspace via cy.loginViaSession, creates credit card via UI, creates account via UI
- [ ] Test: `shows card detail page` — navigate to /w/{uuid}/cards/{cardUuid} → "Fatura" heading visible, card name, available limit
- [ ] Test: `creates a single card expense` — click "Nova Compra" → fill form (installments=1) → submit → redirected to card show, expense appears in open bill
- [ ] Test: `creates an installment purchase` — fill with installments=3 → submit → 3 entries visible with "1/3", "2/3", "3/3" indicators
- [ ] Test: `installments span multiple bills` — purchase 3x with closing_day=1 → installment 1 in current bill, 2/3 in next bill
- [ ] Test: `edits a card expense with single scope` — click "Editar" on installment 2 → select "Apenas esta parcela" → change description → submit → only installment 2 updated
- [ ] Test: `edits card expense with group scope` — click "Editar" on installment 2 → select "Esta e futuras" → change description → submit → installments 2 and 3 updated, 1 unchanged
- [ ] Test: `deletes a card expense` — click "Excluir" on single expense → confirm → expense removed from bill list
- [ ] Test: `pays a closed bill` — close bill (via API or UI) → click "Pagar Fatura" → select account → confirm → bill shows "Paga" status, account balance decreased
- [ ] Test: `undoes bill payment` — on paid bill → click "Desfazer Pagamento" → confirm → bill reverts to "Fechada", account balance restored
- [ ] Test: `available_limit_updates_on_expense` — create expense → check displayed available_limit → decreased by expense value
- [ ] Gate check: `npx cypress run --spec "cypress/e2e/card-expenses/crud.cy.js"` — all 10 tests pass

**Tests:** E2E (Cypress)
**Gate:** `npx cypress run --spec "cypress/e2e/card-expenses/crud.cy.js"`

**Commit:** `test(ccxp): Cypress E2E — card expenses CRUD, installments, bill payment`

---

## Parallel Execution Map

```
Phase 1 (Sequential):
  T1 (structural foundation)

Phase 2 (Sequential — TDD):
  T1 ──→ T2 (AvailableLimitTest → CreditCardService upgrade)

Phase 3 (Sequential — TDD):
  T2 ──→ T3 (CardExpenseCreationTest → full creation stack)

Phase 4 (Sequential — TDD):
  T3 ──→ T4 (CardExpenseInstallmentTest → createInstallment)

Phase 5 (Parallel — 3 sub-agents, TDD):
  T2, T3 complete, then:
    ├── T5  [P]  (BillPaymentTest → payBill + BillController)
    ├── T6  [P]  (BillClosureTest → closeBillsBefore + CloseBillsJob)
    └── T7  [P]  (BillViewTest → CreditCardController@show)

Phase 6 (Parallel — 3 sub-agents, TDD):
  T4 complete, then:
    ├── T8  [P]  (CardExpenseUpdateTest → updateSingle/updateGroup)
    ├── T9  [P]  (CardExpenseDeletionTest → deleteSingle/deleteGroup)
    └── T10 [P]  (CardExpenseAuthorizationTest → verify/fix policies)
  T10 also depends on T5 (pay endpoint exists) — T10 starts after T5 AND T8 AND T9

Phase 7 (Parallel — 3 sub-agents, frontend):
  T3, T5, T7 complete, then:
    ├── T11 [P]  (Cards/Show.tsx + ExpenseRow + InstallmentBadge + Index.tsx mod)
    ├── T12 [P]  (CardExpenses/Create.tsx)
    └── T13 [P]  (CardExpenses/Edit.tsx + Bills/Show.tsx + BillPaymentModal)

Phase 8 (Sequential):
  T5-T13 all complete, then:
    T14 (Cypress E2E — full suite)
```

**Parallelism constraint:** T10 depends on T3, T5, T8, T9 — it can only start after all four complete. In practice, T10 starts at the end of Phase 6 (after T5 from Phase 5 and T8/T9 from Phase 6 finish). If T5 finishes before T8/T9, T10 waits for T8/T9.

---

## Requirement Traceability

| Task | CCXP-01 | CCXP-02 | CCXP-03 | CCXP-04 | CCXP-05 | CCXP-06 |
|------|---------|---------|---------|---------|---------|---------|
| T1 | schema | schema | schema | schema | schema | schema |
| T2 | — | — | — | AC2 (limit restore) | — | — |
| T3 | AC1-AC8 (create full stack) | — | — | — | — | — |
| T4 | — | AC1-AC8 (installments) | — | — | — | — |
| T5 | — | — | — | AC1-AC7 (payment) | — | — |
| T6 | — | — | — | — | — | AC1-AC5 (closure) |
| T7 | — | — | AC1-AC6 (bill view) | — | — | — |
| T8 | — | — | — | — | AC1-AC3, AC8 (update scope) | — |
| T9 | — | — | — | — | AC4-AC8 (delete scope) | — |
| T10 | AC6, AC7 (auth) | — | — | AC6 (pay auth) | AC6 (delete auth) | — |
| T11 | — | — | AC1-AC6 (UI) | — | — | — |
| T12 | AC1 (UI) | AC1 (UI) | — | — | — | — |
| T13 | — | — | AC3-AC4 (UI) | AC1 (UI) | AC1 (UI) | — |
| T14 | AC1 (E2E) | AC1 (E2E) | AC1 (E2E) | AC1 (E2E) | AC1 (E2E) | — |

---

## Task Granularity Check

| Task | Scope | Files | Status |
|------|-------|-------|--------|
| T1 | Migrations + Enum + Model + Factory + Policy skeleton + TestCase base + 5 model modifications | 14 files | ⚠️ Cohesive (all structural foundation for same feature; splitting creates artificial dependencies) |
| T2 | 1 test file + 1 service modification + 1 controller modification | 3 files | ✅ Granular |
| T3 | 1 test file + 2 new services + 1 controller + 1 FormRequest + 1 Policy + 1 Resource + 2 Resource mods + routes | 10 files | ⚠️ Cohesive (TDD: test requires full HTTP stack to pass; all layers for "create card expense") |
| T4 | 1 test file + 1 service method + 1 FormRequest mod + 1 controller mod | 4 files | ✅ Granular |
| T5 | 1 test file + 1 service mod + 1 controller + 1 FormRequest + routes | 5 files | ✅ Granular |
| T6 | 1 test file + 1 service mod + 1 Job + 1 schedule file | 4 files | ✅ Granular |
| T7 | 1 test file + 1 controller mod | 2 files | ✅ Granular |
| T8 | 1 test file + 1 service mod + 1 FormRequest + 1 controller mod | 4 files | ✅ Granular |
| T9 | 1 test file + 1 service mod + 1 controller mod | 3 files | ✅ Granular |
| T10 | 1 test file + potential policy/controller fixes | 2-5 files | ✅ Granular |
| T11 | 1 page + 2 components + 1 page mod | 4 files | ✅ Granular |
| T12 | 1 page | 1 file | ✅ Granular |
| T13 | 2 pages + 1 component | 3 files | ⚠️ Acceptable (Edit + Bills/Show + Modal share bill display patterns) |
| T14 | 1 Cypress spec | 1 file | ✅ Granular |

**⚠️ Notes:**
- T1: 14 files but all structural (migrations, models, enum, factory, policy skeleton, test base). Splitting would create tasks that can't be independently verified (model without migration, factory without model). One cohesive foundation task.
- T3: 10 files but TDD requires the full HTTP stack to exist for the feature test to pass (route → controller → FormRequest → service → model). Splitting would mean writing a test that can't run. One cohesive "create card expense" task.

---

## Diagram-Definition Cross-Check

| Task | Depends On (body) | Diagram Shows | Status |
|------|-------------------|---------------|--------|
| T1 | None | No incoming arrows (Phase 1 root) | ✅ Match |
| T2 | T1 | T1 → T2 | ✅ Match |
| T3 | T2 | T2 → T3 | ✅ Match |
| T4 | T3 | T3 → T4 | ✅ Match |
| T5 | T2, T3 | T2,T3 → T5 [P] | ✅ Match |
| T6 | T3 | T2,T3 → T6 [P] (T2 is not a hard dep, T3 is) | ✅ Match (T2 listed in diagram for context, T3 is hard dep) |
| T7 | T3 | T2,T3 → T7 [P] (same as T6) | ✅ Match |
| T8 | T4 | T4 → T8 [P] | ✅ Match |
| T9 | T4 | T4 → T9 [P] | ✅ Match |
| T10 | T3, T5, T8, T9 | T4 → T10 [P] + T5 dependency noted | ✅ Match (T10 in Phase 6 with T8/T9, but also depends on T5 from Phase 5) |
| T11 | T3, T5, T7 | T3,T5,T7 → T11 [P] | ✅ Match |
| T12 | T3 | T3,T5,T7 → T12 [P] (T3 is hard dep, T5/T7 not strictly needed) | ✅ Match |
| T13 | T3, T5, T8 | T3,T5,T7 → T13 [P] (T3+T5 hard deps, T8 for scope field) | ✅ Match |
| T14 | T5-T13 | T5-T13 → T14 | ✅ Match |

**T10 special case:** T10 is in Phase 6 (parallel with T8, T9) but also depends on T5 (Phase 5). If T5 completes before Phase 6 starts (expected since Phase 5 runs before Phase 6), T10 can start in parallel with T8/T9. If T5 is delayed, T10 waits. The diagram shows T10 in Phase 6 with a note about T5 dependency.

No mismatches. T5-T7 are parallel (depend on T2+T3, not each other). T8-T10 are parallel (depend on T4, not each other — T10 also needs T5 which is in an earlier phase). T11-T13 are parallel (depend on T3/T5/T7, not each other).

---

## Test Co-location Validation

Project test strategy (from AGENTS.md + existing patterns):
- **Backend code** → PHPUnit Feature Tests (HTTP-level, tests full controller→service→DB stack)
- **Frontend pages** → Cypress E2E (browser-level, tests full user journey)
- **TDD-first**: Tests written BEFORE implementation in the same task

| Task | Code Layer | Required Test | Task Says | Status |
|------|-----------|---------------|-----------|--------|
| T1 | Models, Migrations, Enum, Factory | Feature (PHPUnit) | None (structural — merge-forward to T2-T10) | ⚠️ Deferred (structural: verified by migrate:fresh) |
| T2 | Service method upgrade + controller mod | Feature (PHPUnit) | Feature (TDD co-located: AvailableLimitTest) | ✅ OK |
| T3 | Service + Controller + FormRequest + Policy + Resource + Routes | Feature (PHPUnit) | Feature (TDD co-located: CardExpenseCreationTest) | ✅ OK |
| T4 | Service method + FormRequest mod + Controller mod | Feature (PHPUnit) | Feature (TDD co-located: CardExpenseInstallmentTest) | ✅ OK |
| T5 | Service methods + Controller + FormRequest + Routes | Feature (PHPUnit) | Feature (TDD co-located: BillPaymentTest) | ✅ OK |
| T6 | Service methods + Job + Schedule | Feature (PHPUnit) | Feature (TDD co-located: BillClosureTest) | ✅ OK |
| T7 | Controller method | Feature (PHPUnit) | Feature (TDD co-located: BillViewTest) | ✅ OK |
| T8 | Service methods + FormRequest + Controller mod | Feature (PHPUnit) | Feature (TDD co-located: CardExpenseUpdateTest) | ✅ OK |
| T9 | Service methods + Controller mod | Feature (PHPUnit) | Feature (TDD co-located: CardExpenseDeletionTest) | ✅ OK |
| T10 | Policy + Controller guards | Feature (PHPUnit) | Feature (TDD co-located: CardExpenseAuthorizationTest) | ✅ OK |
| T11 | Frontend page + components | E2E (Cypress) | None (merge-forward to T14) | ⚠️ Deferred to T14 |
| T12 | Frontend page | E2E (Cypress) | None (merge-forward to T14) | ⚠️ Deferred to T14 |
| T13 | Frontend pages + component | E2E (Cypress) | None (merge-forward to T14) | ⚠️ Deferred to T14 |
| T14 | E2E tests | E2E | E2E (co-located) | ✅ OK |

**Merge-forward justification:**
- T1 (structural): migrations and models are verified by `migrate:fresh` + `optimize:clear`. The behavioral tests in T2-T10 exercise the models/migrations implicitly. No code leaves unverified.
- T11-T13 (frontend): pages can't be E2E tested until Cypress spec (T14) is written AND the pages exist. T14 writes the spec and runs it against T11-T13's pages. TDD for frontend = write spec first (RED), then implement pages (GREEN). In practice, T11-T13 implement pages, T14 verifies. The spec is written in T14 as the gate.

**TDD compliance:**
- T2-T10: Each task writes the test file FIRST (RED), then implements code to make it pass (GREEN). This is strict TDD-first.
- T1: Structural — no TDD (migrations can't be test-driven in the traditional sense).
- T11-T13: Frontend implementation tasks — verified by T14 (Cypress E2E). The Cypress spec in T14 serves as the test gate.
- T14: Writes and runs the full E2E spec. All 10 tests must pass against the implemented pages.

---

## Backend Test Summary

| File | Tests | Task | Requirement |
|------|-------|------|-------------|
| `AvailableLimitTest.php` | 6 | T2 | D-29, CCXP-04 |
| `CardExpenseCreationTest.php` | 10 | T3 | CCXP-01 |
| `CardExpenseInstallmentTest.php` | 10 | T4 | CCXP-02 |
| `BillPaymentTest.php` | 10 | T5 | CCXP-04 |
| `BillClosureTest.php` | 6 | T6 | CCXP-06 |
| `BillViewTest.php` | 7 | T7 | CCXP-03 |
| `CardExpenseUpdateTest.php` | 5 | T8 | CCXP-05 |
| `CardExpenseDeletionTest.php` | 5 | T9 | CCXP-05 |
| `CardExpenseAuthorizationTest.php` | 5 | T10 | CCXP-01 |
| **Total** | **64** | | **All 7 requirements** |

**Full backend gate:** `php artisan test --filter="CardExpenses|Bills|AvailableLimit"`

---

## E2E Test Summary

| File | Tests | Task | Requirement |
|------|-------|------|-------------|
| `cypress/e2e/card-expenses/crud.cy.js` | 10 | T14 | CCXP-01 through CCXP-06 |

**Full E2E gate:** `npx cypress run --spec "cypress/e2e/card-expenses/**"`

---

## Commit Message Format

All commits follow: `feat(ccxp): [description]` or `test(ccxp): [description]`
Scope: `ccxp` for all CCXP-01 tasks.