# Plano Bugfix: Remover Obrigatoriedade de Conta/Cartão em Todas as Modalidades

Issue original: [Feature/Refactor] Tornar Vínculo de Conta Opcional nas Movimentações (implementada via plano `plan.md` — avulsas já aceitam sem conta).
Comentário adicional: [Bugfix] — a validação `"Uma transação deve ter uma conta ou cartão selecionado."` ainda bloqueia **Recorrência** e **Parcelamento** sem conta/cartão.

## Estado atual (exploração)

- `TransactionValidator::validateSourceMutualExclusion` (linhas 57–63): residual — exige conta OU cartão quando `is_recurring=true` OU `installments > 1`. É a **única** ocorrência da mensagem bloqueante no código.
- `RecurrenceService::resolveRecurrenceTarget` (linha 1222) → `resolveAccountId(Workspace, string $uuid)` (linha 1051, `strict_types`, `firstOrFail()`) → **TypeError/404** se `account_id` for null sem cartão.
- `TransactionService::createAccountInstallment` (linha ~482): `Account::where('uuid', $data['account_id'])->firstOrFail()` → quebra com null.
- `RecurrenceService::resolveForeignKeyIds` (linha 813) usa `isset($data['account_id'])` → passagem de `null` (limpar conta) é silenciosamente ignorada; `applySharedFields` (linha 1010) idem → editar recorrência para "Sem conta" não funciona.
- `UpdateRecurrenceRequest.php:168`: mensagem morta `'A conta é obrigatória.'` (regra é `sometimes|nullable`).
- `Recurrences/Edit.tsx` (linhas 111–125): select de conta **sem** opção vazia "Sem conta".
- Testes que fixam o comportamento residual: `tests/Feature/Transactions/OptionalAccountTransactionTest.php:285` (`test_recurring_expense_still_requires_account_or_card`) e `:301` (`test_installment_expense_still_requires_account_or_card`).
- Já null-safe: `TransactionService::create/update/pay/unpay/archive`, `RecurrenceService` buffer/geração/exclusão (cópia de `account_id` nullable), saldo via `AccountService::recalculateBalance`.

## Unidades de trabalho (TDD)

### U1 — Backend
1. **Testes primeiro** (reescrever os 2 testes que fixam o comportamento residual + novos):
   - Recorrência (despesa) sem conta/cartão: cria `Recurrence` com `account_id = null`, gera buffer com `account_id = null`, sem alterar saldos.
   - Receita recorrente sem conta (via `incomes.store`): sucesso.
   - Parcelamento sem conta/cartão: gera N transações com `account_id = null`; pagar uma delas sem exceção.
   - Editar recorrência para "Sem conta" (`account_id = null`) limpa o vínculo (current + future).
2. **Implementação**:
   - `TransactionValidator`: remover bloco residual (linhas 57–63); manter exclusão mútua conta/cartão.
   - `RecurrenceService::resolveAccountId`: aceitar `?string`, retornar `null` quando vazio (espelhar `TransactionService::resolveAccountId`).
   - `RecurrenceService::resolveRecurrenceTarget`: retornar `[null, null]` quando sem cartão e sem conta.
   - `RecurrenceService::resolveForeignKeyIds` + `applySharedFields` + `accountsToRecalculate`: tratar `array_key_exists` com null (limpar vínculo) sem quebrar recálculo de saldos.
   - `TransactionService::createAccountInstallment`: conta nullable.
   - `UpdateRecurrenceRequest:168`: remover mensagem morta.
   - Auditar `create()`/`createWithFirstInstance()` (linhas 43/91) — tornar null-safe também (usados em fluxo legado).
3. Gate: `php artisan test` + `composer quality`.

### U2 — Frontend
1. `Recurrences/Edit.tsx`: opção "Sem conta" (value `''`) no select de conta; payload envia `account_id: data.account_id || null`.
2. Auditar `Transactions/Create.tsx` / `Incomes/Create.tsx`: garantir que Recorrência/Parcelamento com "Sem conta" enviam `account_id: null` sem bloqueio client-side (select já tem opção vazia; confirmar no payload).
3. Gate: `npm run quality` + `npx tsc --noEmit` (se configurado).

### U3 — Cypress E2E
1. `cypress/e2e/transactions/no-account.cy.js`: novos cenários — Recorrência com "Sem conta" salva com sucesso (toast + sem erro de validação); Parcelamento com "Sem conta" salva com sucesso.
2. Gate: execução depende de servidor local; sintaxe validada.

## DoD
- `composer quality`, `npm run quality`, `php artisan test` verdes.
- Nenhuma ocorrência de "deve ter uma conta ou cartão selecionado" no código.
- UI pt-BR, código em inglês, toasts Sonner nas mutações.
