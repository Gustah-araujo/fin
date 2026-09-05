# Recurring Expenses — Specification

## Problem Statement

Today recurrences only generate income transactions. Users need to model recurring expenses like monthly subscriptions (Netflix, Spotify) or personal installment debts (borrowed money from family, paid back in monthly transfers). These obligations must appear in the planning view alongside income projections.

## Goals

- Allow expense creation via recurrence toggle (mirror income flow)
- Recurring expenses appear in Planning screen projections
- Recurrence management UI shows both types with type filter

## Out of Scope

| Feature | Reason |
|---------|--------|
| Changing recurrence type after creation | D-62: type immutable — delete + recreate |
| Recurring credit card expenses | Already handled by card installments |
| Recurring transfers between accounts | Separate feature |

---

## User Stories

### REEX-01: Create Recurring Expense

**User Story**: As a workspace member, I want to create a recurring expense from the expense form so that monthly obligations are auto-generated.

**Why**: Subscriptions and personal debts are common recurring expenses that currently require manual entry each month.

**Acceptance Criteria**:

1. WHEN a user creates an expense with "É recorrente?" toggle ON AND `start_date <= today` THEN system SHALL create a Recurrence (type=expense) + first Transaction in a single DB transaction and advance `next_date`.
2. WHEN a user creates an expense with toggle ON AND `start_date > today` THEN system SHALL create only the Recurrence (next_date = start_date); Transaction generated later by job.
3. WHEN a user creates an expense with toggle OFF THEN system SHALL create a single avulsed expense (current behavior, unchanged).
4. WHEN a recurring expense is created THEN generated transactions SHALL have `type = Expense`, `paid_at = null`, `recurrence_id` pointing to parent.
5. WHEN the daily `ProcessRecurrencesJob` runs THEN it SHALL generate expense transactions for due expense recurrences (same logic as income).

**Independent Test**: Create recurring expense "Netflix" R$39.90/month starting today → see 1 transaction created + recurrence listed. Create recurring "Dad loan" R$250/month starting next month → see only recurrence, no transaction yet. Run job → future transactions generated.

---

### REEX-02: Recurrence Management Shows Both Types

**User Story**: As a workspace member, I want to see all my recurrences (income and expense) in a single list with type filter so that I can manage all recurring rules.

**Why**: Users need a unified view of all recurring financial rules.

**Acceptance Criteria**:

1. WHEN viewing `/w/{workspace}/recurrences` THEN system SHALL show all recurrences (income + expense) in a single DataTable.
2. Each row SHALL display a type badge ("Receita" emerald / "Despesa" red) and value color SHALL match type.
3. WHEN filtering by type THEN system SHALL show only selected type (income/expense/all).
4. "Ver instâncias" link SHALL route to `transactions.index?recurrence=X` for expenses, `incomes.index?recurrence=X` for income.
5. Page title SHALL be "Recorrências" (generic, not "Receitas recorrentes").

**Independent Test**: Create 2 income recurrences + 1 expense recurrence → list shows 3 with correct badges → filter "Despesa" shows 1 → click "Ver instâncias" on expense → routes to transactions.index.

---

### REEX-03: Recurring Expenses in Planning

**User Story**: As a workspace member, I want recurring expenses to appear in the planning projection so that I see my true financial outlook.

**Why**: Planning without recurring expenses shows incomplete picture — subscriptions and debts are real obligations.

**Acceptance Criteria**:

1. WHEN viewing the planning screen THEN recurring expense projections SHALL appear as expense items in the monthly table.
2. Projected recurring expenses SHALL be included in monthly expense totals and balance calculation (balance = incomes - expenses).
3. Month detail modal SHALL show recurring expense projections alongside real expense transactions.

**Independent Test**: Create recurring expense R$50/month → open planning → see R$50 in monthly expenses → balance reflects deduction.

---

### REEX-04: Category Guard for Expense Recurrences

**User Story**: As a workspace member, I want the system to enforce correct category types for expense recurrences so that I don't assign income-only categories to expenses.

**Why**: Data integrity — expense transactions require expense-compatible categories.

**Acceptance Criteria**:

1. WHEN creating/editing an expense recurrence THEN system SHALL reject categories with `type = Income` (only Expense/Both allowed).
2. WHEN editing a recurrence THEN category filter SHALL branch on recurrence type: Expense/Both for expense recurrences, Income/Both for income recurrences.
3. Error message SHALL be: "Esta categoria não aceita despesas." (expense context).

**Independent Test**: Create expense recurrence → try to select "Salário" (income-only category) → validation error. Edit expense recurrence → category dropdown shows only Expense/Both categories.

---

## Architectural Decisions

| ID | Decision | Context |
|----|----------|---------|
| D-62 | Recurrence `type` immutable after creation | Avoids cascading type changes to past/future instances; delete + recreate if type change needed |
| D-63 | Single recurrence list with type filter + badge | Unified management; avoids duplicating UI structure |
| D-64 | Recurrence toggle on expense form mirrors income flow | Consistent UX; user creates avulsed or recurring in same form |
| D-65 | `RecurrenceService` reads type from model/data, no hardcode | Single source of truth; type determined at creation, propagated to generated instances |

---

## Requirement Traceability

| ID | Story | Phase | Status |
|----|-------|-------|--------|
| REEX-01 | Create Recurring Expense | - | Pending |
| REEX-02 | Recurrence Management Shows Both Types | - | Pending |
| REEX-03 | Recurring Expenses in Planning | - | Pending |
| REEX-04 | Category Guard for Expense Recurrences | - | Pending |

**Coverage:** 4 total, 0 mapped, 4 unmapped

---

## Success Criteria

- [ ] User can create recurring expense from expense form with toggle
- [ ] Daily job generates expense transactions for due recurrences
- [ ] Recurrence list shows type badges and filters by type
- [ ] Planning screen includes recurring expense projections
- [ ] Category guard prevents income-only categories on expense recurrences
- [ ] All existing income recurrence tests still pass (no regression)
