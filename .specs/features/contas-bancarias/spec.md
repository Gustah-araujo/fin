# Contas Bancárias — Specification

**Story ID:** ACCT-01
**Phase:** P1 — MVP Core
**Parent Spec:** `.specs/features/workspace-financeiro/spec.md`

## Problem Statement

Workspace members need to register and manage bank accounts as the foundation of all financial tracking. Without accounts, transactions (debit expenses, credit card bills, income) have nowhere to be recorded. Each account must track a balance that reflects all activity, not just a static number.

## Goals

- [ ] Users can create, view, edit, and archive bank accounts within a workspace
- [ ] Each account has a name, type (checking/savings/investment), and initial balance
- [ ] Account list shows current balance reflecting all financial activity
- [ ] Accounts with transactions cannot be hard-deleted (archive only)

## Key Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Balance strategy | **Denormalized** `current_balance` column | Updated by services; fast queries, simple frontend |
| Soft deletes | **From day 1** via Laravel `SoftDeletes` | Schema is final; avoid migration churn |
| Audit | **`created_by`** FK to users | Supports historical "ex-member" display |

## Out of Scope

| Feature               | Reason                                    |
| --------------------- | ----------------------------------------- |
| Transactions           | Separate features (DEBT-01, CCXP-01, INCM-01) |
| Balance auto-reconciliation | v1: balance updated by transaction services; no manual reconciliation |
| Multi-currency          | v1 is BRL-only per PROJECT.md            |
| Account-to-account transfers | Not in v1 scope — manual debit/income entries suffice |

---

## User Stories

### P1: CRUD de Contas Bancárias ⭐ MVP

**User Story**: As a workspace member, I want to manage bank accounts with name, type, and balance so that I can track where my money is.

**Why P1**: Contas are the foundation — every transaction references an account. Without them, no financial entity works.

**Acceptance Criteria**:

1. WHEN a user with create permission submits account details (name, type, initial_balance) THEN system SHALL create the account linked to the current workspace and redirect to the account list.

2. WHEN a user views the account list THEN system SHALL display all non-archived accounts with their name, type label, and current balance. The balance SHALL be displayed in BRL format (R$ X.XXX,XX).

3. WHEN a user edits an account's name or type THEN system SHALL update the record. The initial_balance SHALL only be editable while the account has zero transactions (future-proof rule; always editable now since transactions don't exist yet).

4. WHEN a user attempts to delete an account with no transactions THEN system SHALL hard-delete it. WHEN the account has transactions THEN system SHALL soft-delete (archive) it and prevent hard deletion.

5. WHEN an account is archived THEN it SHALL not appear in the account list but SHALL remain in historical transaction data.

**Independent Test**: Create 2 accounts with different names/types/balances → verify they appear on the list with correct BRL formatting → edit one name → verify update → delete one → verify removal. Archive with transactions → verify hidden from list.

---

### P2: Saldo Calculado (Future-Ready Foundation)

**User Story**: As a workspace member, I want the account balance to reflect all financial activity so that I always know my real balance.

**Why P2**: The data model must support balance calculation from day 1, even though transactions come later. This prevents a breaking schema change.

**Acceptance Criteria**:

1. WHEN an account is created THEN system SHALL store `initial_balance` and set `current_balance` equal to it. Current balance = initial_balance + sum(income) - sum(paid_debit_expenses) - sum(paid_credit_bills).

2. WHEN future transaction services (income, debit expenses, credit bills) create/update/delete entries THEN they SHALL call `AccountService::recalculateBalance()` to keep `current_balance` in sync.

3. WHEN a user views account details THEN system SHALL show both initial_balance and the breakdown of how current_balance was derived (once transactions exist; show placeholder until then).

**Independent Test**: Create account with R$1000 initial → verify current_balance = R$1000. (Future: add income → balance increases; pay expense → balance decreases.)

---

## Edge Cases

- WHEN an account is created with zero initial_balance THEN system SHALL accept it (valid use case: tracking a new empty account).
- WHEN initial_balance is negative THEN system SHALL accept it (accounts that start overdrawn, e.g., R$ -500 for an existing overdraft).
- WHEN a user without create permission (viewer role) tries to access the create form THEN system SHALL return 403.
- WHEN a user without edit permission (viewer role) tries to update an account THEN system SHALL return 403.
- WHEN a user attempts to access an account from a different workspace THEN system SHALL return 404 (scoped route model binding).
- WHEN the account list is empty (no accounts created yet) THEN system SHALL show an empty state with a CTA to create the first account.

---

## Requirement Traceability

| ID        | Story                            | Phase  | Status  |
| --------- | -------------------------------- | ------ | ------- |
| ACCT-01   | P1: CRUD de Contas Bancárias     | Design | Pending |
| ACCT-01.1 | P2: Saldo Calculado Foundation   | Design | Pending |

**Coverage:** 2 requirements, 0 mapped, 2 unmapped

---

## Success Criteria

- [ ] User can create an account in < 30 seconds from the workspace dashboard
- [ ] Account list renders with correct BRL formatting for all balance values
- [ ] Balance field is structured to support transaction-driven updates without a migration rewrite
- [ ] Archive mechanism is in place and tested, even if hard-delete is the default until transactions exist
