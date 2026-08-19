# Receitas — Specification

**Story ID:** INCM-01
**Phase:** P1 — MVP Core
**Parent Spec:** `.specs/features/workspace-financeiro/spec.md`
**Dependencies:** DEBT-01 (Despesas em Débito), CAT-01 (Categorias e Tags), ACCT-01 (Contas Bancárias)

## Problem Statement

Workspaces com contas (ACCT-01), despesas em débito (DEBT-01) e cartão (CCXP-01) funcionando, mas sem receitas o saldo das contas só desce — `AccountService::recalculateBalance()` já soma `paid income` mas nenhuma receita existe para somar. O usuário perde a outra metade do fluxo de caixa: salário, freelance, reembolso. Sem receitas, o dashboard (DASH-01) e insights (INSG-01) não conseguem projetar saldo futuro.

Além do CRUD básico (espelho do DEBT-01 para `type=Income`), o território novo é **recorrência**: o usuário cadastra salário/freelance com frequência semanal ou mensal e o sistema gera automaticamente as instâncias futuras conforme a data chega — sem input manual repetido. A recorrência é fundacional porque salário e alocação são receitas repetitivas, e re-digitá-las todo mês é justamente o trabalho que o app promete eliminar.

A UX é **unificada**: um único formulário de receita com um toggle "É recorrente?" que revela o painel de recorrência (frequência, dia, data final). Ao criar, o sistema cadastra a Recurrence e já gera a primeira Transaction se a data fizer sentido; caso contrário, só a regra, e o job diário gera as instâncias quando as datas chegarem. A edição/exclusão de instâncias geradas por recorrência oferece escopo (Apenas esta / Esta e futuras).

## Goals

- [ ] CRUD **unificado** de receitas: um único formulário com toggle "É recorrente?" que revela painel de recorrência
- [ ] Criação não recorrente: cadastra uma `Transaction` com `type=Income`, `paid_at=null`
- [ ] Criação recorrente: cadastra `Recurrence` + primeira `Transaction` (se `date ≤ today`), ou apenas `Recurrence` (se `date > today`)
- [ ] Toda receita nasce "prevista/não recebida"; confirmação é ação explícita
- [ ] Confirmação de recebimento adiciona o valor ao saldo da conta (`AccountService::recalculateBalance()`)
- [ ] Frequências suportadas: semanal (todo dia X da semana: Dom–Sáb) e mensal (todo dia X do mês: 1–31)
- [ ] Condição de fim: infinita OU com data final (`until_date`) selecionada pelo usuário
- [ ] Geração automática de instâncias via job diário: para cada recorrência com `next_date ≤ today`, criar uma `Transaction` e avançar `next_date`
- [ ] Link entre recorrência e instâncias geradas (`recurrence_id` FK em `transactions`)
- [ ] Edição de instância recorrente oferece escopo: "Apenas esta" / "Esta e futuras"
- [ ] Exclusão de instância recorrente oferece escopo: "Apenas esta" / "Esta e parar futuras"
- [ ] Página dedicada `/w/{workspace}/recurrences` para listar/gerenciar regras (ativas, esgotadas, pausadas)
- [ ] UI distingue visualmente receitas confirmadas de previstas e instâncias recorrentes de avulsas
- [ ] Filtros por categoria, conta, período, status de recebimento, origem recorrente e busca textual (P2)

## Key Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Income transactions model | Reuso `Transaction` table com `type=Income`, `credit_card_id=null`, `account_id` obrigatório | Consistente com D-24 (single transactions table); `paid_at` e `category_id` já existem |
| Account required on income | `account_id` NOT NULL para receitas (validação impede null) | Usuário: "toda receita cai numa conta bancária" |
| CRUD unificado | Um único formulário com toggle "É recorrente?" revelando painel condicional de recorrência | Melhor UX que 2 CRUDs; usuário pensa em "cadastrar receita" não em "_avulsa_ ou _recorrente_" |
| Create flow when toggle ON AND `date ≤ today` | Cria `Recurrence` + cria `Transaction` com `date = recurrence.start_date`, `paid_at = null`, `recurrence_id = X`, e avança `recurrence.next_date` para o próximo ciclo | Usuário: "cadastrando assim a recorrência e já cadastrando a primeira receita (caso a data faça sentido)" |
| Create flow when toggle ON AND `date > today` | Cria apenas `Recurrence` com `next_date = start_date` (a Transaction será gerada pelo job quando a data chegar) | Sem instância "fantasma" no futuro; lista de receitas só mostra o que venceu |
| Create flow when toggle OFF | Cria apenas `Transaction` com `recurrence_id = null` (espelho do DEBT-01) | Receita avulsa padrão |
| Initial `next_date` when instance generated on create | Após gerar a primeira instância no CREATE, `next_date` avança para o próximo ciclo baseado em `frequency` + `frequency_day` | Mantém simetria com o job: o CREATE faz "a primeira iteração do job manualmente" |
| Recurrence model | Novo modelo `Recurrence` (tabela `recurrences`) genérico, não `RecurringIncome` | Extensível a despesas recorrentes futuras; nome neutro |
| Recurrence-Transaction link | Nova coluna `recurrence_id` (uuid nullable FK) em `transactions` | Permite "cancelar futuras" sem deletar passadas; espelha o pattern `installment_group_id` do CCXP-01 |
| Frequency enum | `RecurrenceFrequency` (Weekly, Monthly) em `App\Enums\` | Espelha pattern `BillStatus`, `TransactionType` |
| Weekday encoding | 0=Dom, 1=Seg, ..., 6=Sáb (compatível com PHP `Carbon::DayOfWeek`) | Permite comparação direta com `Carbon::now()->dayOfWeek` |
| Frequency day column | `frequency_day` int not null: 0–6 para Weekly (= dia da semana), 1–31 para Monthly (= dia do mês) | O enum `frequency` determina como interpretar o inteiro |
| Monthly day overflow | Se mês tem menos dias que `frequency_day`, use o último dia do mês (ex: dia 31 em Fevereiro → dia 28/29) | Mesma convenção adotada para `closing_day` no CCXP-01 |
| End condition | `until_date` nullable date: `null` = infinita; data = última ocorrência permitida (inclusive) | Usuário: "ambos, permitir infinita mas também permitir que o usuário selecione a data final" |
| Next due tracking | `next_date` date tracking column on Recurrence; job filtra `WHERE next_date <= today AND (until_date IS NULL OR next_date <= until_date)` | Mais simples e idempotente que recompute-on-run; visível em UI ("próxima em DD/MM") |
| Generated transaction state | `type=Income`, `paid_at=null`, `created_by=recurrence.created_by`, `recurrence_id=<parent>`, demais campos copiados da Recurrence | Per-user confirma depois; espelha DEBT-01 paid pattern |
| Generation job | `Schedule::job(new ProcessRecurrencesJob)->dailyAt('00:00')` em paralelo com `CloseBillsJob` | Mesmo pattern; um job separado mantém single responsabilidade |
| Job idempotência | Cada recorrência processada em `DB::transaction`: cria Transaction, faz `UPDATE recurrences SET next_date = <next_ocurrence> WHERE id = X AND next_date = <current_value>` (optimistic lock); se update afetar 0 linhas, outra worker já pegou, skip | Evita duplicação se o job rodar concorrentemente (rerun manual, overlap) |
| Job resilience | Erro em uma recorrência não aborta o job; loga e continua (mesma resiliência do `CloseBillsJob`) | Mantém uptime do scheduler |
| Job skip rules | Recorrência com `until_date < today` não gera; account arquivada pula geração + log; recurrence com `status != active` ou soft-deleted pula | Resiliente a estados inconsistentes |
| Confirmação de recebimento | Reuso `TransactionService::pay()` / `unpay()` existentes | Já chamam `AccountService::recalculateBalance()`; fórmula já soma `paid income` |
| Category type guard (income) | Receitas só aceitam categorias com `type=Income` OU `type=Both` (inverso da regra DEBT-01) | Espelha DEBT-01; reuso da validação em StoreTransactionRequest/UpdateTransactionRequest |
| Mutual exclusivity (income) | Receita não pode ter `credit_card_id` setado (validação explicita null) | Receitas sempre caem em conta, nunca em cartão |
| Recurrence pause/stop | Pausar = `status = paused` na Recurrence (não soft-delete); transações já geradas NÃO são afetadas (integridade histórica); soft-delete é reservado para fluxos reais de exclusão (ex: "Esta e parar futuras") | Separa semântica de estado operacional de exclusão lógica |
| Edit instance generated by recurrence — scope prompt | Ao editar instância recorrente, prompt "Apenas esta" / "Esta e futuras" | Padrão consistente com edição de parcelas do CCXP-01 |
| Edit scope "Apenas esta" | Atualiza só a Transaction atual; Recurrence e futuras instâncias não mudam | Mirrors CCXP-01 single-installment edit |
| Edit scope "Esta e futuras" | Atualiza a Transaction atual + atualiza a Recurrence pai (description, value, category, account, tags, end date) + atualiza TODAS as Transactions futuras já geradas vinculadas à mesma Recurrence com `date > current.date` | Mantém consistência da " série recorrente" pós-edição; futuras gerações também usam novos valores automaticamente porque a Recurrence foi atualizada |
| Edit scope — what CAN'T be changed via "Esta e futuras" | `frequency`, `frequency_day`, `start_date` da Recurrence só podem ser alterados pela página de gestão da recorrência (`/recurrences/{id}/edit`), NÃO pelo prompt de escopo da instância | Escopo de instância é semanticamente "mudar o valor/descrição da série"; mudar frequência é re-schedule, ação separada |
| Delete instance generated by recurrence — scope prompt | Ao excluir instância recorrente, prompt "Apenas esta" / "Esta e parar futuras" | Alternativa mais simples: apenas "Apenas esta" + parar via página separada. Escolha do usuário: escopo inline na exclusão |
| Delete scope "Apenas esta" | Soft-deleta só a Transaction atual; Recurrence continua gerando futuras instâncias normalmente | Mirrors CCXP-01 edit-delete single installment |
| Delete scope "Esta e parar futuras" | Soft-deleta a Transaction atual + soft-deleta a Recurrence pai (futuras gerações param). Outras instâncias já geradas (anteriores à atual, com `date < current.date`) PERMANECEM visíveis na lista de receitas. Instâncias futuras já geradas (com `date > current.date`) também são soft-deletadas | Parar futuras gerações sem perder histórico |
| Recurrence management page | Página dedicada `/w/{workspace}/recurrences` mostrando regras ativas, esgotadas (`next_date IS NULL`) e pausadas (`status = paused`) em uma única lista. Ações: editar regra completa (todos os campos inclusive frequency/frequency_day/start_date/until_date), "Pausar" (`status = paused`), "Reativar" (`status = active` + recompute next_date) | Permite gerenciar recorrências sem depender de instância na lista de receitas; evita links quebrados para regras pausadas |
| Generated transaction description | `description` da Recurrence é copiada para a Transaction gerada (sem slug automático; usuário pode customizar por instância depois via edit "Apenas esta") | Flexível; tags e category propagam igualmente |
| Manual "Gerar agora" action | Botão na página de gestão da recorrência; força geração imediata da instância atual (só se `next_date <= today`); respeita optimistic lock; viewer negado 403 | Útil para dev/QA e fallback se job atrasar |
| Currency | Apenas BRL (constraint do projeto) | Sem multi-moeda |

## Out of Scope

| Feature | Reason |
|---------|--------|
| Receita em cartão de crédito | Cartão é para despesas; receita sempre entra em conta bancária |
| Recorrência de despesas (DEBT-01 recurring) | DEBT-01 spec explicita Out of Scope; extensão futura reusa o modelo `Recurrence` |
| Transferências entre contas | Fora do escopo v1 (PROJECT.md) |
| Multi-moeda | Apenas BRL (constraint do projeto) |
| Importação de extratos como receita | IMPT-01 cobre importação genérica |
| Receita dividida em múltiplas contas | v1: 1 receita → 1 conta |
| Anexos/comprovantes | Complexidade de storage desnecessária para v1 |
| Receita gerada antes da data (adiantamento) | Job é gatilho; user pode criar outra receita avulsa no futuro diretamente |
| Notificação de "receita chegou" | Fora do escopo; planner/dashboard exibe em futuras fases (DASH-01, PLAN-01) |
| Frequência diária / anual | Usuário pediu apenas semanal (dia da semana) e mensal (dia do mês) |
| Edição retroativa de instâncias anteriores ("Esta e anteriores") | Out of scope v1; só "Esta e futuras" para simplificar matches o pattern CCXP-01 |
| Backfill de ciclos perdidos (job que ficou meses sem rodar) | v1 simplificação: gera só a mais recente e avança `next_date` sem recriar histórico |
| Importar recorrência de template/exportada | Out of scope v1 |

---

## User Stories

### P1: CRUD Unificado de Receitas (Avulsa + Recorrente) ⭐ MVP

**User Story**: As a workspace member, I want to register a single income OR a recurring income from the same form, with a toggle revealing the recurrence panel, so that I don't have to think about which CRUD to use — I just register income and mark recurrence when applicable.

**Why P1**: É o ponto de entrada da feature. Unificação evita confusão mental (avulsa vs recorrente são o mesmo conceito: dinheiro entrando). Salário (recorrente) e reembolso (avulsa) saem do mesmo formulário. Sem isso, a feature não existe.

**Acceptance Criteria**:

1. WHEN a workspace member (admin or editor) opens the income create form at `/w/{workspace}/incomes/create` THEN system SHALL display a unified form with fields: description, value, date (default today), account_id, category_id, tags[], AND a toggle "É recorrente?" initially OFF.

2. WHEN the user keeps the toggle OFF and submits valid data (description, value, date, account_id, category_id, tags[]) THEN system SHALL create ONE `Transaction` with `type=Income`, `paid_at=null`, `credit_card_id=null`, `installment_*=null`, `recurrence_id=null`, `account_id=<selected>`, `category_id=<selected>`, linked to the current workspace, then redirect to `/w/{workspace}/incomes` with the new income visible.

3. WHEN the user toggles ON "É recorrente?" THEN system SHALL reveal the recurrence panel inline with fields: `frequency` (Weekly | Monthly), `frequency_day` (1–7 if Weekly shown as weekday selector Dom–Sáb; 1–31 if Monthly shown as day-of-month input), `until_date` (optional, empty = infinite). The `date` field becomes the `start_date` of the recurrence (relabeled).

4. WHEN the user submits with toggle ON AND `start_date <= today` THEN system SHALL within a single `DB::transaction`:
   - Create a `Recurrence` row with `workspace_id`, `account_id`, `category_id`, `type=Income`, `description`, `value`, `frequency`, `frequency_day`, `start_date`, `until_date` (nullable), `next_date = <next cycle after start_date>`, `created_by`.
   - Create a `Transaction` (the first instance) with `type=Income`, `paid_at=null`, `credit_card_id=null`, `installment_*=null`, `recurrence_id=<new recurrence.id>`, `account_id`, `category_id`, `description`, `value`, `date=start_date`, `created_by=<user>`. Tags SHALL sync from form via `taggable` polymorphic relationship on both the Recurrence AND the Transaction.
   - Commit and redirect to `/w/{workspace}/incomes` with the new income visible and the recurrence created in the background (visible at `/recurrences`).

5. WHEN the user submits with toggle ON AND `start_date > today` THEN system SHALL create only the `Recurrence` row with `next_date = start_date` (no Transaction created yet). The income list SHALL NOT show any income for this recurrence until the scheduled job generates the instance on `start_date`. The recurrence SHALL appear at `/w/{workspace}/recurrences` with next_date = start_date.

6. WHEN the user submits with toggle ON AND the selected `category.type = Expense` THEN system SHALL reject with "Esta categoria não aceita receitas".

7. WHEN the user submits with toggle ON AND `account_id` is null THEN system SHALL reject with "A conta é obrigatória".

8. WHEN the user submits with toggle ON AND `value <= 0` THEN system SHALL reject with "O valor deve ser maior que zero".

9. WHEN the user submits with toggle ON AND `frequency = Weekly` AND `frequency_day` outside 0–6 THEN system SHALL reject with "Dia da semana inválido".

10. WHEN the user submits with toggle ON AND `frequency = Monthly` AND `frequency_day` outside 1–31 THEN system SHALL reject with "Dia do mês inválido".

11. WHEN the user submits with toggle ON AND `until_date` is set AND `until_date < start_date` THEN system SHALL reject with "A data final deve ser maior ou igual à data inicial".

12. WHEN the user submits with toggle OFF (avulsa) AND `account_id` is null THEN system SHALL reject with "A conta é obrigatória para receitas" (same as AC #7).

13. WHEN the user submits with toggle OFF (avulsa) AND `category.type = Expense` THEN system SHALL reject with "Esta categoria não aceita receitas" (same as AC #6).

14. WHEN a user views the income list at `/w/{workspace}/incomes` THEN system SHALL display all non-archived transactions of `type=Income` ordered by `date DESC`, showing: description, value in BRL format (R$ X.XXX,XX), date, account name, category color + name, tag chips, a "Recorrente" badge + link to parent recurrence (when `recurrence_id != null`), and a "Confirmar recebimento" action button (or "Recebida" indicator when `paid_at != null`).

15. WHEN the income list is empty (no avulsas, no generated instances yet) THEN system SHALL show empty state with CTA "Registrar primeira receita" linking to the unified create form.

16. WHEN a viewer attempts to create/edit/delete an income (with or without recurrence toggle) THEN system SHALL deny with 403.

17. WHEN a user attempts to access an income from a different workspace (scoped UUID route binding) THEN system SHALL return 404.

**Independent Test**: Toggling OFF, create income R$ 500 avulsa on account A → verify single Transaction created (recurrence_id=null), visible on income list. Navigate to create again, toggle ON, select Monthly day=5, start_date=today, infinite → verify Recurrence created + Transaction created (recurrence_id set) + Recurrence.next_date = today+1 month → verify both items visible on income list, second with "Recorrente" badge. Navigate to create again, toggle ON, start_date=30 days in future → verify only Recurrence created, NO Transaction, income list unchanged, but `/recurrences` shows it with next_date = start_date.

---

### P1: Confirmação de Recebimento ⭐ MVP

**User Story**: As a workspace member, I want to confirm receipt of expected income (avulsa OR recurrent-generated) so that the account balance reflects money that has actually arrived — identical to marking expenses as paid in DEBT-01.

**Why P1**: É o coração do controle financeiro — receita prevista é diferente de receita realizada. Só quando confirmada, o saldo da conta sobe. Espelha `pay`/`unpay` do DEBT-01 — recebe o mesmo tratamento independentemente de ser avulsa ou recorrente.

**Acceptance Criteria**:

1. WHEN a user clicks "Confirmar recebimento" on an unconfirmed income (avulsa OR recorrente) THEN system SHALL set `paid_at = now()` AND call `AccountService::recalculateBalance()` on the linked account to add the income value to `current_balance` within a single DB transaction.

2. WHEN a user clicks "Desmarcar recebimento" on a confirmed income THEN system SHALL set `paid_at = null` AND call `recalculateBalance()` to remove the income value from `current_balance` within a single DB transaction.

3. WHEN a user edits a confirmed income's value (avulsa OR recorrente with "Apenas esta" scope) THEN system SHALL `recalculateBalance()` to reflect the new value.

4. WHEN a user edits a confirmed income's `account_id` (moving it to a different account) THEN system SHALL call `recalculateBalance()` on BOTH the old and new accounts.

5. WHEN editing a confirmed income generated by a recurrence with scope "Esta e futuras" AND the value or account changes THEN system SHALL additionally `recalculateBalance()` for every OTHER confirmed future instance affected by the edit (those with `date >= current.date` AND `recurrence_id = X` AND `paid_at != null`).

6. WHEN the income list is rendered THEN system SHALL visually distinguish confirmed incomes (green accent, check icon) from unconfirmed ones (muted styling, pending icon).

7. WHEN any operation (confirm/unconfirm/edit/delete involving a paid income) fails during `recalculateBalance()` THEN system SHALL rollback the entire operation within a database transaction (mirrors DEBT-01 AC #7).

8. WHEN a user attempts to confirm an income linked to an archived account THEN system SHALL reject with "A conta vinculada foi arquivada. Restaure a conta antes de confirmar o recebimento."

9. WHEN a user confirms an income generated by a recurrence THEN the parent Recurrence is NOT affected (no next_date change, no status change) — confirming receipt is orthogonal to generation.

**Independent Test**: Create income R$ 2000 avulsa on account with balance R$ 1000 → balance stays R$ 1000 (unconfirmed) → confirm → balance R$ 3000 → unconfirm → R$ 1000 → edit confirmed value to R$ 2500 → balance reflects R$ 3500 → move to different account → old R$ 1000, new +R$ 2500 → delete confirmed → balance restored. Repeat with income generated by recurrence → same behavior, parent recurrence unaffected.

---

### P1: Geração Automática de Instâncias Recorrentes ⭐ MVP

**User Story**: As a system, I want a scheduled job to generate income transactions from recurrence rules on their due dates so that recurring income appears on the income list without manual action and the user confirms receipt when money actually arrives.

**Why P1**: A recorrência sem geração automática é apenas um cadastro morto. O job é o que materializa a recorrência em instâncias concretas. Sem isso, a recorrência se criada com `start_date > today` nunca gera nada sozinha.

**Acceptance Criteria**:

1. WHEN a `ProcessRecurrencesJob` is scheduled daily at midnight (`routes/console.php`) THEN system SHALL find all `Recurrence` records with `status = active` (and not soft-deleted) WHERE `next_date <= today` AND (`until_date IS NULL` OR `next_date <= until_date`) AND linked account is not archived.

2. FOR EACH matching recurrence, the job SHALL, within a single `DB::transaction`:
   - Create a `Transaction` row with: `type=Income`, `workspace_id=recurrence.workspace_id`, `account_id=recurrence.account_id`, `category_id=recurrence.category_id`, `description=recurrence.description`, `value=recurrence.value`, `date=recurrence.next_date`, `paid_at=null`, `credit_card_id=null`, `installment_*=null`, `recurrence_id=recurrence.id`, `created_by=recurrence.created_by`. Tags SHALL sync from the recurrence via `taggable` polymorphic relationship.
   - Compute the next occurrence date (`next_next_date`):
     - For Weekly frequency: `next_next_date = next_date->nextWeekday(freq_day)` (advance to the next occurrence of `frequency_day`).
     - For Monthly frequency: `next_next_date = next_date->addMonthNoOverflow()` with `day = min(frequency_day, days_in_that_month)` (handles Feb/day-31 edge case).
   - If `until_date` is set AND `next_next_date > until_date`, set `next_date = null` (recurrence exhausted). Otherwise set `next_date = next_next_date`.
   - Apply optimistic lock: `UPDATE recurrences SET next_date = <new_value>, updated_at = NOW() WHERE id = X AND next_date = <original_value>`. If affected rows = 0, another worker already processed it; SKIP creating the transaction (avoid duplicates on concurrent runs).
   - If the linked account is archived (soft-deleted), SKIP this recurrence and log a warning "Recorrência {id} pulada: conta arquivada".

3. WHEN a recurrence's `next_date` becomes `null` (exhausted) THEN the job SHALL NOT generate further transactions and SHALL NOT delete the recurrence (historical integrity preserved).

4. WHEN the job processes a recurrence with `frequency = Monthly` and the `frequency_day` exceeds the number of days in the next month (e.g., day 31 → February 28/29) THEN system SHALL use the last day of that month as the `date` of the generated transaction and advance `next_date` to the same last-day-of-month (`addMonthNoOverflow` Carbon semantics).

5. WHEN the job runs and a recurrence's `next_date` is multiple cycles in the past (e.g., job missed 3 months; recurrence monthly; today is 3 months past `next_date`) THEN system SHALL generate ONLY ONE transaction for the most recent due date (date = today if today <= until_date, else last valid until_date), then advance `next_date` to the NEXT future cycle relative to today. Skipping missed cycles is acceptable in v1 (no backfill of historic transactions).

6. WHEN the job encounters an exception for one recurrence (DB error, invalid data) THEN system SHALL log the error and CONTINUE processing remaining recurrences (do not abort the job). Mirrors `CloseBillsJob` resilience pattern.

7. WHEN a user views an income generated by a recurrence on the income list THEN the income SHALL display a "Recorrente" badge with a hyperlink to the parent recurrence at `/w/{workspace}/recurrences/{id}`.

8. WHEN a user (admin or editor) clicks "Gerar agora" action on the recurrence management page (INCM-05) THEN system SHALL run the generation logic for that ONE recurrence immediately (same logic as the scheduled job, only advancing `next_date` if it was due). It SHALL respect optimistic lock (concurrent with job → no duplicate). Viewer role SHALL be denied (403).

**Independent Test**: Create salary recurrence "Salário" R$ 5000 todo dia 5 do mês, start_date=2026-08-05, infinite → simulate job on 2026-08-05 → verify Transaction created (type=Income, paid_at=null, date=2026-08-05, value=5000, recurrence_id=X, description="Salário") → recurrence.next_date advanced to 2026-09-05 → run job again on same day → NO duplicate (optimistic lock blocks) → test monthly day=31 on February: recurrence next_date=2027-01-31, run job → transaction dated 2027-01-31, next_date set to 2027-02-28 → test backfill skip: set next_date to 2026-05-05 and run job on 2026-08-05 → ONE transaction dated 2026-08-05, next_date advanced to 2026-09-05 (June/July NOT backfilled).

---

### P1: Edição e Exclusão de Instância Recorrente com Escopo ⭐ MVP

**User Story**: As a workspace member editing or deleting a recurrence-generated income, I want to choose the scope ("this only" or "this and future") so that I can correct ONE instance without affecting the series, or apply a change to the entire remaining series at once.

**Why P1**: Sem escopo, o usuário fica refém da instância individual — para mudar o salário de R$ 5000 → R$ 6000 em todas as próximas, teria que editar instância por instância. P1 porque é o modo de evoluir a recorrência ao longo do tempo (aumentos de salário, troca de conta sem perder histórico).

**Acceptance Criteria**:

1. WHEN a user with edit permission opens the edit form on an income generated by a recurrence (`recurrence_id != null`) THEN system SHALL display the standard income form PLUS a scope selector with options: "Apenas esta" (default) and "Esta e futuras". The recurrence panel (frequency, frequency_day, start_date) SHALL be read-only/display-only (these are managed at the recurrence page, not via instance edit).

2. WHEN the user selects "Apenas esta" AND submits changes to description, value, date, account, category, or tags THEN system SHALL update ONLY the current Transaction. The parent Recurrence SHALL NOT be modified. Future generated instances SHALL use the original Recurrence values. `AccountService::recalculateBalance()` SHALL be called if the Transaction is confirmed (`paid_at != null`) and value or account_id changed.

3. WHEN the user selects "Esta e futuras" AND submits changes to description, value, account, category, or tags THEN system SHALL, within a single `DB::transaction`:
   - Update the current Transaction.
   - Update the parent Recurrence with the same description, value, account_id, category_id, tags (future generations use these values).
   - Update ALL other Transactions with `recurrence_id = parent.id` AND `date >= current.date` AND `deleted_at IS NULL` — apply the same changes to each.
   - Call `recalculateBalance()` for EVERY affected account if any of those updated Transactions had `paid_at != null`.

4. WHEN the user selects "Esta e futuras" AND attempts to change `frequency`, `frequency_day`, or `start_date` (via the read-only recurrence panel — not interactive) THEN system SHALL inform the user: "Para alterar a frequência, edite a recorrência na página de gestão." and prevent the submit.

5. WHEN a user deletes an income with `recurrence_id != null` THEN system SHALL prompt a modal: "Apenas esta" or "Esta e parar futuras".

6. WHEN the user selects "Apenas esta" for deletion THEN system SHALL soft-delete only the current Transaction. The Recurrence SHALL NOT be affected and SHALL continue to generate future instances on schedule. If the Transaction was confirmed (`paid_at != null`), `recalculateBalance()` SHALL be called on the linked account BEFORE soft-deleting to restore the balance.

7. WHEN the user selects "Esta e parar futuras" for deletion THEN system SHALL, within a single `DB::transaction`:
   - Soft-delete the current Transaction.
   - Soft-delete ALL other Transactions with `recurrence_id = parent.id` AND `date > current.date` AND `deleted_at IS NULL` (futures already generated).
   - Soft-delete the parent Recurrence (next_date = null implicitly via deleted_at).
   - Transactions with `date < current.date` (past, possibly confirmed) SHALL remain visible — historical integrity.
   - For each soft-deleted Transaction with `paid_at != null`, call `recalculateBalance()` on its linked account to restore balance.

8. WHEN a user attempts to delete/edit scope on an income generated by a recurrence that is paused (`status = paused`) or soft-deleted (parent already stopped) THEN system SHALL only allow "Apenas esta" — "Esta e parar futuras" is a no-op (parent already stopped). The income behaves like avulsa at this point.

9. WHEN a viewer attempts the edit/delete scope flow THEN system SHALL deny with 403.

10. WHEN the "Esta e futuras" edit results in a new `account_id` for the Recurrence AND future generated instances (not yet created by the job) SHALL now go to the new account — the job uses the Recurrence's `account_id` at generation time. No migration of past-due confirmed Transactions is forced (only future-dated confirmed Transactions with `date >= current.date` are touched per AC #3).

**Independent Test**: Create salary recurrence "Salário" R$ 5000 todo dia 5, infinite → let it generate Jan/Feb instances → edit Feb instance with "Esta e futuras" change value to R$ 6000 → verify Feb Transaction = R$ 6000, Recurrence.value = R$ 6000, Jan Transaction UNCHANGED R$ 5000, Mar instance when generated = R$ 6000 → edit Feb with "Apenas esta" change description → verify only Feb description changed, Recurrence.description unchanged → delete Feb with "Esta e parar futuras" → verify Feb gone, Recurrence soft-deleted, Mar+ not generated, Jan still visible → attempt edit Jan → "Apenas esta" only (parent stopped).

---

### P1: Gestão de Recorrências (Página Dedicada) ⭐ MVP

**User Story**: As a workspace member, I want a dedicated page to list and manage all my recurrence rules (active, exhausted, paused) so that I can pause a salary, edit a frequency, reativar a paused recurrence, or manually trigger generation — actions that don't require navigating via an instance.

**Why P1**: Sem página dedicada, recorrências esgotadas sem instância recente visível viram órfãs; o usuário não consegue ver/parar/editar a regra. A distinção das actions (mudar frequência, reativar, parar) é semanticamente diferente de editar instância.

**Acceptance Criteria**:

1. WHEN a user navigates to `/w/{workspace}/recurrences` THEN system SHALL display all `Recurrence` rows for the current workspace (active, exhausted, paused), ordered by `next_date ASC NULLS LAST` (ativas primeiro, esgotadas ao final). For each: description, value in BRL, frequency label ("Toda Seg" / "Todo dia 5"), next_date ("Próxima em DD/MM/YYYY" or "Esgotada"), until_date ("Indefinidamente" or formatted date), account name, category color + name, status badge ("Ativa" / "Esgotada" / "Pausada"), and actions: Editar, Pausar / Reativar, Gerar agora, Ver instâncias.

2. Paused recurrences (`status = paused`) SHALL be displayed alongside active and exhausted rules in the default `/recurrences` view, with a "Pausada" status badge and a "Reativar" action.

3. WHEN a user clicks "Editar" on a recurrence THEN system SHALL open the recurrence edit form (`/w/{workspace}/recurrences/{id}/edit`) with ALL fields editable: description, value, account_id, category_id, tags[], frequency, frequency_day, start_date (read-only if any Transaction already generated), until_date. Submit updates the Recurrence row. Future generated instances use the new values. Already-generated Transactions are NOT retroactively updated (edit via instance scope for that — INCM-04).

4. WHEN a user edits a recurrence's `frequency` or `frequency_day` AND no Transaction has been generated yet (`next_date = start_date` AND no income linked) THEN system SHALL recompute `next_date` to the next future occurrence based on the new frequency. If Transactions already exist, `next_date` SHALL be recomputed to the next future occurrence relative to today (preserves future schedule; past instances unchanged).

5. WHEN a user edits a recurrence's `until_date` to a date earlier than the current `next_date` THEN system SHALL set `next_date = null` (recurrence becomes exhausted — future generations stop). Already-generated Transactions remain intact.

6. WHEN a user clicks "Pausar" on an active recurrence THEN system SHALL set the Recurrence `status = paused`. Already-generated Transactions SHALL remain visible on the income list with their "Recorrente" badge pointing to the Recurrence. Future generations SHALL stop. The recurrence SHALL appear in `/recurrences` with a "Pausada" badge and a "Reativar" action.

7. WHEN a user clicks "Reativar" on a paused recurrence THEN system SHALL set the Recurrence `status = active` and recompute `next_date` to the next future occurrence based on `frequency`, `frequency_day` relative to today, respecting `until_date`. If `until_date < today`, system SHALL reject with "A recorrência já atingiu sua data final. Crie uma nova recorrência."

8. WHEN a user clicks "Gerar agora" on a recurrence AND `next_date <= today` AND `until_date IS NULL OR next_date <= until_date` THEN system SHALL run the same generation logic as the job for this ONE recurrence (AC INCM-03 #2). If `next_date > today`, system SHALL inform "A próxima ocorrência é em {next_date}. Aguarde o job ou ajuste a recorrência."

9. WHEN a user clicks "Ver instâncias" on a recurrence THEN system SHALL redirect to `/w/{workspace}/incomes?recurrence={id}` showing all incomes (active and soft-deleted) linked to this recurrence.

10. WHEN a viewer attempts to edit, pause, reactivate, or generate now on a recurrence THEN system SHALL deny with 403.

11. WHEN a user attempts to access a recurrence from a different workspace (scoped UUID route binding) THEN system SHALL return 404.

12. WHEN the recurrences page is empty (no active, no exhausted, no paused) THEN system SHALL show empty state: "Nenhuma recorrência cadastrada. Crie uma receita recorrente pelo formulário de receitas." with CTA linking to `/w/{workspace}/incomes/create`.

**Independent Test**: Create 2 recurrences (salary monthly infinite, freelance weekly until 2026-12-31) → verify both on `/recurrences` with correct labels → pause salary → salary appears with "Pausada" badge in the same list → reactivate salary → next_date recomputed to next month → edit freelance frequency_weekly from Tuesday (2) to Wednesday (3) → verify next_date moved to next Wednesday → click "Gerar agora" on salary when due → verify Transaction generated on income list → set salary until_date to yesterday → verify status "Esgotada", next_date = null.

---

### P2: Filtros e Busca em Receitas

**User Story**: As a workspace member, I want to filter and search incomes by category, account, date range, confirmation status, and recurrence origin so that I can quickly find specific transactions.

**Why P2**: Qualidade de vida — útil com volume crescente; não bloqueia o core. Espelha DEBT-01 P2.

**Acceptance Criteria**:

1. WHEN a user types in the search field on the income list THEN system SHALL filter incomes by description (partial, case-insensitive) using a debounced server request (300ms).

2. WHEN a user selects a category filter THEN system SHALL show only incomes with that category.

3. WHEN a user selects an account filter THEN system SHALL show only incomes linked to that account.

4. WHEN a user selects a date range filter THEN system SHALL show only incomes within that period (inclusive).

5. WHEN a user selects a confirmation status filter (Confirmadas / Previstas / Todas) THEN system SHALL show only incomes matching that status.

6. WHEN a user selects a "Apenas recorrentes" filter THEN system SHALL show only incomes with `recurrence_id != null`. Alternatively "Apenas avulsas" filters `recurrence_id IS NULL`.

7. WHEN the URL contains `?recurrence={uuid}` (linked from `/recurrences` "Ver instâncias" action) THEN system SHALL pre-filter the income list to that recurrence's instances and visually indicate the active filter with a "Limpar" action.

8. WHEN multiple filters are active THEN system SHALL combine them with AND logic.

9. WHEN filters are active THEN system SHALL show a visual indicator (e.g., filter count badge) and a "Limpar filtros" button.

**Independent Test**: Create 6 incomes across 3 categories, 2 accounts, 4 confirmed/2 previstas, 3 generated by recurrences → filter by category "Salário" → see only matching → add status "Confirmadas" → see intersection → toggle "Apenas recorrentes" → see intersection of recurring + confirmed + salário → clear all → all 6 visible → visit `?recurrence=X` URL → see only that recurrence's instances with clear filter button.

---

## Edge Cases

- WHEN value of an income is zero or negative THEN system SHALL reject with "O valor deve ser maior que zero".
- WHEN value exceeds 999999999.99 THEN system SHALL reject with "O valor excede o limite permitido".
- WHEN description is empty THEN system SHALL reject with "A descrição é obrigatória".
- WHEN description exceeds 255 characters THEN system SHALL reject with "A descrição não pode ter mais de 255 caracteres".
- WHEN date is in the future THEN system SHALL accept (user may pre-register known income; if recurrence toggle ON, becomes start_date > today → only Recurrence created, no Transaction yet).
- WHEN date is in the past (older than the account was created) THEN system SHALL accept but show warning "A data informada é anterior à criação da conta".
- WHEN an account is archived (soft-deleted) THEN a confirmed income referencing it SHALL still reference it for historical integrity; account name displayed with "(Arquivada)" suffix; NEW incomes/recurrências SHALL NOT be created on archived accounts (validation rejects).
- WHEN a category is soft-deleted THEN income referencing it SHALL still reference it; category name displayed with "(Removida)" suffix.
- WHEN tags are provided but some don't exist in workspace THEN system SHALL reject with "Tag inválida".
- WHEN account_id doesn't belong to the same workspace as the income/recurrence THEN system SHALL reject with validation error.
- WHEN category_id doesn't belong to the same workspace as the income/recurrence THEN system SHALL reject with validation error.
- WHEN a user attempts to set `credit_card_id` on an income THEN system SHALL reject with "Uma receita não pode estar vinculada a um cartão de crédito".
- WHEN the scheduled `ProcessRecurrencesJob` is missing or disabled in `routes/console.php` THEN recurrences SHALL NOT be generated automatically; user can use the manual "Gerar agora" button as a fallback.
- WHEN the job processes a recurrence created with `start_date` in the past (e.g., user backdated the start date) THEN ONLY ONE transaction is generated for the most recent due date (today or earlier if until_date passed); past cycles are NOT backfilled (v1 simplification).
- WHEN `frequency = Monthly` and `frequency_day = 29, 30, or 31` and the current month is February (28/29 days) THEN the generated transaction's `date` SHALL be the last day of February (28 or 29 depending on leap year) — `addMonthNoOverflow` semantics.
- WHEN `frequency = Weekly` and the recurrence's `next_date` falls on a different weekday than `frequency_day` (due to manual edit/restore) THEN system SHALL normalize `next_date` to the next occurrence of `frequency_day` on save.
- WHEN `until_date` equals `start_date` THEN the recurrence generates ONE transaction (or zero if start_date > today at creation, but next_date = start_date and is never reached because next_date is same date as until) — verifier semantics: if ON toggle and start_date = until_date = today, generate ONE Transaction then next_date = null (exhausted). If start_date = until_date in the future, only Recurrence created, and when job runs on start_date it generates one and exhausts.
- WHEN a recurrence reaches `until_date` (exhausted, `next_date = null`) and the user later edits `until_date` to a future date THEN system SHALL recompute `next_date` from today's next valid occurrence (respecting frequency and new until_date).
- WHEN a Recurrence's `account_id` is changed via edit to an archived account THEN system SHALL reject with "A conta selecionada foi arquivada".
- WHEN the income list contains only recurrences-generated incomes (no avulsas) THEN system SHALL display them normally with the recurring badge.
- WHEN two concurrent job runs (or manual + scheduled) attempt to process the same recurrence THEN the optimistic lock `UPDATE ... WHERE next_date = <expected>` SHALL cause the second run to affect 0 rows; the second run SHALL skip generating the transaction (no duplicate).
- WHEN a user attempts to confirm receipt of an income generated by a recurrence AND the income's `account_id` points to a soft-deleted account (mid-flight archiving race condition) THEN system SHALL reject with "A conta vinculada foi arquivada. Restaure a conta antes de confirmar o recebimento."
- WHEN a user pauses a Recurrence via "Pausar" on /recurrences page AND it has linked confirmed Transactions already generated THEN system SHALL only set `status = paused`; confirmed Transactions SHALL remain visible in the income list; their `recurrence_id` Foreign key SHALL remain valid (cascade not allowed).
- WHEN a user clicks "Reativar" on a paused recurrence whose `account_id` points to an archived account THEN system SHALL reject with "A conta vinculada foi arquivada. Restaure a conta ou selecione outra conta antes de reativar."
- WHEN a user edits an instance scope "Esta e futuras" AND the new `account_id` differs from the parent Recurrence's current `account_id` THEN the parent Recurrence.account_id is updated too (consistent with AC #3 of INCM-04); future generations go to the new account.
- WHEN the `ProcessRecurrencesJob` runs with zero matching recurrences (workspace without any recurrence, or all exhausted/paused) THEN system SHALL complete silently (no error, no log spam).
- WHEN the recurrence form sees the user toggle ON then toggle OFF again THEN system SHALL discard the recurrence panel inputs (frequency, frequency_day, until_date) on submit (treat as avulsa).
- WHEN a recurrence created with toggle ON AND `start_date <= today` AND the optimistic lock on first-instance-generation fails (concurrent creating twice) THEN system SHALL rollback the entire create operation (Recurrence creation + Transaction creation) and surface a validation error "Recurso já existe. Recarregue e tente novamente."

---

## Requirement Traceability

| Requirement ID | Story                                              | Phase   | Status  |
| --------------- | --------------------------------------------------- | ------- | ------- |
| INCM-01         | P1: CRUD Unificado de Receitas (Avulsa + Recorrente)| Specify | Pending |
| INCM-02         | P1: Confirmação de Recebimento                     | Specify | Pending |
| INCM-03         | P1: Geração Automática de Instâncias Recorrentes    | Specify | Pending |
| INCM-04         | P1: Edição e Exclusão de Instância Recorrente com Escopo | Specify | Pending |
| INCM-05         | P1: Gestão de Recorrências (Página Dedicada)        | Specify | Pending |
| INCM-06         | P2: Filtros e Busca em Receitas                     | -       | Pending |

**Coverage:** 6 requirements, 6 mapped to stories, 0 unmapped ⚠️

**ID format:** `INCM-[NUMBER]`

**Status values:** Pending → In Design → In Tasks → Implementing → Verified

---

## Success Criteria

- [ ] User can register an avulsa income in < 15 seconds from the unified income form
- [ ] User can register a recurring income (weekly or monthly) with optional end date in < 30 seconds from the same unified form, just by toggling "É recorrente?"
- [ ] When registering a recurring income with `start_date <= today`, both the Recurrence rule AND the first Transaction appear immediately
- [ ] When registering a recurring income with `start_date > today`, only the Recurrence appears on `/recurrences`; the Transaction appears on `start_date` via the scheduled job
- [ ] Confirming an income immediately increases the account balance (no page refresh required)
- [ ] Unconfirmed ("prevista") and confirmed ("recebida") incomes are visually distinguishable at a glance
- [ ] Recurrence instances display a "Recorrente" badge with link to the parent rule
- [ ] Editing/deleting a recurrence-generated instance offers scope ("Apenas esta" / "Esta e futuras") and applies correctly without retroactive changes to past instances
- [ ] `/recurrences` page lists all active, exhausted, and paused recurrences with management actions (Edit, Pause, Reactivate, Generate now, View instances)
- [ ] Daily scheduled job generates pending recurrence instances correctly and idempotently (no duplicates on concurrent/overlapping runs)
- [ ] Monthly frequency with `frequency_day > days-in-month` gracefully falls back to last day of that month
- [ ] Cancelling a recurrence via "Esta e parar futuras" preserves all past (possibly confirmed) transactions and their links visible
- [ ] Pausing + reactivating a recurrence correctly recomputes next_date from today's next valid occurrence (respecting until_date)
- [ ] Filters combine correctly (AND logic) and visually indicate active state
- [ ] Balance integrity: no sequence of confirm/unconfirm/edit/delete with scope operations leaves balance in a wrong state
- [ ] Cross-workspace isolation: no income or recurrence leaks between workspaces (scoped UUID route binding + workspace_id FK)
- [ ] Soft deletes preserve historical income + recurrence data for auditing and future features (DASH-01, INSG-01)
- [ ] Category type constraint prevents income using expense-only categories (and vice versa — already enforced in DEBT-01)
- [ ] Manual "Gerar agora" fallback works when the scheduled job has not yet run on its due date