# Despesas de Cartão de Crédito — Specification

**Story ID:** CCXP-01
**Phase:** P1 — MVP Core
**Parent Spec:** `.specs/features/workspace-financeiro/spec.md`
**Dependencies:** CARD-01 (Cartões de Crédito), DEBT-01 (Despesas em Débito), CAT-01 (Categorias e Tags)

## Problem Statement

Com cartões cadastrados (CARD-01), usuários precisam registrar compras de cartão de crédito — individuais ou parceladas — para saber exatamente o que compõe cada fatura e quando cada parcela vence. Sem despesas de cartão, o cartão é um cadastro vazio: limite disponível nunca muda, fatura não existe, e o pagamento da fatura não debita a conta. Além disso, o usuário perde visibilidade sobre parcelas futuras que já comprometem seu orçamento.

Diferente das despesas em débito (DEBT-01), onde cada transação nasce "não paga" e o usuário marca o pagamento individualmente, as despesas de cartão operam por ciclo de fatura: o usuário paga a FATURA inteira de uma vez, e esse pagamento debita uma conta bancária selecionada. A fatura tem ciclo de fechamento/vencimento definido pelo cartão.

## Goals

- [ ] CRUD de despesas de cartão de crédito (compras individuais)
- [ ] Despesas parceladas: N parcelas geradas a partir de uma única compra
- [ ] Visualização da fatura atual (open) e faturas anteriores (closed/paid) por cartão
- [ ] Indicação visual de parcelamento ("3/12") na listagem de fatura
- [ ] Pagamento de fatura com débito automático da conta selecionada
- [ ] `available_limit` recalculado a cada operação de despesa e pagamento de fatura
- [ ] Encerramento automático de ciclo (job diário + fallback on-demand)
- [ ] Edição/exclusão de parcela com escopo (apenas esta / esta e futuras)
- [ ] Integração com `AccountService::recalculateBalance()` (pagamento de fatura)
- [ ] Integração com `CreditCardService::recalculateAvailableLimit()` (despesas e pagamentos)

## Key Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Card expense model | Reuse `Transaction` com `credit_card_id` nullable + `account_id` nullable | DEBT-01 já decidiu tabela única `transactions`; CCXP-01 adiciona link ao cartão |
| Diferenciação debit vs credit | `account_id` ≠ null = débito (DEBT-01); `credit_card_id` ≠ null = crédito (CCXP-01); mutual exclusivity enforced by validation | Sem novo enum; FKs existentes discriminam |
| Installment columns | Novas colunas `installment_number` (nullable int), `installments_total` (nullable int), `installment_group_id` (nullable uuid) em `transactions` | Single purchase → todos null; installment purchase → preenchidos; group_id agrupa parcelas da mesma compra |
| Valor por parcela | `value` por parcela = `round(total / count, 2)`; última parcela absorve diferença de arredondamento | Mantém soma das parcelas = total exato |
| Bill model | Novo modelo `CreditCardBill` (card, year, month, status, total, paid_at, paid_to_account_id, payment_transaction_id) | Entidade explícita para ciclo de fatura (open → closed → paid) e operação de pagamento |
| Bill uniqueness | Unique constraint `(credit_card_id, period_year, period_month)` | Uma fatura por cartão por mês |
| Bill-expense link | `credit_card_bill_id` FK nullable em `transactions` | Cada despesa/parcela pertence a exatamente uma fatura baseada na data + closing_day do cartão |
| Bill status enum | `BillStatus`: `Open`, `Closed`, `Paid` em `App\Enums\` | Lifecycle explícito com transições claras |
| Card expense `paid_at` | Sempre `null` para despesas de cartão individuais | Pagamento acontece na FATURA, não em despesas individuais |
| Bill creation | Lazy — bill é criada quando a primeira despesa entra no ciclo; job diário (P2) encerra e cria próxima | Simples; sem bills vazias para cartões sem uso |
| Bill payment transaction | Cria nova `Transaction` (type=Expense, account_id=conta selecionada, paid_at=now(), value=bill.total) | Reusa `AccountService::recalculateBalance()`; despesa aparece na lista de transações normalmente |
| Bill payment category | Auto-criado "Pagamento de Cartão" (type=Expense) por workspace na criação do primeiro cartão | Não requer input do usuário; category é system-managed (não deletável, nome editável) |
| Closing day semantics | Purchase ON closing_day → pertence à fatura que FECHA naquele dia | Convenção brasileira: data da compra ≤ data de fechamento → fatura do ciclo que fecha |
| Billing cycle computation | Purchase date D pertence à fatura cuja closing_date é a primeira ≥ D | closing_day=1: compra em 15/02 → fatura de março (fecha 01/03); compra em 02/03 → fatura de abril |
| available_limit formula | `credit_limit - sum(value de despesas em bills não pagas)` | Despesas em faturas já pagas não consomem limite (foram quitadas) |
| Installment edit scope | Prompt: "Apenas esta parcela" / "Esta e futuras" | Per AC #4 do workspace spec |
| Designed for | Single-card scope (sem multi-moeda, sem partial payment, sem interest) | v1 simples; extensível futuramente |

## Out of Scope

| Feature | Reason |
|---------|--------|
| Saque/advance em cartão (cash withdrawal) | Treat como despesa comum; sem mecânica especial de juros |
| Juros/interest sobre fatura não paga | v1: fatura é paga integralmente ou não; sem modelagem de mora |
| Pagamento parcial de fatura | v1: pagamento é binário (total ou nenhum); simplifica fluxo |
| Fatura parcelada (pagar fatura em N vezes) | Fora do escopo; usuário paga a fatura ou não |
| Reembolso/chargeback | Fluxo de estorno complexo; adiado |
| Multi-moeda | Apenas BRL (constraint do projeto) |
| Categorização automática de despesas de cartão | Feature separada (IA, IMPT-01); CCXP-01 usa categorias existentes |
| Anexos/comprovantes | Storage desnecessário para v1 |
| Cashback/recompensas | Cartão é genérico (CARD-01 decision); sem programa de pontos |
| Limites por tipo de compra | Cartão tem apenas `credit_limit` total (CARD-01) |

---

## User Stories

### P1: CRUD de Despesas de Cartão (Single Purchase) ⭐ MVP

**User Story**: As a workspace member, I want to register single credit card purchases with description, value, date, card, category and tags so that I can track what's on my card bill.

**Why P1**: É o bloco básico de registro de crédito. Sem isso, o cartão é só um cadastro. Single purchase = caso default (installments=1).

**Acceptance Criteria**:

1. WHEN a workspace member (admin or editor) submits a single card purchase (description, value, date, credit_card_id, category_id, tags[]) THEN system SHALL create one Transaction with `type=Expense`, `paid_at=null`, `account_id=null`, `credit_card_id=<card>`, `installment_number=null`, `installments_total=null`, `installment_group_id=null` linked to the current workspace and associate it to the appropriate open bill based on `date` and `card.closing_day`.

2. WHEN a user views a card's bill THEN system SHALL list all expenses (single and installments) on that bill with description, value in BRL format (R$ X.XXX,XX), date, category name with color indicator, tag chips, and installment indicator ("N/M") when applicable.

3. WHEN a user edits a single card expense's description, value, date, card, category or tags THEN system SHALL update the Transaction. Tag sync SHALL use the `taggable` polymorphic relationship. Date change SHALL re-bucket the expense to the correct bill.

4. WHEN a user deletes a single card expense THEN system SHALL soft-delete it. The expense SHALL not appear on the bill listing. The card's `available_limit` SHALL be recalculated.

5. WHEN the bill listing is empty THEN system SHALL show an empty state with CTA "Registrar primeira compra no cartão".

6. WHEN a viewer attempts to create, edit, or delete a card expense THEN system SHALL deny with 403.

7. WHEN a user attempts to create a card expense on a card from a different workspace THEN system SHALL return 404 (scoped route model binding).

8. WHEN a user attempts to set `account_id` AND `credit_card_id` on the same transaction THEN system SHALL reject with validation error "Uma transação deve ter conta OU cartão, nunca ambos".

**Independent Test**: Create 2 single purchases with different cards/categories/tags → verify they appear on correct bills (current open bill of each card) with correct BRL formatting → edit one description → verify update → delete one → verify hidden from bill and `available_limit` restored.

---

### P1: Despesas Parceladas ⭐ MVP

**User Story**: As a workspace member, I want to record installment purchases (e.g., "R$1200 in 12x of R$100") so that each installment appears on the correct monthly bill.

**Why P1**: Parcelamento é o caso mais comum de cartão no Brasil. Sem isso, o cartão perde sua principal utilidade. Cada parcela vence num mês diferente e entra numa fatura diferente.

**Acceptance Criteria**:

1. WHEN a user submits an installment purchase (description, total_value, installments_count, first_installment_date, credit_card_id, category_id, tags[]) THEN system SHALL create N=installments_count Transaction rows where:
   - Each row: `type=Expense`, `account_id=null`, `credit_card_id=<card>`, `installments_total=N`, `installment_group_id=<shared uuid>`
   - Row i (1-indexed): `installment_number=i`, `value=round(total/N, 2)` (last installment absorbs remainder), `date=first_installment_date + (i-1) months`
   - Tags, category, description are identical across all installments
   - Each installment is associated to the appropriate bill based on its `date` and `card.closing_day` (installments may span multiple bills)

2. WHEN installments_count is 1 THEN system SHALL treat as single purchase (installment fields null, no group_id).

3. WHEN viewing a card bill THAT includes installment entries THEN system SHALL display each installment as a separate line with indicator "N/M" (e.g., "3/12") next to the description.

4. WHEN viewing a card bill THEN system SHALL group installments of the same purchase visually (e.g., shared background or "parcelas de: {description}" annotation) so the user can recognize them as part of one purchase.

5. WHEN a user creates an installment purchase AND the installments span across a year boundary (e.g., first installment Dec 2026, last installment Nov 2027) THEN system SHALL correctly compute due dates including month/year overflow.

6. WHEN a user creates an installment purchase AND total_value / installments_count does not divide evenly THEN system SHALL round per-installment value to 2 decimal places with the LAST installment absorbing the remainder so that `sum(installment_values) == total_value` exactly.

7. WHEN a user attempts to create an installment purchase with installments_count < 1 or > 48 THEN system SHALL reject with validation error "Número de parcelas deve estar entre 1 e 48".

8. WHEN a user attempts to create an installment purchase with total_value ≤ 0 THEN system SHALL reject with "O valor total deve ser maior que zero".

**Independent Test**: Create purchase R$ 1200 in 12x starting 15/02/2026 on card with closing_day=1 → verify 12 rows in transactions table → verify installment 1 belongs to March 2026 bill (closing 01/03), installment 2 to April 2026 bill, ..., installment 12 to January 2027 bill → verify each installment value = R$ 100 → verify total sum = R$ 1200 → verify installment indicator "3/12" on bill view.

---

### P1: Visualização de Fatura ⭐ MVP

**User Story**: As a workspace member, I want to view a card's bill (current and past) with the total and breakdown of expenses so that I know how much I owe and what's on it.

**Why P1**: A fatura é o coração do cartão. É o que o usuário quer ver primeiro: "quanto vou pagar e quando".

**Acceptance Criteria**:

1. WHEN a user navigates to `/w/{workspace}/cards/{card}` (card show page) THEN system SHALL display:
   - Card name, credit_limit, available_limit
   - Current open bill: total amount, closing date, due date, status "Aberta"
   - List of all expenses (single + installments) on the current bill, ordered by date
   - Each expense: description, value (BRL), date, category color, tag chips, installment indicator
   - Navigation to previous bills (closed/paid) with their totals and statuses

2. WHEN the open bill is empty (no expenses yet for the current cycle) THEN system SHALL show empty state "Nenhuma compra neste ciclo ainda".

3. WHEN a user views a previous CLOSED bill THEN system SHALL display the bill total, status "Fechada" with closing/due dates, and list of expenses that belonged to that cycle.

4. WHEN a user views a previous PAID bill THEN system SHALL display bill total, status "Paga" with payment date, paid-to account name, and the payment transaction reference.

5. WHEN there are multiple historical bills on a card THEN system SHALL list them (collapsible or paginated) with year/month label, total, status, and click-to-view.

6. WHEN available_limit is shown on the card page THEN system SHALL display it from the persisted `available_limit` column (NO on-the-fly computation).

**Independent Test**: Create 3 purchases on a card (one single, one 3-installment, one 12-installment) → view current bill → see all entries with installment indicators → bill total = sum of all entries → verify `available_limit = credit_limit - current_bill_total` and any open future-bill installments (from the 12x purchase) → click a previous (non-existent yet) bill → "Não há faturas anteriores".

---

### P1: Pagamento de Fatura ⭐ MVP

**User Story**: As a workspace member, I want to pay a closed credit card bill by selecting a bank account so that the bill amount is debited from that account.

**Why P1**: Sem pagamento, o cartão não impacta em conta real; o saldo do usuário fica incorreto. O pagamento é a ponte entre crédito e débito.

**Acceptance Criteria**:

1. WHEN a user clicks "Pagar fatura" on a CLOSED bill THEN system SHALL prompt the user to select one of the workspace's active accounts (current_balance > 0) and confirm the amount displayed (= bill.total).

2. WHEN the user confirms payment with a selected account THEN system SHALL within a single DB transaction:
   - Create a new `Transaction` with `type=Expense`, `account_id=<selected>`, `paid_at=now()`, `value=bill.total`, `description="Pagamento Fatura {card.name} {month}/{year}"`, `category_id=<Pagamento de Cartão system category>`, `credit_card_id=null`
   - Mark the bill: `status=Paid`, `paid_at=now()`, `paid_to_account_id=<selected>`, `payment_transaction_id=<new transaction>`
   - Call `AccountService::recalculateBalance(selected_account)` to deduct the bill total from the account's `current_balance`
   - Call `CreditCardService::recalculateAvailableLimit(card)` to restore the limit (paid expenses no longer count)

3. WHEN the selected account has insufficient balance to cover the bill total THEN system SHALL still allow the payment (account can go negative — bank overdraft is allowed in v1) but SHALL warn the user with a confirmation prompt.

4. WHEN someone attempts to pay an OPEN bill (not yet closed) THEN system SHALL reject with "A fatura ainda está aberta. Encerre o ciclo antes de pagar." A separate action "Pagar fatura antecipada" might exist for closed-bill-only flow (P2+).

5. WHEN someone attempts to pay an already-PAID bill THEN system SHALL reject with "Esta fatura já foi paga em {paid_at}".

6. WHEN a user with viewer role attempts to pay a bill THEN system SHALL return 403.

7. WHEN the "Pagamento de Cartão" category does not exist in the workspace (e.g., CCXP-01 not fully initialized) THEN system SHALL auto-create it with name="Pagamento de Cartão", type=Expense, color="#6B7280", workspace_id=current before creating the payment transaction.

**Independent Test**: Create card with credit_limit R$5000 → add expense R$1000 → close bill (mark as CLOSED) → pay bill selecting account with R$5000 balance → account balance drops to R$4000 → card `available_limit` returns to R$5000 → bill status="Paga" → payment transaction appears in transactions list with description "Pagamento Fatura {card} {month}/{year}".

---

### P2: Edição e Exclusão de Parcela com Escopo

**User Story**: As a workspace member editing or deleting an installment, I want to choose the scope ("this only" or "this and all future") so that I can fix a typo in one installment or cancel remaining installments.

**Why P2**: A edição/exclusão com escopo é um UX refinamento. Sem ela, o usuário é forçado a editar/deletar uma parcela por vez ou a compra inteira. P2 porque o core (CRUD + pagamento de fatura) já resolve 80% dos casos.

**Acceptance Criteria**:

1. WHEN a user edits an installment THEN system SHALL prompt: "Apenas esta parcela" or "Esta e futuras".

2. WHEN the user selects "Apenas esta parcela" THEN system SHALL update only the current installment row. Available_limit SHALL be recalculated based on the new value.

3. WHEN the user selects "Esta e futuras" THEN system SHALL update all installments with `installment_group_id=<group>` AND `installment_number >= current.installment_number`. Changes apply to description, category, tags, card. Value redistribution: remaining installments keep their original values (no automatic recalculation).

4. WHEN a user deletes an installment THEN system SHALL prompt: "Apenas esta parcela" or "Esta e futuras".

5. WHEN the user selects "Apenas esta parcela" for deletion THEN system SHALL soft-delete only the current installment row. Available_limit SHALL be recalculated to reflect the restored amount.

6. WHEN the user selects "Esta e futuras" for deletion THEN system SHALL soft-delete all installments with `installment_group_id=<group>` AND `installment_number >= current.installment_number`. Available_limit SHALL be recalculated for all affected bills.

7. WHEN deleting an installment causes the parent purchase to have ZERO remaining installments THEN system SHALL NOT leave an orphaned installment_group; all rows deleted normally (soft-delete).

8. WHEN the edit/delete action affects any installment that belongs to a PAID bill THEN system SHALL reject with "Não é possível editar/excluir parcelas de faturas já pagas". Only installments on OPEN or CLOSED bills can be edited/deleted.

**Independent Test**: Create 12x purchase → edit installment 5 with "Esta e futuras" change description → verify installments 5-12 have new description, 1-4 unchanged → edit installment 3 with "Apenas esta parcela" change value → verify only installment 3 changed → delete installment 8 with "Esta e futuras" → verify installments 8-12 gone, 1-7 remain.

---

### P2: Encerramento Automático de Ciclo

**User Story**: As a workspace member, I want my card's billing cycles to close automatically when the closing day passes, so that bills are ready to pay on time without manual intervention.

**Why P2**: Core operations (purchase creation, bill payment) work with OPENED bills. Encerramento automático mantém o lifecycle sem necessidade de ação manual; melhora UX progressivamente.

**Acceptance Criteria**:

1. WHEN a scheduled job runs daily at midnight THEN system SHALL find all bills with `status=Open` AND `closing_date < today` and mark them as `status=Closed`. For each closed bill, the next OPEN bill for the NEXT cycle SHALL be created (so future purchases have a target bill).

2. WHEN a user views a card on a date past the current open bill's closing_date (job not yet run) THEN system SHALL on-demand verify and close the bill before rendering.

3. WHEN a bill is closed THEN system SHALL NOT allow new expenses to be added to it. New expenses with `date > closing_date` SHALL be associated to the next OPEN bill automatically.

4. WHEN the next month's open bill does not exist (lazily created) AND the previous bill is being closed THEN system SHALL create the next open bill with `period_year`/`period_month` set to the next cycle.

5. WHEN the scheduled job fails for a card (e.g., db error) THEN system SHALL log the error but NOT abort the entire job — other cards continue to be processed.

**Independent Test**: Create card with closing_day=1, today=2026-03-05 → run closure job → bill for Feb 2026 cycle (closing 01/03) transitions to CLOSED → new open bill for March 2026 cycle created → new purchase with date 2026-03-10 enters the new March bill.

---

### P2: Filtros em Despesas de Cartão

**User Story**: As a workspace member, I want to filter card expenses by category, period, and bill status so that I can quickly find specific purchases.

**Why P2**: Qualidade de vida — útil com volume crescente; não bloqueia o core.

**Acceptance Criteria**:

1. WHEN a user types in the search field on a card's bill page THEN system SHALL filter expenses by description (partial, case-insensitive) using a debounced server request (300ms).

2. WHEN a user selects a category filter THEN system SHALL show only expenses with that category.

3. WHEN a user selects a date range filter THEN system SHALL show only expenses within that period (inclusive).

4. WHEN a user selects a bill status filter (Open/Closed/Paid) THEN system SHALL show only bills matching that status.

5. WHEN multiple filters are active THEN system SHALL combine them with AND logic.

6. WHEN filters are active THEN system SHALL show a visual indicator and a "Limpar filtros" button.

**Independent Test**: Create 5 purchases on a card across 3 categories and 2 bills (open + closed) → filter by category "Alimentação" → see only matching → add status "Aberta" → see intersection → clear → all 5 visible across both bills.

---

## Edge Cases

- WHEN value of a single purchase is zero or negative THEN system SHALL reject with "O valor deve ser maior que zero".
- WHEN total_value of an installment purchase is zero or negative THEN system SHALL reject with "O valor total deve ser maior que zero".
- WHEN installments_count > 48 THEN system SHALL reject with "Número de parcelas deve estar entre 1 e 48".
- WHEN first_installment_date is in the future THEN system SHALL accept (user pre-registers future purchases).
- WHEN description exceeds 255 characters THEN system SHALL reject with "A descrição não pode ter mais de 255 caracteres".
- WHEN a credit card is archived (soft-deleted) THEN card expenses SHALL still reference it; card name displayed with "(Arquivado)" suffix; NEW expenses SHALL not be allowed on archived cards (validation rejects).
- WHEN a user attempts to create a card expense on an archived card THEN system SHALL reject with "Não é possível registrar despesas em um cartão arquivado".
- WHEN a category used by a card expense is soft-deleted THEN expense SHALL still reference it; category name displayed with "(Removida)" suffix.
- WHEN category_id doesn't belong to the same workspace as the card THEN system SHALL reject with validation error.
- WHEN category.type = Income (not Expense or Both) THEN system SHALL reject with "Esta categoria não aceita despesas" (same rule as DEBT-01).
- WHEN tags are provided but some don't exist in workspace THEN system SHALL reject with "Tag inválida".
- WHEN an account is archived (soft-deleted) THEN it SHALL not appear in the "Pagar fatura" account selection list.
- WHEN a user pays a bill selecting an archived account (race condition) THEN system SHALL reject with "A conta selecionada foi arquivada".
- WHEN value > 999999999.99 THEN system SHALL reject with "O valor excede o limite permitido".
- WHEN a purchase date is exactly on the card's closing_day THEN the expense SHALL belong to the bill that CLOSES on that day (in cycle ending that day).
- WHEN a purchase date is 1 day AFTER the card's closing_day THEN the expense SHALL belong to the NEXT bill (cycle starting the day after closing).
- WHEN closing_day is 31 and the month has fewer days (Feb=28/29, Apr=30, etc.) THEN the effective closing_date SHALL be the last day of that month.
- WHEN due_day is 31 and the month has fewer days THEN the effective due_date SHALL be the last day of that month.
- WHEN the "Pagamento de Cartão" system category is deleted (manual DB intervention) THEN the next bill payment SHALL recreate it automatically (idempotent logic).
- WHEN a bill payment transaction is deleted by mistake (the user accesses the transactions list and deletes it) THEN system SHALL prevent deletion with "Esta transação foi gerada pelo pagamento de uma fatura. Use 'Desfazer pagamento' na fatura."
- WHEN a user uses "Desfazer pagamento" on a paid bill THEN system SHALL: soft-delete the payment transaction, mark bill back to `Closed`, restore account balance via `AccountService::recalculateBalance()`, recalculate card `available_limit`.
- WHEN the scheduled closing job runs but no OPEN bill exists for a card (e.g., never had any purchases) THEN system SHALL NOT create an empty CLOSED bill (bills remain lazy).
- WHEN an installment's date is changed via "Apenas esta parcela" edit AND the new date falls into a different bill's cycle THEN the installment SHALL be re-bucketed to the appropriate bill (bill_id updated).
- WHEN an installment's edit/delete affects installments in another bill's cycle THEN system SHALL recalculate `total_amount` of affected bills.
- WHEN a credit card with paid bills is archived THEN the bills and their expenses SHALL remain visible (historical integrity); the archived card name shows "(Arquivado)" on bill pages.
- WHEN a credit card is archived AND then un-archived (restored) THEN all historical bills and expenses SHALL be visible again; new expenses can be created normally.
- WHEN two bills exist for the same (card, year, month) due to a race condition THEN system SHALL enforce the unique constraint (card_id, period_year, period_month) to prevent duplicates; the duplicate insert SHALL fail with a 500 error.

---

## Requirement Traceability

| ID         | Story                                | Phase    | Status   |
| ---------- | ------------------------------------ | -------- | -------- |
| CCXP-01    | P1: CRUD de Despesas de Cartão       | Specify  | Done     |
| CCXP-02    | P1: Despesas Parceladas              | Specify  | Done     |
| CCXP-03    | P1: Visualização de Fatura            | Specify  | Done     |
| CCXP-04    | P1: Pagamento de Fatura               | Specify  | Done     |
| CCXP-05    | P2: Edição/Exclusão de Parcela       | Specify  | Done     |
| CCXP-06    | P2: Encerramento Automático de Ciclo | Specify  | Done     |
| CCXP-07    | P2: Filtros em Despesas de Cartão    | Specify  | Done     |

**Coverage:** 7 requirements, 7 mapped to stories, 0 unmapped

**ID format:** `CCXP-[NUMBER]`

**Status values:** Pending → In Design → In Tasks → Implementing → Verified

---

## Success Criteria

- [ ] User can register a single card purchase in < 15 seconds from the card page
- [ ] User can register a 12x installment purchase and each installment appears on the correct monthly bill
- [ ] Bill total accurately reflects sum of all single + installment entries in the cycle
- [ ] `available_limit` is correctly persisted and reflects: `credit_limit − sum of card expenses on non-paid bills`
- [ ] Paying a bill correctly debits the selected account (account balance decreases by bill total)
- [ ] After bill payment, `available_limit` is restored (paid expenses no longer count)
- [ ] Installment indicator "N/M" is visible and correct on bill listings
- [ ] Auto-closing of cycles works via scheduled job; on-demand fallback covers missed runs
- [ ] Edit/delete of installments with scope (this only / this and future) works as specified
- [ ] Cross-workspace isolation: no card expense leaks between workspaces
- [ ] Soft deletes preserve historical bills and expenses for audit
- [ ] The "Pagamento de Cartão" category is auto-created per workspace when the first credit card is added; not user-deletable; name editable
- [ ] Undoing a bill payment correctly soft-deletes the payment transaction, restores the account balance, and reverts bill to Closed