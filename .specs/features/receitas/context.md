# Receitas — Context (User Decisions for Gray Areas)

**Story ID:** INCM-01
**Date:** 2026-07-15
**Source:** User answers during Specify phase

Decisions captured for gray areas left open in `spec.md`. These are inputs for Design + Tasks phases.

## G-1: Edição de parcela individual de receita parcelada

**Decision:** Editar parcela edita SÓ aquela linha.

**Rationale:** Parcelas são independentes uma vez criadas (mesma família por `installment_group_id`), mas cada uma é sua própria `Transaction` com próprio `paid_at`, valor (pós-arredondamento), conta (pode mudar destino entre parcelas — ex: 「1ª parcela na conta A, 2ª na B」). Edit do grupo só na criação e no destroy.

**Behavior locked:**
- Update de uma parcela NUNCA propaga a otras parcelas do grupo.
- CCXP-01 tem comportamento diferente (batch ops) — INCM-01 NÃO espelha essa parte; divergência documentada (atualizar CCXP-01 spec como nota? Sim, durante Execução do INCM-01).

---

## G-2: Excluir parcelada não-recebida

**Decision:** Dialog de confirmação com ação dupla: 「Excluir apenas esta parcela」 vs 「Excluir todas as parcelas do grupo」.

**Rationale:** User requested explicit UI choice; controller precisa de 2 endpoints (ou 1 endpoint com `scope` query param).

**Behavior locked:**
- Default highlight: 「Excluir todas as parcelas」 aparece como destrutivo (vermelho, confirmação dupla se grupo > 3 parcelas).
- 「Excluir apenas esta parcela」 Mantém o `installment_group_id` intacto; NÃO recalcula `installments_total` (parcela ausente fica gap no `installment_number` ≠ reindexação — histórico preserva estado).
- Impacto: service `DeleteInstallmentService::deleteOne($uuid, $scope)` where `$scope∈{single,group}`.
- Ação bloqueada se `paid_at != null` na parcela alvo do 「single」 subset para a parcela recebida exclusão via fluxo normal de recebida (reverte saldo + soft delete).

---

## G-3: Editar template recorrente

**Decision:** UI pergunta ao usuário: 「Aplicar a futuras ocorrências não recebidas?」 (checkbox na modal de edição do template).

**Rationale:** User wants explicit choice; nunca modify ocorrências já recebidas (saldo real já impactado).

**Behavior locked:**
- Edição de template sem propagação: só atualiza fields do template (próximas gerações criadas com esses valores).
- Com propagação: update `WHERE recurring_parent_uuid=template.uuid AND paid_at IS NULL`.
- Campos propagáveis: `description, value, category_id, account_id, date(carrega +offset) — NOT date (data é fixo por mês bucket por G-6/D-37)` — valores mudam, datas não.
- Tags: sincronizadas com futuras (sync via `taggables`).
- Ocorrências já recebidas: NUNCA tocadas, nem mesmo tags.

---

## G-4: Deletar template recorrente

**Decision:** UI confirma: 「excluir recebidas também」 vs 「manter ocorrências como standalone」.

**Rationale:** User asked explicit confirmation (memory: D-37 originally preferia standalone干 hard-coded — revisão).

**Behavior locked:**
- Option A: 「Manter ocorrências」 `Occurrence::where('recurring_parent_uuid', $template->uuid)->update(['recurring_parent_uuid' => null, 'is_recurring' => false])`; soft-delete só template.
- Option B: 「Excluir ocorrências não-recebidas」: soft-delete template + soft-delete unreceived occurrences; received occurrences always kept (memory: nenhuma deleção afeta saldo histórico).
- Nunca ocorre opção 「excluir recebidas」: recebidas são saldo real — permanecem intactas sempre.
- Default selección no dialog: Option A (safe default).

---

## G-5: Marker técnico para transferências

**Decision:** Derivado de `transfer_group_id IS NOT NULL`. Sem `is_transfer` boolean column.

**Rationale:**
- `transfer_group_id` já no schema; único marker técnico canônico (impossível falsificar fora da API de transferência).
- Boolean `is_transfer` seria redundante + desync risk.
- Categoria sistema 「Transferência」 (G-8 below) = só para UX/display e filtro; NÃO fonte de verdade.
- Dashboard/reports 「Renda real」 = `WHERE type=Income AND transfer_group_id IS NULL`. Análogo para 「Despesa real」.
- Custo: 1 predicate por query. Zero denormalização.

**Behavior locked:**
- Services/services resources never expose `is_transfer`; expose `transfer_group_id` (null = not transfer).
- `TransactionResource::isTransfer()` derivada = `$this->transfer_group_id !== null`.
- Category sistema 「Transferência」 displayed when `isTransfer()===true` (override visual), independentesda `category_id` da leg (vc pode setar categoria sistema「Transferência」 para visual agrupamento ou deixar user category — design decide).

---

## G-6: Idempotencia recorrência — UNIQUE constraint

**Decision:** `UNIQUE(recurring_parent_uuid, year_month_bucket)` onde `year_month_bucket` = CHAR(7) `'YYYY-MM'`.

**Rationale:** User escolheu bucket string; simples e compatível MariaDB. Alternativa via virtual column from `date` descartada.

**Behavior locked:**
- New column `recurring_year_month char(7) null` on `transactions`.
- Unique index only sobre `recurring_parent_uuid + recurring_year_month` but partial (ambos not null)? MariaDB supports NULLs in unique — multiple nulls OK.
- Setado quando ocorrência é criada a partir de template (`recurring_year_month = $date->format('Y-m')`).
- Para receitas avulsas não recorrentes: `recurring_parent_uuid = null AND recurring_year_month = null` — index parcial harmónico (null不在 unique).
- Template self-row: `recurring_parent_uuid = null` para si, mas `id` é o parent — especificamente: ocorrências illnesses têm `recurring_parent_uuid = template.uuid`; template não tem parent. Logo unique não se aplica ao template (apenas às ocorrências). ✓ Sem colisão.
- Decisão final sobre que coluna nova: yan uses `recurring_year_month` para ocorrências; valor null no template. Migration incremental.

---

## G-7: Checker jobb recurrence

**Decision:** Job diário a `midnight`; novo `GenerateRecurringIncomesJob` separado (NÃO merges com `CloseBillsJob`).

**Rationale:** Separação de concerns:
- `CloseBillsJob` é de cartão (CreditCardBill lifecycle — D-32).
- Recorrência é de Receita (escopo diferente).
- Scheduler Laravel único em `routes/console.php` ou `bootstrap/app.php` -> `Schedule::command('receitas:generate-recurring')->dailyAt('00:00')`.
- Artisan command `receitas:generate-recurring` envolve job. Fallback on-demand: quanto listagem de receitas carrega para usuário, backend verifica templates activos (`is_recurring=true AND (recurring_ends_at IS NULL OR recurring_ends_at >= today)`) e gera missing months antes de retornar. NÃO dupla-geração via UNIQUE (G-6).

**Behavior locked:**
- Job roda em container (docker-compose — não inventar Node-based scheduler; Laravel-native).
- Idempotência server-side guaranteed via UNIQUE (G-6); race entre job e fallback harmónico (segundo perde inserção, primary key violation capturado no try/catch).

---

## G-8: Categoria sistema 「Transferência」

**Decision:** Auto-criar category 「Transferência」 per workspace (padrão D-31), system-managed, user não pode deletar, name editável.

**Behavior locked:**
- Criada no momento que a workspace tem sua primeira `Account` (igual 「Pagamento de Cartão」 é criada ao primeiro cartão) OR lazy on first transfer creation — Design decide, mas倾向: criar na primeira transferência (postergar schema pollution).
- `type = Both` (aparece em receitas e despesas).
- Category auto-assigned to BOTH legs (Income e Expense) no momento da criação da transferência. User pode posteriormente editar a categoria? Block: NÃO — categorias das legs são locked como 「Transferência」; transferência NÃO usa user-chosen category (é interna). Annotations/tags ainda livres.
- Used purely para filtro visual + contagem; agregação de dashboards usa `transfer_group_id IS NOT NULL` (G-5) como exclusion filter — não rely só na categoría.
- 「Pagamento de Cartão」 (D-31) similarly is system-managed — 一致 pattern.

---

## Cross-cleanups required during INCM-01 implementation:

1. Update CCXP-01 spec/spec to note INCM-01 divergence on per-installment edit (G-1).
2. Update DEBT-01 out-of-scope row (done — kept for log).
3. PROJECT.md v1 transfer line updated (done).
4. STATE.md decisions D-34 to D-39 logged (done).

---

## Open detailed decisions deferred to Design phase (not user-blocking):

- Exact UI components: choose vs create `RadioGroup`, `Dialog` variants
- Whether new columns (`recurring_year_month`) added to schema as separate migration or part of a larger migration
- API endpoint design for transfer (single POST `/w/{w}/transfers` vs reuse `TransactionController::store` with `transfer` flag)
- Whether `receitas:generate-recurring` is Artisan command or Job class; scheduler registration location
- React page structure: single Receitas list + transfer creation modal vs dedicated `/transfers` route
- Whether tags are allowed on transfer legs (yes by memory D-38) — reconfirm during design.