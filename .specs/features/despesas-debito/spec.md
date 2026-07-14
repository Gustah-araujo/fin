# Despesas em Débito — Specification

**Story ID:** DEBT-01
**Phase:** P1 — MVP Core
**Parent Spec:** `.specs/features/workspace-financeiro/spec.md`

## Problem Statement

Workspace members need to record debit expenses linked to a bank account so that their account balance reflects real spending. Without expense tracking, accounts are static containers — balances never change, and there's no record of where money goes. Debit expenses are the most frequent transaction type and form the foundation for budget analysis, spending insights, and cash flow visibility.

## Goals

- [ ] CRUD de despesas em débito com descrição, valor, data, conta, categoria e tags
- [ ] Toda despesa nasce "não paga"; pagamento é ação explícita do usuário
- [ ] Pagamento deduz do saldo da conta vinculada via AccountService::recalculateBalance()
- [ ] Edição/exclusão de despesa paga reverte e recalcula saldo corretamente
- [ ] UI distingue visualmente despesas pagas de não pagas
- [ ] Filtros por categoria, conta, período e busca textual
- [ ] Paginação para performance com muitos registros

## Key Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Model | `Transaction` — tabela única `transactions` | Futuro: receitas (INCM-01) e cartão (CCXP-01) compartilham 80% do schema; queries unificadas (dashboard, filtros) sem UNION |
| Type discriminator | Enum `TransactionType` (Income, Expense) no campo `type` | Já existe em `App\Enums\`; DEBT-01 usa apenas `Expense` |
| Payment status | Campo `paid_at` (datetime nullable) | `null` = unpaid; valor = timestamp do pagamento; registra "quando" não só "se" |
| Balance recalculation | TransactionService chama `AccountService::recalculateBalance()` após create/update/delete de transações pagas | Respeita arquitetura em camadas; service não acessa model diretamente |
| recalculateBalance impl | Soma `initial_balance + sum(paid_income) - sum(paid_expenses)` via queries no account | Substitui o stub atual que só reseta para initial_balance |
| Tags | Many-to-many polimórfico via `taggables` já existente | Migration criada em CAT-01; DEBT-01 é o primeiro consumer com `syncTags()` |
| Soft deletes | `SoftDeletes` from day 1 | Consistência com Accounts, Categories, Tags |
| Account FK validação | Pertence ao mesmo workspace da transação | Evita cross-workspace data leaks |
| Pagamento toggle | Ação explícita (botão "Pagar" / "Desmarcar pagamento") | Sem checkbox inline; ação consciente que dispara recalculo de saldo |

## Out of Scope

| Feature | Reason |
|---------|--------|
| Receitas (INCM-01) | Feature separada; schema suporta mas lógica virá depois |
| Despesas de cartão de crédito (CCXP-01) | Feature separada; schema extensível com credit_card_id futuro |
| Despesas recorrentes | INCM-01 cobre recorrência; escopo complexo para primeiro transaction type |
| Transferências entre contas | Fora do escopo v1 (PROJECT.md) |
| Conciliação bancária manual | v1: saldo é calculado automaticamente; sem reconciliação |
| Importação de extratos | IMPT-01 (P2); escopo separado |
| Anexos/comprovantes | Complexidade de storage desnecessária para v1 |
| Ordenação customizável de lista | Default: data descending; ordenação adicionada se houver demanda |

---

## User Stories

### P1: CRUD de Despesas em Débito ⭐ MVP

**User Story**: As a workspace member, I want to record, view, edit and delete debit expenses with description, value, date, account, category and tags so that I can track my spending.

**Why P1**: Debit expenses are the most basic and frequent transaction type. Every subsequent feature (dashboard, insights, planning) depends on expense data existing. Without this, accounts are just static containers.

**Acceptance Criteria**:

1. WHEN a user with create permission submits an expense (description, value, date, account_id, category_id, tags[]) THEN system SHALL create the transaction with type=Expense, paid_at=null, linked to the current workspace and redirect to the expense list.

2. WHEN a user views the expense list THEN system SHALL display all non-archived transactions ordered by date descending, showing: description, value in BRL format (R$ X.XXX,XX), date, account name, category name with color indicator, and tag chips.

3. WHEN a user edits an unpaid expense's description, value, date, account, category or tags THEN system SHALL update the record. Tags SHALL sync via the taggable polymorphic relationship.

4. WHEN a user deletes an unpaid expense THEN system SHALL soft-delete it. The expense SHALL not appear in default lists.

5. WHEN the expense list is empty THEN system SHALL show an empty state with CTA "Registrar primeira despesa".

6. WHEN a user without create permission (viewer role) attempts to create/edit/delete THEN system SHALL return 403.

7. WHEN a user attempts to access an expense from a different workspace THEN system SHALL return 404 (scoped route model binding).

**Independent Test**: Create 3 expenses with different accounts/categories/tags → verify they appear on list with correct BRL formatting → edit one description → verify update → delete one → verify hidden from list (soft deleted).

---

### P1: Pagamento de Despesa ⭐ MVP

**User Story**: As a workspace member, I want to mark expenses as paid so that the account balance reflects money that actually left my account.

**Why P1**: The core differentiator of debit expenses over a simple todo list. Without payment confirmation, there's no balance impact and no real financial tracking.

**Acceptance Criteria**:

1. WHEN a user marks an unpaid expense as paid THEN system SHALL set paid_at = now() AND call AccountService::recalculateBalance() on the linked account to deduct the expense value from current_balance.

2. WHEN a user marks a paid expense back to unpaid THEN system SHALL set paid_at = null AND call recalculateBalance() to restore the value.

3. WHEN a user edits a paid expense's value THEN system SHALL recalculateBalance() to reflect the new value (account balance adjusts by the difference).

4. WHEN a user edits a paid expense's account_id (moves it to a different account) THEN system SHALL call recalculateBalance() on BOTH the old and new accounts.

5. WHEN a user deletes a paid expense THEN system SHALL call recalculateBalance() on the linked account to restore the value BEFORE soft-deleting the record.

6. WHEN viewing the expense list THEN system SHALL visually distinguish paid expenses from unpaid (distinct styling: muted text, check icon, or gray background).

7. WHEN any payment operation (pay/unpay/edit paid/delete paid) fails during recalculateBalance THEN system SHALL rollback the entire operation within a database transaction.

**Independent Test**: Create expense R$100 → mark paid → account balance decreases R$100 → mark unpaid → balance restored. Edit paid expense value to R$200 → balance reflects -R$100 additional. Move expense to different account → old account restored, new account deducted. Delete paid expense → balance restored.

---

### P2: Filtros e Busca

**User Story**: As a workspace member, I want to filter and search expenses by category, account, date range, and text so that I can quickly find specific transactions.

**Why P2**: Useful quality-of-life feature. The basic list works without it, but filtering becomes essential as transaction count grows.

**Acceptance Criteria**:

1. WHEN a user types in the search field THEN system SHALL filter expenses by description (partial match, case-insensitive) using a debounced server request (300ms).

2. WHEN a user selects a category filter THEN system SHALL show only expenses with that category.

3. WHEN a user selects an account filter THEN system SHALL show only expenses linked to that account.

4. WHEN a user selects a date range THEN system SHALL show only expenses within that period (inclusive).

5. WHEN a user selects the payment status filter THEN system SHALL show only paid, only unpaid, or all expenses.

6. WHEN multiple filters are active THEN system SHALL combine them with AND logic.

7. WHEN filters are active THEN system SHALL show a visual indicator (e.g., filter count badge) and a "Limpar filtros" button.

**Independent Test**: Create 5 expenses across 3 categories/2 accounts → filter by "Alimentação" → see only those → add account filter → see intersection → search "mercado" → see matching → clear all → all 5 visible.

---

### P2: Paginação

**User Story**: As a workspace member with many expenses, I want paginated lists so that the UI remains fast and responsive.

**Why P2**: Performance — an empty workspace doesn't need this, but as expenses accumulate, loading 200+ records degrades UX.

**Acceptance Criteria**:

1. WHEN the expense list exceeds 25 items THEN system SHALL paginate showing 25 per page with page navigation controls.

2. WHEN filters are active THEN pagination SHALL apply to filtered results, not the full dataset.

3. WHEN a user navigates between pages THEN filters SHALL be preserved (query string parameters).

**Independent Test**: Seed 30 expenses → verify 25 on page 1 with pagination controls → navigate to page 2 → see remaining 5. Apply filter → pagination adjusts to filtered count.

---

## Edge Cases

- WHEN value is zero or negative THEN system SHALL reject with "O valor deve ser maior que zero".
- WHEN value exceeds 999999999.99 THEN system SHALL reject with "O valor excede o limite permitido".
- WHEN description is empty THEN system SHALL reject with "A descrição é obrigatória".
- WHEN description exceeds 255 characters THEN system SHALL reject with "A descrição não pode ter mais de 255 caracteres".
- WHEN date is in the future THEN system SHALL accept (users may pre-register known expenses).
- WHEN date is missing THEN system SHALL default to today (or reject — TBD in implementation).
- WHEN account is archived (soft-deleted) THEN expense SHALL still reference it for historical integrity; account name displayed with "(Arquivada)" suffix.
- WHEN category is soft-deleted THEN expense SHALL still reference it; category name displayed with "(Removida)" suffix.
- WHEN tags are provided but some don't exist in workspace THEN system SHALL reject with "Tag inválida".
- WHEN account_id doesn't belong to the same workspace as the transaction THEN system SHALL reject with validation error.
- WHEN category_id doesn't belong to the same workspace as the transaction THEN system SHALL reject with validation error.
- WHEN unpaid list is empty but paid list has items THEN system SHALL still show the paid section with its header.
- WHEN all expenses are soft-deleted THEN system SHALL show empty state (not an error).
- WHEN a transaction references a category with type=Income (not Expense or Both) THEN system SHALL reject (a debit expense cannot use an income-only category).
- WHEN initial_balance edit on account happens independently THEN system SHALL NOT affect existing transactions (recalculateBalance handles the formula).

---

## Requirement Traceability

| ID       | Story                            | Phase  | Status  |
| -------- | -------------------------------- | ------ | ------- |
| DEBT-01  | P1: CRUD de Despesas em Débito   | Specify | Pending |
| DEBT-02  | P1: Pagamento de Despesa         | Specify | Pending |
| DEBT-03  | P2: Filtros e Busca              | -      | Pending |
| DEBT-04  | P2: Paginação                    | -      | Pending |

**Coverage:** 4 requirements, 0 mapped, 4 unmapped

---

## Success Criteria

- [ ] User can create a debit expense in < 15 seconds from the workspace dashboard (per workspace spec)
- [ ] Paying an expense immediately reflects in the account balance (no page refresh needed)
- [ ] Unpaid and paid expenses are visually distinguishable at a glance
- [ ] Balance integrity: no sequence of edit/pay/unpay/delete operations leaves balance in a wrong state
- [ ] AccountService::recalculateBalance is no longer a stub — it sums real transaction data
- [ ] Cross-workspace isolation: no transaction leaks between workspaces
- [ ] Soft deletes preserve historical data for auditing and future features
- [ ] Category type constraint prevents expense using income-only categories
