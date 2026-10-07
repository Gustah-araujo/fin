# Cartões de Crédito V2 — Context

**Gathered:** 2026-09-26
**Spec:** `.specs/features/cartoes-v2/spec.md`
**Status:** Design approved

---

## Feature Boundary

Refactor completo da feature de cartões de crédito: despesas de cartão viram despesas tradicionais criadas no form unificado (com cartão selecionado); pagamento acontece exclusivamente via fatura (que marca todas as despesas vinculadas como pagas); faturas são pré-cadastradas 13 meses à frente por job; recorrências em cartão (sempre 1x) materializam buffer de despesas futuras nas faturas; UI do cartão vira o hub único (criar cartões, registrar despesa via redirect, ver despesas por fatura, cards de limite, pagar fatura inline).

---

## Implementation Decisions

### Pagamento de fatura

- Mantém o ciclo **Open→Closed→Paid**: só fatura fechada é pagável — usuário rejeitou pagar antecipado a fatura aberta
- Pagar a fatura marca TODAS as despesas vinculadas como pagas; desfazer reverte

### Formulário unificado

- Seletor "forma de pagamento" (Conta/Cartão); cartão revela parcelas 1–48
- Fluxo `CardExpenses` dedicado removido por completo (controller, service, páginas, rotas)
- Redirect do cartão → form de despesas com o cartão pré-preenchido

### Faturas pré-criadas

- **Híbrido**: job diário pré-cria faturas 13 meses à frente + buffer usa `findOrCreateBill` idempotente como garantia
- Horizonte **13 meses fixo** (ciclo atual + 12) — usuário rejeitou atrelar ao buffer e configurável por workspace

### Recorrência em cartão

- **Sempre 1x** — sem combinação recorrência × parcelas
- Criação **bloqueada** se qualquer ocorrência retroativa (start_date→hoje) cair em fatura paga — usuário rejeitou "pular ciclos pagos" e "anexar mesmo assim"

### Listagem geral

- Despesas de cartão aparecem na listagem geral com coluna "Cartão" + filtros por cartão e por fatura; botão Pagar oculto para elas

### UI do cartão

- Cards de limite **globais** (Limite total / Consumido / Disponível) + Total da fatura em vista — usuário rejeitou "um card por fatura"
- Despesas **sempre scopadas por fatura**, com seletor de faturas no **mesmo estilo do seletor de meses da UI de despesas**
- O botão "marcar fatura como paga" refere-se à **fatura sendo vista** (não à "última fechada não paga")

---

## Agent's Discretion

- Desfazer pagamento reverte `paid_at` das despesas (comportamento espelho)
- Edição/exclusão de despesa em fatura paga continua bloqueada (comportamento atual)
- Recorrências de cartão herdam os escopos de edição/exclusão (D-44/D-45) e a lista única de `/recurrences`
- Parcelamento continua exclusivo de cartão (não vira opção para débito em conta)
- `Bills/Show` mantida como detalhe/histórico de faturas fechadas e pagas
- Fallback on-demand de fechamento incluso no refactor (CCXP-06 AC2 nunca implementada)
- Pagamento de fatura vazia (total 0) bloqueado

---

## Specific References

- "usar mesmo estilo de seletor de meses da UI de despesas para o seletor de faturas aqui" — referência explícita do usuário ao month-picker da listagem de despesas (`Transactions/Index`)

---

## Design Decisions (confirmed)

### Service Architecture
- `CardExpenseService` fully merged into `TransactionService`
- Card-specific logic in prefixed methods: `createCardExpense()`, `createCardInstallment()`, `updateCardSingle()`, `updateCardGroup()`, `deleteCardSingle()`, `deleteCardGroup()`
- Shared helpers extracted: `resolveBill()`, `syncTags()`, `ensureBillNotPaid()`
- `pay()`/`unpay()` reject transactions with `credit_card_id` (guard D-28)

### Recurrence
- `account_id` becomes nullable; `credit_card_id` added (nullable)
- Mutual exclusivity via FormRequest validation
- `RecurrenceService` gets `BillService` + `CreditCardService` as new constructor deps
- Retroactive occurrences materialized on closed (unpaid) bills — per spec
- Paid bill collision blocks creation (D-79)

### Bill Lifecycle
- 13 bills pre-created synchronously on card creation (no latency concern)
- `CloseBillsJob` closes ALL bills with closing_date < today (including empty)
- `PreCreateBillsJob` (new) maintains 13-month horizon daily
- Fallback on-demand closing in `Cards/Show` render

### Frontend Form
- Sub-components per expense type: `AccountFields`, `CardExpenseFields`, `RecurrenceFields`
- `PaymentMethodSelector` toggle (Conta ↔ Cartão)
- Card recurrence locks installments to 1

### Decommission
- Complete atomic removal of CardExpenses silo (project not in production)
- `CardExpenseController`, `CardExpenseService`, `StoreCardExpenseRequest`, `UpdateCardExpenseRequest` → deleted
- `CardExpenses/Create.tsx`, `CardExpenses/Edit.tsx` → deleted
- Routes `cards/{card}/expenses/*` → removed (404)
- CardExpenses tests rewritten for unified flow

---

## Deferred Ideas

- Pagamento antecipado de fatura aberta — decidido contra por agora; capturado como possível evolução futura
- Pagamento parcial de fatura — mantido out of scope (herdado do CCXP-01)
