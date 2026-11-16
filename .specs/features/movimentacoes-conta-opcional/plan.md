# Plano: Conta Opcional nas Movimentações + Cadastro como Já Pago/Recebido

Issue: [Feature/Refactor] Tornar Vínculo de Conta Opcional nas Movimentações e Permitir Cadastro como Já Pago/Recebido

## Descobertas (exploração)

- `account_id` **já é nullable** no schema (`2026_07_14_000001_create_transactions_table.php`). A exigência está nas validações:
  - `StoreIncomeRequest` linha 29: `account_id` **required** (+ checks de workspace/trashed no `withValidator`)
  - `UpdateIncomeRequest`: `sometimes|required`
  - `TransactionValidator::validateSourceMutualExclusion` (linha 42): despesa exige account XOR cartão
- Status "pago" = `paid_at` (nullable timestamp), sem enum. Hoje nasce sempre `null`; só é setado via `POST .../pay`.
- Saldo centralizado em `AccountService::recalculateBalance` (soma transações pagas da conta). Chamadores: `TransactionService` (pay/unpay/archive/recalculateAfterUpdate), `RecurrenceService` (updateThisAndFuture/deleteThisAndFuture), `BillService` (payBill/undoPayment — sempre com conta, intocável).
- `TransactionService::create()` linha ~44: `Account::where('uuid',...)->firstOrFail()` → quebra com null.
- `TransactionService::pay()` (linha ~172): rejeita conta trashed; recalcularia com `null` → TypeError.
- IA: `ValidateTransactionTool` schema `account_id` required; `ConfirmTransactionTool` delega ao `TransactionService::create`.
- Frontend: `Incomes/Create.tsx` e `Edit.tsx` (select sem opção vazia), `Transactions/Create.tsx` (payment_method account/card), `Transactions/Edit.tsx`; listagens já exibem `—` em despesas; receitas exibem `is_paid`.

## Payload

- Frontend envia `is_paid: boolean` nos formulários de criação (Receita e Despesa).
- Backend: `StoreIncomeRequest`/`StoreTransactionRequest` aceitam `is_paid` boolean; `TransactionService::create()` define `paid_at = now()` quando `is_paid` e recalcula saldo **somente se `account_id` preenchido**.

## Unidades de trabalho (TDD)

### U1 — Backend (validações + service)
1. Testes feature ANTES da implementação:
   - criar despesa sem conta/cartão (200, `paid_at` null, sem exceção)
   - criar receita sem conta
   - criar despesa/receita com `is_paid=true` COM conta → `paid_at` setado + saldo refletido
   - criar com `is_paid=true` SEM conta → `paid_at` setado + saldo de todas as contas inalterado
   - editar movimentação para/vindo de conta null; pay/unpay/delete sem conta (sem exceção)
   - isolamento de workspace
2. Implementação:
   - `StoreIncomeRequest`: `account_id` nullable; checks de workspace/trashed só quando preenchido
   - `UpdateIncomeRequest`: `nullable` (em vez de `sometimes|required`)
   - `StoreTransactionRequest`: aceitar `is_paid`; exigir conta apenas para parcelados (`createAccountInstallment`)
   - `UpdateTransactionRequest`: `account_id` `nullable` (em vez de `sometimes|required`)
   - `TransactionValidator::validateSourceMutualExclusion`: permitir account e cartão ambos null (apenas single, não parcelado); manter exclusão mútua quando preenchidos
   - `TransactionService`: `create()` tolera account null e honra `is_paid`; `update()` recalcula apenas contas não-null; `pay()/unpay()/archive()` guardam recálculo com account null
   - `RecurrenceService::accountsToRecalculate()/recalculateAccounts()/deleteThisAndFuture`: skip contas null
3. Gate: `php artisan test` + `composer quality`

### U2 — Assistente IA
- `ValidateTransactionTool`: schema `account_id` opcional; handle tolera null (sem lookup quando ausente)
- Testes: confirmar transação sem conta via IA persiste sem exceção
- Gate: `php artisan test --filter=Fin` + `composer quality`

### U3 — Frontend
- `Incomes/Create.tsx` / `Edit.tsx`: opção "Sem conta" no select; checkbox/switch "Já foi recebida"; payload `is_paid`
- `Transactions/Create.tsx`: opção "Sem conta" no select (modo conta); checkbox "Já foi paga"; payload `is_paid`
- `Transactions/Edit.tsx`: opção "Sem conta"
- Listagens: coluna conta exibe "Sem conta" (receitas) / já exibem `—` (despesas)
- Gate: `npm run quality` + `npx tsc --noEmit` (se configurado)

### U4 — E2E Cypress
- `cypress/e2e/transactions/no-account.cy.js`: despesa sem conta + "Já foi paga" → status pago na listagem, conta vazia, toast
- `cypress/e2e/incomes-paid.cy.js`: receita com conta + "Já foi recebida" → status, saldo atualizado, toast

## DoD
- `composer quality`, `npm run quality`, `php artisan test` verdes
- Testes E2E escritos (execução depende de servidor local)
- Toasts (Sonner) nas mutações; UI pt-BR; código em inglês
