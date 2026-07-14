# Cartões de Crédito Specification

## Problem Statement

Usuários que usam cartão de crédito precisam cadastrar seus cartões no Fin para depois registrar despesas de crédito (individuais e parceladas) e pagar a fatura. Sem o cadastro de cartões, não é possível organizar compras por cartão, saber o limite disponível, ou programar o pagamento da fatura. Hoje o Fin só suporta despesas em débito (lançadas diretamente em contas).

## Goals

- [ ] CRUD completo de cartões de crédito dentro de um workspace
- [ ] Cadastro de dia de fechamento e dia de vencimento da fatura por cartão
- [ ] Cadastro do limite total do cartão (informativo, base para cálculo de disponível)
- [ ] Coluna `available_limit` persistida (redundância) — inicializada igual a `credit_limit` na criação, recalculada a cada nova despesa de cartão (CCXP-01)
- [ ] Apenas admins e editores podem criar/editar cartões; viewers podem visualizar

## Out of Scope

| Feature                          | Reason                                                |
| -------------------------------- | ----------------------------------------------------- |
| Vínculo com conta bancária       | Definido em CCXP-01 (pagamento de fatura debita conta)|
| Despesas de cartão               | Feature CCXP-01 — compras individuais e parceladas   |
| Pagamento de fatura              | Feature CCXP-01 — débito automático da conta          |
| Current balance / fatura atual   | Feature CCXP-01 — calculado a partir das despesas     |
| Tipo de cartão (físico/virtual)  | Feature simples — cartão é genérico                   |
| Multi-moeda                     | Fora do escopo do projeto — apenas BRL                |

---

## User Stories

### P1: CRUD de Cartões de Crédito ⭐ MVP

**User Story**: As a workspace member, I want to create, view, edit, and delete credit cards in my workspace so that I can register my credit cards with their limit, closing day, and due day.

**Why P1**: Sem cartões cadastrados, não há onde registrar despesas de cartão de crédito (CCXP-01). É a fundação para todo o fluxo de crédito.

**Acceptance Criteria**:

1. WHEN a workspace member (admin or editor) navigates to the credit cards index page THEN the system SHALL display all cards of the workspace in a list with name, credit limit, closing day, and due day
2. WHEN a viewer accesses the credit cards index page THEN the system SHALL display the list but SHALL NOT show create/edit/delete actions
3. WHEN a user clicks "Novo Cartão" and fills in name, credit limit, closing day, and due day THEN the system SHALL create the card and redirect to the index page
4. WHEN a user edits a credit card and updates any field THEN the system SHALL save the changes and redirect to the index page
5. WHEN an admin deletes a credit card THEN the system SHALL soft-delete the card and remove it from the index list
6. WHEN a viewer attempts to create, edit, or delete a credit card THEN the system SHALL deny the action with 403

**Independent Test**: Can demo by navigating to /w/{workspace}/cards, creating a card with name "Nubank Mastercard", limite R$ 10000, fechamento dia 1, vencimento dia 10, and seeing it in the list.

---

### P2: Limite Disponível

**User Story**: As a workspace member, I want to see the available credit limit for each card so that I know how much credit I still have.

**Why P2**: Important for financial visibility. In CARD-01, `available_limit` is a persisted column initialized to `credit_limit` on creation. CCXP-01 will trigger recalculation when card expenses are created/updated/deleted.

**Acceptance Criteria**:

1. WHEN a user creates a credit card THEN the system SHALL set `available_limit = credit_limit` (persisted column)
2. WHEN a user views the credit cards list THEN the system SHALL display `available_limit` from the persisted column (no on-the-fly calculation)
3. WHEN a user updates `credit_limit` via edit THEN the system SHALL recalculate `available_limit = credit_limit - sum of open card expenses` (in CARD-01, with no expenses, equals `credit_limit`)
4. WHEN CCXP-01 creates/updates/deletes a card expense THEN the CreditCardService SHALL recalculate and persist `available_limit`

**Independent Test**: Can demo by creating a card with limit R$ 5000 and verifying the persisted column `available_limit` equals 5000 in the DB and API response.

---

## Edge Cases

- WHEN closing_day or due_day is 29, 30, or 31 and the month doesn't have that day THEN the system SHALL accept the value as-is (validação faz apenas range 1-31; lógica de overflow de mês fica para CCXP-01 ao calcular faturas)
- WHEN a user tries to set closing_day or due_day to a value outside 1-31 THEN the system SHALL reject with validation error
- WHEN a user updates credit_limit THEN the system SHALL recalculate available_limit from the new credit_limit minus current open expenses (in CARD-01, available_limit = credit_limit)
- WHEN a user tries to set credit_limit to a negative value THEN the system SHALL reject with validation error
- WHEN a user tries to create a card in a workspace they're not a member of THEN the system SHALL deny with 403
- WHEN a deleted card is referenced by future card expenses THEN the system SHALL prevent deletion or cascade-soft-delete (decided in CCXP-01)

---

## Requirement Traceability

| Requirement ID | Story                  | Phase  | Status  |
| --------------- | ---------------------- | ------ | ------- |
| CARD-01         | P1: CRUD de Cartões    | Design | Pending |
| CARD-02         | P1: CRUD de Cartões    | Design | Pending |
| CARD-03         | P1: CRUD de Cartões    | Design | Pending |
| CARD-04         | P1: CRUD de Cartões    | Design | Pending |
| CARD-05         | P1: CRUD de Cartões    | Design | Pending |
| CARD-06         | P1: CRUD de Cartões    | Design | Pending |
| CARD-07         | P2: Limite Disponível  | Design | Pending |
| CARD-08         | P2: Limite Disponível  | Design | Pending |
| CARD-09         | P2: Limite Disponível  | Design | Pending |
| CARD-10         | P2: Limite Disponível  | Design | Pending |

**Coverage:** 10 total, 0 mapped to tasks, 10 unmapped ⚠️

**ID format:** `CARD-[NUMBER]`

**Status values:** Pending → In Design → In Tasks → Implementing → Verified

**Coverage:** 9 total, 0 mapped to tasks, 9 unmapped ⚠️

---

## Success Criteria

- [ ] Usuário pode criar, visualizar, editar e excluir cartões de crédito em menos de 1 minuto por operação
- [ ] Validação impede dias de fechamento/vencimento inválidos (fora de 1-31)
- [ ] Viewers não conseguem criar/editar/excluir cartões (403)
- [ ] `available_limit` é coluna persistida, inicializada igual a `credit_limit` na criação, recalculada a cada mudança de `credit_limit` (e a cada despesa em CCXP-01)
- [ ] Cartões excluídos são soft-deleted e não aparecem na listagem