# DataTable — Tasks

**Story ID:** DTBL-01
**Spec:** `.specs/features/datatable/spec.md`
**Design:** `.specs/features/datatable/design.md`

TDD-first. Cada task lista teste + gate. Ordem topológica: infra → piloto → expansão → limpeza.

---

## Fase 0 — Fundação (infra backend + frontend)

### T0.1 — Deps: shadcn table + axios
- `npx shadcn@latest add table` (gera `components/ui/table.tsx`).
- `npm i axios`.
- **Gate:** `npm run build` verde; `table.tsx` presente; axios em `package.json`.

### T0.2 — `Filter`, `DatatableConfig`, `DatatableService`
Backend em `app/Services/Datatable/`. Implementa search, filtros (text/number/date/relation/select), sort whitelist, paginação, resposta `{data, meta}`.
- **Testes (antes):** `tests/Feature/Datatable/DatatableServiceTest.php` (search, cada tipo de filtro, filtro relation por uuid, sort inválido ignorado, paginação, shape).
- **Gate:** `php artisan test --filter=DatatableServiceTest`; `composer quality`.

### T0.3 — Tipos frontend `types/datatable.ts`
`Paginated<T>`, `PaginatedMeta`, `DataTableColumn<T>`, `DataTableFilter`, `SelectOption`, `SortDirection`, `DataTableParams`.
- **Gate:** `npx tsc --noEmit` limpo.

### T0.4 — `lib/api.ts` (axios wrapper)
`getJson<T>(url, params?, signal?)` com `Accept: application/json`, abort, erro padronizado.
- **Gate:** build limpo.

### T0.5 — `hooks/use-data-table.ts`
Estado (page/sort/filters/rows/meta/loading/error), debounce 300ms p/ text, reset de page ao filtrar, `AbortController`.
- **Gate:** build limpo.

### T0.6 — Componentes `Components/DataTable/*`
`DataTable`, `DataTableColumnHeader`, `DataTableFilterRow`, `DataTableFilterInput`, `DataTablePagination`. Compostos sobre shadcn `Table`/`Button`/`Input`/`Select`/`Badge`. Filtros na 2ª linha do thead.
- **Gate:** `npm run quality`; `npm run build`.

---

## Fase 1 — Piloto: Transactions + Incomes

### T1.1 — `TransactionController::datatable` + rota + config
Rota `transactions.datatable`; `datatable()` autoriza `viewAny`, monta query, delega ao `DatatableService`. `index()` passa a devolver shell + `accounts`/`categories`/`tags` (opções de select), sem dados paginados.
- **Testes:** `TransactionDatatableEndpointTest` (auth, 404 cross-workspace, JSON `{data,meta}`).
- **Gate:** `php artisan test --filter=TransactionDatatableEndpointTest`.

### T1.2 — Migrar `Transactions/Index.tsx` para DataTable
Colunas conforme design (§2.6). Remove filtro/paginação inline (589 linhas → enxuto). Ações de linha (Pagar/Desmarcar/Editar/Excluir) preservadas via `cell`.
- **Atualizar testes:** `TransactionFilteringTest` → JSON endpoint + filtro FK por **uuid**.
- **Gate:** `php artisan test --filter=Transaction`; `npm run build`; Cypress despesas.

### T1.3 — `IncomeController::datatable` + rota + config
Igual T1.1 + filtros `origin` e `recurrence` (oculto). Query base `where('type', Income)`.
- **Testes:** `IncomeDatatableEndpointTest` (origin, recurrence, shape).
- **Gate:** `php artisan test --filter=IncomeDatatableEndpointTest`.

### T1.4 — Migrar `Incomes/Index.tsx` para DataTable
Colunas + badge "Recorrente" + filtro origin + delete com escopo (Dialog) preservado.
- **Atualizar testes:** `IncomeFilteringTest` → JSON + uuid.
- **Gate:** `php artisan test --filter=Income`; `npm run build`; Cypress receitas.

---

## Fase 2 — Recurrences + Members

### T2.1 — `RecurrenceController::datatable` + rota + config + paginação
Adiciona paginação (hoje `get()`). Sort `next_date` (NULLS last). Filtros text/number/date/select/relation. `RecurrenceResource`: `id` → `uuid`.
- **Testes:** `RecurrenceDatatableEndpointTest` + ajustar `RecurrenceManagementTest` (`has('recurrences', 2)` → shape paginado/`uuid`).
- **Gate:** `php artisan test --filter=Recurrence`.

### T2.2 — Migrar `Recurrences/Index.tsx` para DataTable
Colunas + badge status + ações (Editar/Pausar/Gerar/Ver instâncias). Lê `uuid`.
- **Gate:** `npm run build`; Cypress recorrências.

### T2.3 — `WorkspaceMemberController::datatable` + `MemberResource`
Rota `members.datatable`; adota `MemberResource` (remove mapeamento inline — violação D-01). Filtros text (name/email) + select (role).
- **Testes:** `MemberDatatableEndpointTest` (shape `user`/`role`, auth `viewMembers`).
- **Gate:** `php artisan test --filter=Member`.

### T2.4 — Migrar `Workspace/Members.tsx` para DataTable
Colunas avatar+nome, email, role badge, dropdown ações. Convites (`PendingInvitesList`) permanecem fora da tabela.
- **Gate:** `npm run build`; Cypress membros.

---

## Fase 3 — Limpeza + verificação final

### T3.1 — Remover código morto / verificar zero duplicação
Confirmar que não sobrou filtro/paginação inline em Transactions/Incomes; que `dangerouslySetInnerHTML` de links paginados sumiu.
- **Gate:** grep por `dangerouslySetInnerHTML` em Pages de listagem = 0.

### T3.2 — Gates finais
- `php artisan test` (suíte inteira) verde
- `composer quality` verde
- `npm run quality` verde
- `npm run build` verde
- Cypress E2E verde

---

## Dependências

```
T0.1 ─ T0.2 ─ T0.3 ─ T0.4 ─ T0.5 ─ T0.6
                                     │
                    ┌────────────────┴────────────────┐
                    T1.1 ─ T1.2                      T2.1 ─ T2.2
                    T1.3 ─ T1.4                      T2.3 ─ T2.4
                                     │
                          T3.1 ─ T3.2
```

T1.* e T2.* são paralelizáveis entre si após T0.6.

## Notas de migração (riscos vivos)

- **Filtro FK uuid vs int:** hoje quebrado em produção (UI manda uuid, banco é int). `Filter::relation` corrige; testes passam a usar uuid.
- **`RecurrenceResource` `id`→`uuid`:** quebra frontend/tests existentes — atualizar junto (T2.1).
- **Members sem Resource:** adota `MemberResource` (D-01).
- **Filtros em state local, não URL:** deep-link `recurrence` preservado via `initialFilters`; demais filtros resetam ao navegar (aceito v1).
