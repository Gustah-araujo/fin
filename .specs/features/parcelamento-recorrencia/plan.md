# Plano — Separação de Recorrências e Parcelamento no Cadastro de Movimentações

Issue: [Refactor/Feature] Separação de Recorrências e Parcelamento no Cadastro de Movimentações

## Estado atual (auditoria)

**Já existe e permanece:**
- `Recurrence` com `until_date` (Data Final opcional), `buffer_ahead`, geração via buffer
  (`RecurrenceService::createWithBuffer`, `computeBufferDates`, `generateBufferInstances` +
  `ProcessRecurrencesJob` diário) — todos já respeitam `until_date` (`exceedsUntilDate`).
- Parcelamento de cartão (`TransactionService::createCardInstallment`) com resto na última parcela.
- UI de receitas: apenas Avulsa/Recorrente (sem parcelamento).
- Bloqueio recorrência + parcelamento no cartão (`TransactionValidator`).

**Gaps a implementar:**
1. Despesa em **conta/carteira** não tem caminho de parcelamento no `TransactionController::store`.
2. Sufixo `({N}/{TOTAL})` na descrição das parcelas — inexistente (cartão e conta).
3. **Dia do Mês** para parcelas (`installment_day`) — inexistente; datas derivam do dia da compra.
4. UI do formulário de despesa: toggle "É recorrente?" independente do campo "Parcelas" — não é
   uma seleção explícita de modalidade (Avulsa / Recorrência / Parcelamento).
5. Backend não rejeita `is_recurring` + `installments` em despesa de **conta** (só no cartão).
6. `StoreIncomeRequest` não rejeita payload de parcelamento.
7. `updateCardGroup` perde o sufixo ao editar descrição do grupo.

**Sem mudança de schema:** `installment_day` é usado apenas para calcular as datas no momento da
criação; cada `Transaction` guarda a própria `date`. `installment_number`, `installments_total`,
`installment_group_id` já existem para contas e cartões.

## Tarefas

### T1 — Backend: validações
Arquivo: `app/Services/TransactionValidator.php`, `app/Http/Requests/StoreTransactionRequest.php`,
`app/Http/Requests/StoreIncomeRequest.php`.

- `StoreTransactionRequest`: adicionar regra `installment_day` (nullable, integer, min:1, max:31).
- `TransactionValidator::validateRecurrenceInstallments` (generalizar a existente): erro em
  `installments` se `is_recurring` e `installments > 1` (conta **ou** cartão): "Recorrências não
  podem ser parceladas. Escolha entre Recorrência ou Parcelamento.".
- Novo: se `is_recurring`, rejeitar `total_value`/`installment_day` preenchidos.
- Novo: se `installments > 1`, `installment_day` deve estar entre 1–31.
- `StoreIncomeRequest`: rejeitar `installments > 1` (ou `total_value`/`installment_day`
  preenchidos) com erro em `installments`: "Receitas não podem ser parceladas.".

### T2 — Backend: geração de parcelas (conta + cartão) com sufixo e dia do mês
Arquivo: `app/Services/TransactionService.php`, `app/Http/Controllers/TransactionController.php`.

- Helper privado `applyInstallmentSuffix(string $base, int $number, int $total): string`
  → `"{$base} ({$number}/{$total})"`, truncando a base para caber em 255 (mb).
- `createAccountInstallment(Workspace, User, array $data): string` — análogo ao card:
  - grupo `installment_group_id` (uuid), `account_id` resolvido no workspace,
    `credit_card_id`/`credit_card_bill_id` null.
  - valor por parcela = `round(total/count, 2)`, resto na **última** (mesma regra do cartão).
  - datas: mês da `$data['date']` + (i−1) meses; dia = `min(installment_day, daysInMonth)`
    (clamp fev/30/31); sem `installment_day` → usa o dia da data informada (compat).
  - descrição com sufixo `(i/count)`; tags sincronizadas; `paid_at` null.
- `createCardInstallment`: aplicar sufixo `(i/count)` e suportar `installment_day`
  (mesma matemática de datas; fallback para dia da compra).
- `updateCardGroup`: ao atualizar descrição do grupo, re-aplicar sufixo por linha
  (strip `/\s\(\d+\/\d+\)$/` + append `({n}/{total})`).
- `TransactionController::store`: no caminho de **conta**, adicionar branch
  `installments > 1` → `createAccountInstallment` + Toast "Despesa parcelada criada com sucesso."
- Bloquear recorrência + parcelamento também em conta (T1 já cobre via validação; remover
  a exceção duplicada em `RecurrenceService::resolveRecurrenceTarget` fica redundante mas inofensiva —
  manter como defesa).

### T3 — Backend: testes feature
- `tests/Feature/Transactions/AccountInstallmentTest.php` (novo):
  cria despesa parcelada em conta — quantidade exata, soma = total, dia do mês com clamp
  (ex.: dia 31 → 28/02), sufixo `(1/3)`, `installment_group_id` igual, `paid_at` null,
  `workspace_id` escopado, conta correta.
- Estender `tests/Feature/Cards/CardUnifiedExpenseCreationTest.php` (ou novo arquivo):
  sufixo `(1/12)` no cartão, `installment_day` altera datas, clamp de fevereiro.
- `tests/Feature/Recurrences/RecurrenceUntilDateBufferTest.php` (novo):
  - recorrência criada com `until_date` dentro do alcance do buffer → nº de instâncias ≤ limite;
  - `ProcessRecurrencesJob`/`generateBufferInstances` não gera além de `until_date`
    (deletar instâncias futuras + rodar job → nada além de `until_date`).
- `tests/Feature/Incomes/IncomeInstallmentRejectionTest.php` (novo):
  POST `incomes.store` com `installments=3` → erro de validação; com `total_value`/`installment_day` → erro.
- Exclusividade: POST `transactions.store` em conta com `is_recurring=1` + `installments=3` → erro.
- Atualizar `CardExpenseEditingTest` (sufixo re-aplicado no group edit).

### T4 — Frontend: formulário de despesa
Arquivo: `resources/js/Pages/Transactions/Create.tsx`, `resources/js/Components/Transactions/CardExpenseFields.tsx`
(+ novo `resources/js/Components/Transactions/InstallmentFields.tsx`).

- Substituir o Switch "É recorrente?" por radio group **Modalidade**:
  `Avulsa | Recorrência | Parcelamento` (state `modality: 'single' | 'recurrence' | 'installment'`).
- `modality === 'recurrence'` → renderiza os campos atuais de frequência/frequency_day/
  has_until_date/buffer_ahead (payload envia `is_recurring=true` + esses campos).
- `modality === 'installment'` → renderiza `InstallmentFields`: Qtd. parcelas (2–48),
  Dia do mês (1–31), Valor total, preview reativo:
  "Serão lançadas N parcelas de R$ X" + `Exemplo: "{descrição} (1/N)"`.
  Funciona para conta **e** cartão (bloco compartilhado, fora do CardExpenseFields).
- `CardExpenseFields`: manter só seleção de cartão (remover parcelas/total daqui).
- `ValueField` oculto quando `modality === 'installment'`.
- `handleSubmit`: payload por modalidade; no parcelamento, `value` transformado
  (`total/installments`) e `installment_day` enviado quando preenchido.
- Labels: "Data de início" quando recorrência; toast de sucesso vem do backend (já existe).

### T5 — Frontend: E2E Cypress
Arquivos (padrão `.cy.js` do projeto, em `cypress/e2e/`):
- `transactions/installments.cy.js`: despesa parcelada em conta — preview atualiza dinamicamente,
  submit, listagem mostra `(1/3)`; toasts do Sonner.
- Estender `recurring-expenses.cy.js` ou novo: recorrência com data final.
- `incomes.cy.js`: confirmar ausência de opção de parcelamento + fluxo de recorrência funcionando.
- Estender `cards/credit-card-hub.cy.js` ou novo: parcelada em cartão com sufixo na listagem.

## Fora de escopo
- Migrações/schema (nada novo é persistido).
- Edição de modalidade de uma transação existente.
- Receitas parceladas (explicitamente proibidas).
