# Receitas — Specification

**Story ID:** INCM-01
**Phase:** P1 — MVP Core
**Parent Spec:** `.specs/features/workspace-financeiro/spec.md`

## Problem Statement

Workspace members need to record incoming money — salary, freelance gigs, refunds, rental income — linked to a bank account so their balance reflects real cash inflow. Without income tracking, accounts only ever decrease (DEBT-01), dashboards show outflow without inflow, and the workspace cannot answer the most basic financial question: *did I make more than I spent?* Receitas are the symmetric counterpart to debit expenses and close the balance loop on Milestone 1.

## Goals

- [ ] CRUD de receitas com descrição, valor, data, conta, categoria (Income/Both) e tags
- [ ] Toda receita nasce "a receber"; recebimento é ação explícita do usuário
- [ ] Recebimento soma ao saldo da conta via `AccountService::recalculateBalance()`
- [ ] Edição/exclusão de receita recebida reverte e recalcula saldo corretamente
- [ ] Receitas parceladas reutilizando `installment_number` / `installments_total` / `installment_group_id` (schema existente)
- [ ] Receitas recorrentes mensais (template + ocorrências geradas lazy/até data fim)
- [ ] Transferência entre contas como par de receita+despesa espelhada no mesmo grupo
- [ ] UI distingue visualmente receitas recebidas de "a receber"
- [ ] Filtros por categoria, conta, período, status de recebimento e busca textual; paginação 25/página

## Key Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Model | `Transaction` (tabela única) | Espelho DEBT-01/CCXP-01; `type=Income` discrimina receitas |
| Recebido em | Reusa coluna `paid_at` (sem nova coluna `received_at`) | `recalculateBalance()` já soma `paid_income`; UI rótulos "Recebida em X" vs "Pago em X" conforme `type` |
| Status default | `paid_at = null` ("a receber") | Espelho DEBT-01; suporta projeção de fluxo (FUTX-01) |
| Saldo | `recalculateBalance()` soma receitas pagas ao saldo após create/update/delete de receitas recebidas | `initial_balance + sum(paid_income) - sum(paid_expenses)` (fórmula já definida em DEBT-01) |
| Categoria | Aceita `type` ∈ {`Income`, `Both`}; rejeita `Expense` | Espelho da validação que bloqueia `Income` em despesas |
| Tags | Many-to-many polimórfico `taggables` + `syncTags()` | Reutiliza infraestrutura CAT-01/DEBT-01 |
| Conta destino | `Account` (banco) da mesma workspace; cartões não aceitos | Espelho DEBT-01; validação cross-workspace |
| Parcelamento | Reusa `installment_number`, `installments_total`, `installment_group_id` (UUID de grupo) | Schema originado em CCXP-01; cada parcela = uma `Transaction type=Income`; arredondamento respeita D-33 (última absorve resto) |
| Recorrência (P1) | Receita recorrente **mensal**; cria Transaction template com `is_recurring=true`, `recurring_parent_uuid`, `recurring_ends_at` (nullable); ocorrências geradas lazy até mês corrente por job diário + fallback on-demand | Modelo simples que sustenta salário/aluguel recebido sem generalizar frequências customizadas; reusar padrão `CloseBillsJob` (D-32) |
| Transferência entre contas | Par de 2 `Transaction` (Income no destino + Expense na origem) em mesmo grupo (`transfer_group_id` UUID); ambas recebebidas/pagas simultaneamente e ambas disparam `recalculateBalance()` na respectiva conta | Espelho simétrico explicado pelo usuário; saldo total invariante da workspace; contradiz PROJECT v1 — revisão formal abaixo |
| Contradição PROJECT.md | Transferência entre contas marcada OFF em PROJECT v1 e DEBT-01; INCM-01 reintroduz por decisão explícita do usuário | Estado: necessita atualizar PROJECT.md/DEBT-01 out-of-scope e registrar decisão em STATE.md (recomendado D-34) |
| Soft deletes | `SoftDeletes` desde day 1 | Consistência com demais transações |
| Date default | Hoje quando omitida | Espelho DEBT-01 |
| Future date | Aceita (receita prevista/recebida a termo) | Compatível com FUTX-01 |

## Out of Scope

| Feature | Reason |
|---------|--------|
| Transferência entre contas como feature nativa separada (TRSF) | Decisão do user: par espelho reutiliza Transaction; sem modelo próprio |
| Frequências de recorrência != mensal | P1 só mensal; semanal/quinal/anual adiadas |
| Anexos/comprovantes | v1 — storage desnecessário (igual DEBT-01) |
| Importação de extratos | IMPT-01 (P2) |
| Conciliação bancária manual | Saldo é calculado (igual DEBT-01) |
| Receita em cartão de crédito | Cartão é despesa (CCXP-01); receita sempre cai em `Account` |
| Transferência internacional / multi-moeda | Fora do escopo v1 (PROJECT.md) |
| Delete de ocorrência já recebida de receita recorrente isoladamente | Edita a ocorrência como qualquer receita; ocorrência não-gerada (a receber) pode ser pulada sem afetar template |

---

## User Stories

### P1: CRUD de Receitas ⭐ MVP

**User Story**: As a workspace member, I want to record, view, edit and delete income transactions with description, value, date, account, category and tags so that I can track incoming money.

**Why P1**: Receitas fecham o loop de saldo do Milestone 1 — sem receitas, contas só decrementam. Dashboard futuro depende de receita existir.

**Acceptance Criteria**:

1. WHEN a user with create permission submits a receita (description, value, date, account_id, category_id, tags[]) THEN system SHALL create a `Transaction type=Income, paid_at=null`, linked to current workspace, and redirect to the receita list.
2. WHEN a user views the receita list THEN system SHALL display all non-archived receitas ordered by date descending showing: description, value in BRL (R$ X.XXX,XX), date, account name, category name with color, tag chips, and status badge ("A receber" / "Recebida em dd/mm/yyyy").
3. WHEN a user edits an unpaid receita's description, value, date, account, category or tags THEN system SHALL update the record and sync tags via `taggables`.
4. WHEN a user deletes an unpaid receita THEN system SHALL soft-delete it; it SHALL NOT appear in default lists.
5. WHEN the receita list is empty THEN system SHALL show empty state with CTA "Registrar primeira receita".
6. WHEN a viewer-role user attempts to create/edit/delete THEN system SHALL return 403.
7. WHEN a user attempts to access a receita from a different workspace THEN system SHALL return 404 (scoped route model binding).
8. WHEN a receita's category_id refers to a category with `type=Expense` (or `Both`? rejeita) — i.e., `type=Expense` only — THEN system SHALL reject with "Categoria de despesa não pode ser usada em receita".

**Independent Test**: Criar 3 receitas em contas/categorias/tags distintas → listar com formatação BRL correta → editar descrição de uma → listar atualizada → excluir uma → não aparece (soft deleted).

---

### P1: Recebimento de Receita ⭐ MVP

**User Story**: As a workspace member, I want to mark receitas as received so that the account balance reflects money that actually entered my account.

**Why P1**: Simétrico ao pagamento de despesa. Sem recebimento confirmado, saldo não reflete entradas reais e planejamento de fluxo fica impossível.

**Acceptance Criteria**:

1. WHEN a user marks an unpaid receita as received THEN system SHALL set `paid_at = now()` AND call `AccountService::recalculateBalance()` on the linked account to add the value to `current_balance`.
2. WHEN a user un-marks a received receita (back to "a receber") THEN system SHALL set `paid_at = null` AND call recalculateBalance() to deduct the value.
3. WHEN a user edits a received receita's value THEN system SHALL recalculateBalance() to reflect the new value.
4. WHEN a user edits a received receita's account_id (moves it) THEN system SHALL call recalculateBalance() on BOTH the old and new accounts.
5. WHEN a user deletes a received receita THEN system SHALL call recalculateBalance() to deduct before soft-deleting.
6. WHEN viewing the list THEN system SHALL visually distinguish received / "a receber" (estilo distinto: mute, check icon, badge colorida).
7. WHEN any recebimento operation (receive/unreceive/edit received/delete received) fails during recalculateBalance THEN system SHALL rollback within a database transaction.

**Independent Test**: Criar receita R$100 → marcar recebida → saldo +R$100 → desmarcar → saldo restaurado. Editar receita recebida para R$200 → saldo +R$100 adicional. Mover para outra conta → conta antiga -R$200, nova +R$200. Excluir recebida → saldo restaurado.

---

### P1: Receita Parcelada ⭐ MVP

**User Story**: As a workspace member, I want to register an income payable in N installments so that each installment enters as its own receita (revenue recognition per period).

**Why P1**: Espelhamento natural com CCXP-01 e schema já pronto. Cobre casos como venda a prazo em 3x que o user solicitou.

**Acceptance Criteria**:

1. WHEN a user creates a receita with installments_total=N (N≥2) and value=V THEN system SHALL create N `Transaction type=Income` rows sharing `installment_group_id` (UUID), with `installment_number` 1..N and dates one month apart starting from the given date.
2. WHEN distributing value across installments THEN system SHALL use `round(V/N, 2)` for the first N-1 and last absorbs remainder so that `sum == V` exactly (D-33).
3. WHEN a user edits a single installment's value but only received status of standalone installment THEN... TBD: edit of an installment only affects that installment (installments are independent once created, unlike CCXP-01 batches). **Design fase vai detalhar; especifique o comportamento mínimo aqui** — default: editing one installment edits only that row.
4. WHEN a user deletes a parcelada capturada-ainda-não-recebida THEN system SHALL offer "excluir apenas esta parcela" vs "excluir todas as parcelas do grupo"; padrão de UI confirmado em Design.
5. WHEN each installment receives `paid_at` THEN system SHALL recalc a conta aumentando o saldo apenas daquela parcela.
6. WHEN installments_total=1 (or omitted) THEN system SHALL treat as normal receita (installment_number=1, total=1).

**Independent Test**: Criar receita R$1000 em 3x → 3 linhas R$333.33, R$333.33, R$333.34 → datas mensais → somatório R$1000 exato → receber a 1ª parcela → conta +R$333.33 → excluir o grupo inteiro → todas removidas.

---

### P1: Receita Recorrente ⭐ MVP

**User Story**: As a workspace member, I want to register a recurring income (e.g., monthly salary) so that receitas are generated automatically each period instead of re-entering monthly.

**Why P1**: Decisão do user. Salário/aluguel recebido é o caso mais natural de recorrência; cria massa crítica para FUTX-01 (despesas futuras espelha receitas futuras).

**Acceptance Criteria**:

1. WHEN a user creates a receita with `is_recurring=true`, start_date D, optional `recurring_ends_at` E (nullable = sem fim) THEN system SHALL create a template `Transaction type=Income, is_recurring=true, recurring_parent_uuid=null (self-template), installment_* null` (recorrência não reusa installments).
2. WHEN template created THEN system SHALL generate occurrences for periods from D up to current month (and ≤ E if set); each occurrence is a normal `Transaction type=Income, is_recurring=false, recurring_parent_uuid=template.uuid, paid_at=null`.
3. WHEN new month arrives THEN a scheduled job (daily, mirroring `CloseBillsJob`) SHALL generate occurrences for months not yet present, bounded by current month and `recurring_ends_at`.
4. WHEN backend generation runs after midnight on first of month THEN system SHALL generate by lazy fallback on first read too (defensive double — never pula ocorrência).
5. WHEN a user cancels a recurring receita THEN system SHALL set `recurring_ends_at = today()` (template kept); future occurrences SHALL NOT be generated; past occurrences remain intact.
6. WHEN a user edits the template's description/value/category/account THEN system SHALL offer (UI confirmada em Design) to propagate to FUTURE not-received occurrences only — never modify received occurrences.
7. WHEN a user deletes a received occurrence THEN system SHALL treat it as normal receita (reverte saldo); the cancellation of the *whole recurrence* é ação separada no template.
8. WHEN a user deletes the template THEN system SHALL block soft-delete of received occurrences but SHALL not cascade (occurrences become standalone `is_recurring=false, recurring_parent_uuid` cleared). Design detalha.

**Independent Test**: Criar receita recorrente salário R$5000 início 01/01 → sistema gera JAN, FEV, ..., até mês corrente → job avança mês → novas ocorrências aparecem → cancelar recorrência → sem novas gerações, recebidas permanecem → excluir template → ocorrências viram standalone.

---

### P1: Transferência Entre Contas ⭐ MVP

**User Story**: As a workspace member, I want to transfer money between two of my accounts so that balances reflect internal money movement without inflating income/expense totals.

**Why P1**: Decisão do user (contraria PROJECT v1 original; override registrado). Importante para fluxo real: carteira → poupança, conta-corrente → cartão-pré-pago.

**Acceptance Criteria**:

1. WHEN a user creates a transfer (from_account_id A, to_account_id B, value V, date D, description) THEN system SHALL create in ONE database transaction TWO `Transaction` rows: (a) `type=Expense, account_id=A, paid_at=now, transfer_group_id=G (new UUID)` and (b) `type=Income, account_id=B, paid_at=now, transfer_group_id=G`.
2. WHEN transfer created THEN system SHALL call `AccountService::recalculateBalance()` on BOTH accounts: A decresce V, B acresce V. Saldo total da workspace (soma) permanece invariante.
3. WHEN from_account == to_account THEN system SHALL reject with "Conta de origem e destino devem ser diferentes".
4. WHEN either account pertence a workspace diferente THEN system SHALL reject com erro de validação e 404 scoped.
5. WHEN a user edits the value of a transfer THEN system SHALL update both rows atomically and recalc both accounts.
6. WHEN a user deletes a transfer THEN system SHALL delete both rows (soft) and recalc both accounts to restore values.
7. WHEN a user lists receitas THEN transfer receitas (Income leg) SHALL appear flagged as "Transferência" (ícone/badge distinto) so that they NÃO poluem ficha de "renda real" total receitas (designa como interna). Same appears in expense list flagged "Transferência".
8. WHEN listing dashboard/reports THEN transfer legs SHALL ser aggregated separately (não somar como receita/despesa) — ícone enum/flag de categoria; detalhes em Design.

**Independent Test**: Conta A saldo R$1000, conta B R$0. Transferir R$300 A→B → A=R$700, B=R$300, soma workspace R$1000 invariante. Editar valor para R$500 → A=R$500, B=R$500. Excluir transferência → A=R$1000, B=R$0 restaurados.

---

### P2: Filtros e Busca

**User Story**: As a workspace member, I want to filter and search receitas by category, account, date range, status, recurrence/parcelada/transfer flag and text.

**Why P2**: Qualidade de vida à medida que a lista cresce. Espelho do DEBT-01.

**Acceptance Criteria**:

1. WHEN typing in search THEN system SHALL filter receitas by description (partial, case-insensitive) com debounce 300ms via server request.
2. WHEN selecting categoria filter THEN SHALL mostrar só receitas da categoria.
3. WHEN selecting conta filter THEN SHALL mostrar só da conta.
4. WHEN selecting período (start/end) THEN SHALL mostrar no range inclusive.
5. WHEN selecting status (a receber / recebida / todas) THEN SHALL filtrar por `paid_at`.
6. WHEN selecting flag (recorrente / parcelada / transferência / simples) THEN SHALL filtrar por `is_recurring` / `installments_total>1` / `transfer_group_id not null` / nenhum.
7. WHEN multiple filters active THEN SHALL combinar com AND + badge count + botão "Limpar filtros".

**Independent Test**: Criar 5 receitas (1 recorrente, 1 parcelada, 3 simples) → filtrar "Recorrente" → ver 1 → combinar com conta → intersecção → limpar → todas visíveis.

---

### P2: Paginação

**Acceptance Criteria**:

1. WHEN list >25 THEN paginate 25/página com controles.
2. WHEN filters active THEN pagination aplica aos filtered.
3. WHEN navigating pages THEN filters preservados (query string).

**Independent Test**: Seed 30 receitas → 25 na pág 1 → pág 2 → 5 restantes → aplicar filtro → paginação ajusta.

---

## Edge Cases

- WHEN value ≤ 0 THEN reject "O valor deve ser maior que zero".
- WHEN value > 999999999.99 THEN reject "O valor excede o limite permitido".
- WHEN description empty THEN reject "A descrição é obrigatória".
- WHEN description > 255 chars THEN reject "A descrição não pode ter mais de 255 caracteres".
- WHEN date omitted THEN default hoje.
- WHEN date future THEN accept (receita prevista).
- WHEN account archived THEN receita still references for history; name shown with "(Arquivada)".
- WHEN category soft-deleted THEN receita still references; name with "(Removida)".
- WHEN tags provided but not in workspace THEN reject "Tag inválida".
- WHEN account_id/category_id cross-workspace THEN reject (scoped).
- WHEN receita uses category type=Expense THEN reject "Categoria de despesa não pode ser usada em receita".
- WHEN installments_total < 2 THEN normal receita (installments_total=1, installment_number=1).
- WHEN installments_total=0 or negative THEN reject "Número de parcelas inválido".
- WHEN installments_total > 60 THEN reject "Máximo de 60 parcelas" (guarda total).
- WHEN recurrence duration overlaps a parcelada group on same receita THEN reject: recorrente e parcelado mutualmente exclusivos.
- WHEN transfer has account outside workspace THEN reject + scoped 404.
- WHEN recurring template has requrring_ends_at < start_date THEN reject.
- WHEN ad-hoc occurrence generation trigger runs twice in same month THEN system SHALL be idempotente (no duplicate occurrence for a given month) — guard via UNIQUE(recurring_parent_uuid, year-month bucket).
- WHEN received occurrence is regenerated by race (deleted then regeneration) THEN integrity preserved via the idempotency guard above.

---

## Requirement Traceability

| Requirement ID | Story                                       | Phase  | Status  |
| -------------- | ------------------------------------------- | ------ | ------- |
| INCM-01        | P1: CRUD de Receitas                        | Specify | Pending |
| INCM-02        | P1: Recebimento de Receita                  | Specify | Pending |
| INCM-03        | P1: Receita Parcelada                       | Specify | Pending |
| INCM-04        | P1: Receita Recorrente                      | Specify | Pending |
| INCM-05        | P1: Transferência Entre Contas               | Specify | Pending |
| INCM-06        | P2: Filtros e Busca                         | -      | Pending |
| INCM-07        | P2: Paginação                               | -      | Pending |

**Coverage:** 7 requirements, 0 mapped to tasks yet (phase de Tasks), 0 unmapped.

---

## Success Criteria

- [ ] Usuário registra uma receita avulsa em < 15 segundos.
- [ ] Recebimento reflete imediatamente no saldo da conta (sem refresh).
- [ ] "A receber" e "Recebida" visualmente distinguíveis.
- [ ] Integridade de saldo: nenhuma sequência de edit/receive/unreceive/delete deixa saldo errado.
- [ ] Receita parcelada de R$1000 em 3x gera exatamente R$333.33 + R$333.33 + R$333.34.
- [ ] Receita recorrente salário gera ocorrências mensais até mês corrente sem duplicidade após job ou fallback lazy.
- [ ] Transferência A→B deixa soma de saldos da workspace invariante.
- [ ] Lista de receitas marca transferências como "Transferência" e não inflate o total de "renda real".
- [ ] Isolamento cross-workspace: nenhuma receita vaza entre workspaces.
- [ ] Soft deletes preservam histórico.
- [ ] Validação de categoria: tipo Expense nunca usado em receita; tipo Both permitido.