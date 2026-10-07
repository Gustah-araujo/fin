# Cartões de Crédito V2 (Refactor) — Specification

**Story ID:** CCV2
**Phase:** P1 — Refactor do MVP Core
**Supersedes:** CCXP-01 (parcial), CCXP-06 AC2/AC3/AC4 (lazy/on-demand nunca implementadas conforme especificado), CCXP-07 (absorvido), REEX (exclusão de recorrência em cartão)
**Dependencies:** CARD-01 (Cartões de Crédito), CCXP-01 (Despesas de Cartão), REEX-01 (Recorrências), DTBL-01/DTBL-02 (DataTable)

## Problem Statement

Despesas de cartão hoje vivem num fluxo paralelo: form dedicado (`CardExpenses`), páginas próprias e pagamento de fatura numa tela separada — embora arquiteturalmente já sejam `Transactions` comuns (D-24). O invariant D-28 ("despesa de cartão é paga via fatura, nunca individualmente") não é enforced: o botão "Pagar" aparece na lista de despesas para transações de cartão e o backend não tem guard. Pagar a fatura não marca as despesas individuais como pagas (`paid_at` fica eternamente null), e a criação lazy de faturas (D-32) impede recorrências de cartão com buffer de despesas futuras — a spec REEX deixou cartão explicitamente out of scope por isso.

Este refactor unifica o registro de despesas num único formulário, enforce o pagamento exclusivamente via fatura, pré-cadastra faturas 13 meses à frente via job, traz recorrências para o cartão e transforma a UI do cartão no hub único do crédito: cards de limite, despesas scopadas por fatura e pagamento inline.

## Goals

- [ ] Form único de despesas com seletor "forma de pagamento" (Conta/Cartão) + parcelas 1–48 quando cartão
- [ ] Remoção do fluxo dedicado CardExpenses (controller, service, FormRequests, páginas, rotas)
- [ ] Guard de pagamento: despesa de cartão paga APENAS via fatura (backend + frontend)
- [ ] Pagamento de fatura marca TODAS as despesas vinculadas como pagas; desfazer reverte
- [ ] Faturas pré-criadas 13 meses à frente (na criação do cartão + job diário) com fallback idempotente
- [ ] Recorrências em cartão (sempre 1x) com buffer materializado em faturas existentes
- [ ] UI do cartão: cards de limite + seletor de faturas + despesas scopadas por fatura + pagamento inline
- [ ] Despesas de cartão na listagem geral de despesas com coluna "Cartão" + filtros por cartão/fatura

## Key Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| D-73: Fluxo unificado | Form de despesas único com seletor "forma de pagamento" (Conta/Cartão); cartão revela parcelas 1–48; fluxo `CardExpenses` (controller, service, páginas, rotas) removido | Despesa de cartão é despesa comum com vínculo; UX única; redirect do cartão pré-preenche |
| D-74: Guard de pagamento | `TransactionService::pay()`/`unpay()` rejeitam transações com `credit_card_id`; botão oculto na listagem | Enforcement real do D-28 (hoje violável pela lista de despesas) |
| D-75: Fatura paga marca despesas | `payBill()` seta `paid_at` em todas as transações da fatura; `undoPayment()` reverte para null | A fatura é a única fonte de `paid_at` em cartão |
| D-76: Faturas pré-criadas | 13 meses (ciclo atual + 12) criados sincronamente na criação do cartão + job diário mantém horizonte; buffer usa `findOrCreateBill` idempotente como garantia | Supersede D-32 (lazy); viabiliza recorrências de cartão com buffer; faturas futuras visíveis na UI |
| D-77: Fechamento uniforme | Job diário fecha TODAS as faturas com `closing_date` vencida (incluindo vazias) + fallback on-demand ao renderizar a UI do cartão | Ciclo uniforme com faturas pré-criadas; implementa a CCXP-06 AC2 (nunca implementada) |
| D-78: Recorrência em cartão | `recurrences.account_id` vira nullable + `credit_card_id` (mutuamente exclusivos); recorrente em cartão sempre 1x, sem parcelas | Supersede a exclusão da spec REEX; evita explosão combinatória (recorrência × parcelas × escopos) |
| D-79: Colisão com fatura paga | Criação/edição de recorrência de cartão bloqueada se qualquer ocorrência entre `start_date` e hoje cair em fatura paga | Preserva imutabilidade da fatura paga (decisão do usuário — rejeitou "pular ciclos pagos") |
| D-80: UI do cartão hub | Despesas sempre scopadas por fatura com seletor no estilo do month-picker da UI de despesas; cards de limite globais + total da fatura em vista; pagamento inline | Decisão do usuário: a UI do cartão serve para criar cartões, registrar despesa (redirect), ver despesas por fatura, ver limites e pagar a fatura |

## Out of Scope

| Feature | Reason |
|---------|--------|
| Pagamento de fatura aberta (antecipado) | Mantém Open→Closed→Paid — decisão do usuário na discussão |
| Pagamento parcial de fatura | Binário ( herdado CCXP-01) |
| Parcelamento para débito em conta | Parcelas continuam exclusivas de cartão |
| Recorrência parcelada em cartão | Sempre 1x (D-78) |
| Juros/mora, multi-moeda, cashback, anexos, reembolso | Herdado do out of scope do CCXP-01 |
| Mudanças no `PlanningService` | Já lê `transactions`; buffer de cartão flui automaticamente para a projeção |
| Remoção da página `Bills/Show` | Mantida como detalhe/histórico de fatura fechada/paga |
| Fatura como transferência entre contas | Pagamento continua como `Transaction` de despesa com categoria de sistema (D-30/D-31) |

## Supersedes & Preserves

**Superseded:**

| Original | Motivo |
|----------|--------|
| CCXP-01 ACs do fluxo dedicado (`cards/{card}/expenses/*`) | Substituído pelo form unificado (CCV2-01) |
| CCXP-01 Key Decision "Bill creation lazy" (D-32) | Substituído por pré-criação + fallback (D-76) |
| CCXP-03 (Visualização de Fatura — estrutura da página do cartão) | Substituído pela UI scopada por fatura (CCV2-06) |
| CCXP-06 AC2 (fallback on-demand) e AC3/AC4 (criação lazy da próxima fatura) | AC2 agora implementada (D-77); AC3/AC4 substituídas pela pré-criação |
| CCXP-07 (Filtros na página de fatura) | Absorvido pelo scoping por fatura (CCV2-06) + filtros na listagem geral (CCV2-07) |
| REEX: "recorrência em cartão out of scope" | Superado pelas recorrências de cartão (CCV2-05) |

**Preserved (semântica carregada para o fluxo unificado):** parcelamento com resto na última (D-33), escopos de edição/exclusão de parcela (CCXP-05/D-44/D-45), re-bucketing por mudança de data, fórmula do `available_limit` (D-29), transação de pagamento + categoria de sistema (D-30/D-31), imutabilidade de fatura paga, exclusividade conta↔cartão (D-24).

---

## User Stories

### P1: Formulário Unificado de Despesas ⭐ MVP

**User Story**: As a workspace member, I want to register credit card expenses (single or installment) in the standard expense form by selecting the card as payment method, so that all my expenses are created in one single place.

**Why P1**: É a mudança estrutural central do refactor — sem ela, o fluxo dedicado continuaria existindo e o resto da feature não se conecta.

**Acceptance Criteria**:

1. WHEN a member opens the expense creation form THEN system SHALL display a "forma de pagamento" selector with "Conta" (default) and "Cartão de crédito".
2. WHEN "Cartão de crédito" is selected THEN system SHALL swap the account select for a card select (active cards of the workspace), reveal the installments field (integer 1–48, default 1), and display "Valor total" (total_value) instead of "Valor" when installments > 1.
3. WHEN the form is submitted with card + installments=1 THEN system SHALL create one Transaction with `type=Expense`, `account_id=null`, `credit_card_id=<card>`, `paid_at=null`, linked to the bill of the period computed from `date` + `card.closing_day` (existing CCXP-01 semantics).
4. WHEN the form is submitted with card + installments=N>1 THEN system SHALL create N Transaction rows per CCXP-02 semantics: `value=round(total/N, 2)` (last absorbs remainder), `date=first + (i-1) months`, shared `installment_group_id`, each linked to its period's bill.
5. WHEN both `account_id` and `credit_card_id` are submitted THEN system SHALL reject with "Uma transação deve ter conta OU cartão, nunca ambos" (existing rule, now in the unified form).
6. WHEN neither account nor card is submitted THEN system SHALL reject with a validation error (exactly one is required).
7. WHEN installments > 1 is submitted without total_value THEN system SHALL reject with "O valor total é obrigatório para compras parceladas".
8. WHEN the recurrence toggle is ON and card is selected THEN system SHALL lock the installments field to 1 (recurrence × installments not allowed — CCV2-05).
9. WHEN the user clicks "Nova despesa neste cartão" on the card page THEN system SHALL redirect to the expense creation form with forma de pagamento=Cartão and the card pre-selected (e.g., via query string).
10. WHEN a card expense is edited THEN system SHALL offer the same unified form with the installment scope prompt ("Apenas esta parcela" / "Esta e futuras") and re-bucketing on date change (existing CCXP-05 semantics preserved).
11. WHEN a user navigates to any legacy card-expenses route (`cards/{card}/expenses/*`) THEN system SHALL return 404 (dedicated flow removed).
12. WHEN a viewer attempts to create/edit/delete a card expense via the unified form THEN system SHALL deny with 403 (existing policy).
13. WHEN the submitted card belongs to another workspace or is archived THEN system SHALL reject (existing rules preserved).

**Independent Test**: From the card page, click "Nova despesa neste cartão" → form opens with card pre-selected → submit a 12x purchase → 12 transactions each linked to the correct bills → edit installment 5 with "Esta e futuras" → scope applied → legacy CardExpenses routes return 404.

---

### P1: Despesa de Cartão Paga Somente via Fatura ⭐ MVP

**User Story**: As a workspace member, I want card expenses to be paid exclusively through the bill payment so that account balance and card limit never diverge.

**Why P1**: O invariant D-28 existe no papel mas não é enforced — hoje é possível "pagar" uma despesa de cartão pela lista de despesas, sem efeito no saldo.

**Acceptance Criteria**:

1. WHEN `TransactionService::pay()` is invoked on a transaction with `credit_card_id` ≠ null THEN system SHALL reject with "Despesas de cartão são pagas através da fatura do cartão".
2. WHEN `TransactionService::unpay()` is invoked on a card transaction THEN system SHALL reject with the same rule.
3. WHEN the pay endpoint (`POST transactions.pay`) receives a card transaction THEN system SHALL return 422 with that message.
4. WHEN the expenses list renders a card expense THEN system SHALL NOT render the "Pagar"/"Desmarcar" buttons for it; the status column SHALL display the bill-linked status (CCV2-07).
5. WHEN a card expense is created or updated THEN `paid_at` SHALL remain null (paid state is never accepted from payload for card expenses).

**Independent Test**: Create a card expense → attempt POST pay → 422 with message → list shows no pay button → pay the bill → expense shows as paid via bill.

---

### P1: Pagamento de Fatura Marca as Despesas ⭐ MVP

**User Story**: As a workspace member, I want all expenses of a bill to be marked as paid when I pay that bill so that my expense list reflects reality.

**Why P1**: Hoje pagar a fatura cria a transação de débito e marca a fatura, mas as despesas individuais ficam eternamente "não pagas" — status inconsistente na listagem.

**Acceptance Criteria**:

1. WHEN a user pays a CLOSED bill THEN system SHALL, within the same DB transaction as the existing payment logic, set `paid_at=<payment timestamp>` on ALL non-deleted transactions linked to that bill.
2. WHEN the bill payment is undone THEN system SHALL revert `paid_at` to null on all transactions of that bill (mirror behavior).
3. WHEN the payment completes THEN the existing mechanics SHALL be preserved: debit Transaction with system category "Pagamento de Cartão", `AccountService::recalculateBalance()`, bill `status=Paid` + `paid_at` + `paid_to_account_id` + `payment_transaction_id`, `CreditCardService::recalculateAvailableLimit()`.
4. WHEN someone attempts to pay an OPEN bill THEN system SHALL reject with "A fatura ainda está aberta. Encerre o ciclo antes de pagar." (existing behavior preserved).
5. WHEN someone attempts to pay an already-PAID bill THEN system SHALL reject (existing).
6. WHEN the bill has zero expenses (total 0) THEN system SHALL reject payment with "Esta fatura não possui despesas".
7. WHEN a viewer attempts to pay a bill THEN system SHALL return 403 (existing).

**Independent Test**: Bill with 3 expenses (2 single + 1 installment) → pay → all 3 have `paid_at` set → status "Paga" in the list → undo → all 3 back to null.

---

### P1: Faturas Pré-criadas ⭐ MVP

**User Story**: As a workspace member, I want my card's future bills to exist in advance so that recurring card expenses can be scheduled into them and I can see future commitments.

**Why P1**: Pré-requisito direto das recorrências de cartão (o buffer precisa de faturas existentes) e da UI scopada por fatura; supersede a criação lazy (D-32).

**Acceptance Criteria**:

1. WHEN a credit card is created THEN system SHALL synchronously pre-create 13 bills (current cycle + 12 future cycles) with `status=Open`, `total_amount=0`, and computed `closing_date`/`due_date` per existing rules (closing_day/due_day month-end clamping).
2. WHEN the daily scheduled job runs THEN system SHALL ensure every non-archived card has bills up to 13 months ahead, creating the missing ones (idempotent).
3. WHEN the daily job closes bills THEN system SHALL close ALL Open bills with `closing_date < today` — including empty ones (uniform lifecycle; supersedes the "no empty bills" lazy rule).
4. WHEN any expense creation (manual, installment, recurrence buffer) targets a period whose bill does not exist (beyond horizon, legacy card, race) THEN system SHALL create it via the idempotent `findOrCreateBill` (unique constraint `(credit_card_id, period_year, period_month)` protects against duplicates).
5. WHEN the card page renders and any bill has `closing_date < today` but `status=Open` THEN system SHALL close it on-demand before rendering (implements the never-implemented CCXP-06 AC2).
6. WHEN a card is archived THEN the pre-creation job SHALL skip it; existing bills remain untouched.
7. WHEN a user views the card page invoice selector THEN pre-created future bills SHALL be listed with their (possibly zero) totals.
8. WHEN the pre-creation job fails for one card THEN system SHALL log the error and continue with the other cards (per-card try/catch, mirrors `CloseBillsJob` resilience).

**Independent Test**: Create card → 13 open bills exist → run daily job forward → horizon maintained and past bills closed (even empty) → create recurrence with buffer 20 → bills beyond the 13-month horizon created on demand by the buffer.

---

### P1: Recorrências em Cartão ⭐ MVP

**User Story**: As a workspace member, I want recurring expenses on my credit card (e.g., monthly subscriptions) so that they are automatically scheduled into future bills.

**Why P1**: Gap explícito da spec REEX (cartão out of scope); o buffer de despesas futuras exige faturas — resolvido por CCV2-04.

**Acceptance Criteria**:

1. WHEN a member creates an expense with recurrence ON and forma de pagamento=Cartão THEN system SHALL create a Recurrence with `credit_card_id` set and `account_id=null` (mutual exclusivity enforced by validation).
2. WHEN a card recurrence is created THEN system SHALL materialize the buffer (retroactive occurrences from `start_date` to today + `buffer_ahead` future occurrences) as Transactions with `credit_card_id`, `paid_at=null`, each linked to its period's bill via `findOrCreateBill`.
3. WHEN a card recurrence is created with installments > 1 THEN system SHALL reject with "Despesas recorrentes em cartão não podem ser parceladas".
4. WHEN any occurrence between `start_date` and today falls on a PAID bill of that card THEN system SHALL reject creation with "A data de início colide com faturas já pagas do cartão".
5. WHEN occurrences fall on CLOSED (unpaid) bills THEN system SHALL allow creation (expenses attach; bill totals recalculated; paid when the bill is paid).
6. WHEN the daily recurrence job maintains the buffer of a card recurrence THEN each new future instance SHALL be linked to its period's bill (`findOrCreateBill` guarantee).
7. WHEN a card recurrence instance is edited/deleted THEN the existing scope patterns SHALL apply ("Apenas esta" / "Esta e futuras", D-44/D-45).
8. WHEN a card recurrence appears in the `/recurrences` list THEN system SHALL display the card (instead of account); the existing type filter works unchanged.
9. WHEN the card of an active recurrence is archived THEN buffer generation SHALL skip that recurrence (no new instances; existing ones remain).
10. WHEN a card recurrence's bill is paid THEN its instances SHALL be marked paid via the bill payment (CCV2-03) — no special-casing.
11. WHEN a card recurrence's `start_date` is changed (edit) such that any occurrence between `start_date` and today would fall on a PAID bill THEN system SHALL reject with the same error as creation.

**Independent Test**: Create card recurrence (monthly, buffer 12) starting today → 13 transactions (today + 12 future) each on its period's bill → attempt creation with `start_date` 3 months ago where an old bill is paid → rejected → `/recurrences` shows the card name.

---

### P1: UI do Cartão como Hub ⭐ MVP

**User Story**: As a workspace member, I want the card page to show limit cards, an invoice selector with that invoice's expenses, and inline bill payment so that I manage everything about the card in one place.

**Why P1**: A UI atual fragmenta o fluxo (fatura em página separada, sem cards de limite, pagamento fora do cartão); o usuário definiu a UI do cartão como hub único do crédito.

**Acceptance Criteria**:

1. WHEN a user opens the card page THEN system SHALL display limit cards: "Limite total" (`credit_limit`), "Consumido" (sum of expenses on non-paid bills = `credit_limit − available_limit`), "Disponível" (persisted `available_limit`) and "Total da fatura em vista" (selected bill's `total_amount`).
2. WHEN a user views the card page THEN system SHALL display an invoice selector in the same visual style as the month selector of the expenses UI, listing the card's bills ordered by period (past, current and future pre-created), defaulting to the current cycle's bill.
3. WHEN an invoice is selected THEN system SHALL display: bill details (period label MM/YYYY, closing date, due date, status, total) and the invoice's expenses (description, value in BRL, date, category with color, tag chips, installment indicator "N/M"), ordered by date.
4. WHEN the selected invoice is CLOSED (unpaid) THEN system SHALL display the action "Marcar fatura como paga" which prompts for the account (active accounts of the workspace), confirms the amount displayed (= bill total), and executes the bill payment (CCV2-03).
5. WHEN the selected invoice is OPEN THEN the pay action SHALL NOT be available; a hint SHALL display "Aguardando fechamento em {closing_date}".
6. WHEN the selected invoice is PAID THEN system SHALL display payment info (`paid_at`, account) and the "Desfazer pagamento" action (existing undo).
7. WHEN the selected invoice has no expenses THEN system SHALL show an empty state "Nenhuma despesa nesta fatura".
8. WHEN a user clicks "Nova despesa neste cartão" THEN system SHALL redirect to the expense form pre-filled (CCV2-01 AC9).
9. WHEN a viewer opens the card page THEN the page SHALL be read-only (no pay/new-expense actions); pay attempts return 403 (existing policy).
10. WHEN the card is archived THEN the page SHALL remain accessible in read-only mode with "(Arquivado)" suffix (existing behavior preserved).

**Independent Test**: Open card → see 4 limit cards → select a future bill in the selector (empty, R$ 0,00) → select the current open bill (expenses listed, no pay button, hint with closing date) → after closing, pay action with account selection → bill paid, expenses marked, limit restored.

---

### P1: Despesas de Cartão na Listagem Geral ⭐ MVP

**User Story**: As a workspace member, I want card expenses visible in the general expenses list with card and bill filters so that I can find and filter them without visiting each card.

**Why P1**: O vínculo com a fatura existe para poder filtrar em listagens — parte central da visão do usuário para o refactor.

**Acceptance Criteria**:

1. WHEN the expenses datatable renders THEN card expenses SHALL appear with a "Cartão" column showing the card name (empty for account expenses).
2. WHEN the user filters by card THEN the list SHALL show only that card's expenses.
3. WHEN the user filters by bill THEN the list SHALL show only that bill's expenses (bill options labeled "Cartão — MM/YYYY").
4. WHEN the bill filter is active THEN the list SHALL show all expenses of that bill regardless of the selected month (bill filter overrides month scoping, since a bill cycle crosses months).
5. WHEN a card expense row renders THEN the pay/unpay buttons SHALL be hidden (CCV2-02) and the status column SHALL reflect the linked bill: "Na fatura (Aberta)", "Na fatura (Fechada)" or "Paga".
6. WHEN card/bill filters are applied THEN they SHALL follow the existing DataTable filter contract (D-52–D-56) and session persistence (D-71/D-72).

**Independent Test**: Create expenses on 2 cards + account expenses → filter by card A → only card A's expenses → filter by a specific bill → all expenses of that bill (even across month boundary) → clear → all visible with the Cartão column filled for card expenses.

---

## Edge Cases

- WHEN paying a bill with total 0 (no expenses) THEN system SHALL reject with "Esta fatura não possui despesas".
- WHEN the buffer maintenance job generates an instance whose target bill is PAID (defensive — shouldn't happen given creation validation) THEN system SHALL skip that occurrence and log a warning.
- WHEN a card expense/occurrence is dated before the card's creation (past period without bill) THEN `findOrCreateBill` SHALL create the past-period bill; the on-demand closing marks it Closed (past closing date).
- WHEN a recurrence's `buffer_ahead` exceeds the 13-month bill horizon THEN `findOrCreateBill` SHALL create the missing future bills during buffer generation.
- WHEN the same bill period is targeted concurrently (race) THEN the unique constraint `(credit_card_id, period_year, period_month)` SHALL prevent duplicates.
- WHEN a card is archived with pre-created future bills THEN those bills remain (historical integrity); pre-creation stops; recurrence buffer skips the card's recurrences.
- WHEN the card of a recurrence is archived and later restored THEN buffer generation SHALL resume from the current date (no retroactive backfill of the paused period — mirrors D-47).
- WHEN a card expense is edited with a date change to another period THEN re-bucketing SHALL move it to the correct bill and recalculate both bills' totals (existing).
- WHEN edit/delete targets an expense on a PAID bill THEN system SHALL reject with the existing immutability message.
- WHEN the payment transaction (created by bill payment) is deleted from the transactions list THEN system SHALL prevent with "Esta transação foi gerada pelo pagamento de uma fatura. Use 'Desfazer pagamento' na fatura." (existing).
- WHEN a card recurrence's retroactive occurrence falls on a CLOSED unpaid bill THEN creation SHALL be allowed; bill total recalculated; paid when the bill is paid.
- WHEN an existing account-based recurrence exists (pre-migration data) THEN it SHALL continue working unchanged (`account_id` NOT NULL rows remain valid after the nullable migration).
- WHEN the daily jobs (CloseBills, PreCreateBills, ProcessRecurrences) run on the same day a card is created THEN no duplicate bills SHALL be created (idempotent creation + unique constraints).
- WHEN installments=1 is submitted without total_value THEN system SHALL accept (single purchase needs only value).
- WHEN the user attempts to pay an OPEN bill from the card UI THEN system SHALL reject (CCV2-03 AC4) — no early payment in v2.
- WHEN closing_day/due_day is 31 and the month has fewer days THEN the effective dates SHALL clamp to the last day of the month (existing).

---

## Requirement Traceability

| ID       | Story                                        | Phase    | Status |
| -------- | -------------------------------------------- | -------- | ------ |
| CCV2-01  | P1: Formulário Unificado de Despesas         | Specify  | Done   |
| CCV2-02  | P1: Despesa de Cartão Paga Somente via Fatura| Specify  | Done   |
| CCV2-03  | P1: Pagamento de Fatura Marca as Despesas    | Specify  | Done   |
| CCV2-04  | P1: Faturas Pré-criadas                      | Specify  | Done   |
| CCV2-05  | P1: Recorrências em Cartão                   | Specify  | Done   |
| CCV2-06  | P1: UI do Cartão como Hub                    | Specify  | Done   |
| CCV2-07  | P1: Despesas de Cartão na Listagem Geral     | Specify  | Done   |

**Coverage:** 7 requirements, 7 mapped to stories, 0 unmapped

**ID format:** `CCV2-[NUMBER]`

**Status values:** Pending → In Design → In Tasks → Implementing → Verified

---

## Success Criteria

- [ ] Um único formulário cria despesas em conta e em cartão (avulsas, parceladas e recorrentes)
- [ ] Nenhum caminho manual de pagamento para despesa de cartão (backend guardado; botão oculto)
- [ ] Pagar fatura marca todas as despesas vinculadas como pagas; desfazer reverte
- [ ] Cartão recém-criado possui 13 faturas; horizonte mantido pelo job diário; faturas vazias fecham uniformemente
- [ ] Recorrência de cartão materializa buffer em faturas; criação/edição bloqueada por colisão com fatura paga
- [ ] UI do cartão: 4 cards de limite + seletor de faturas (estilo month-picker) + despesas scopadas + pagamento inline
- [ ] Listagem geral filtra por cartão e por fatura (filtro de fatura sobrepõe o scoping de mês)
- [ ] Comportamentos preservados: parcelas (resto na última), escopos de edição/exclusão, re-bucketing, `available_limit`, categoria de sistema, imutabilidade de fatura paga
- [ ] Gates verdes: `composer quality`, `npm run quality`, suite PHPUnit completa (testes de CardExpenses reescritos para o fluxo unificado) e Cypress E2E das jornadas de cartão (gap atual: não existe nenhum)
- [ ] Zero quebra para dados existentes (transações de cartão, faturas lazy já criadas, recorrências de conta)
