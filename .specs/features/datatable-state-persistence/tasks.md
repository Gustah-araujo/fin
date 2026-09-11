# DataTable State Persistence — Tasks

**Story ID:** DTBL-02
**Spec:** `.specs/features/datatable-state-persistence/spec.md`
**Design:** `.specs/features/datatable-state-persistence/design.md`

TDD-first. Cada task lista teste + gate. Ordem topológica: backend (serviço + trait + controller) → frontend (hook + componente + pages) → E2E.

---

## Fase 0 — Backend: Serviço de Estado + Trait

### T0.1 — `TableStateService` (TDD)

**Testes primeiro:**
- `tests/Feature/Datatable/TableStateServiceTest.php`:
  - `test_save_persists_filters_and_sort_to_session`
  - `test_restore_returns_saved_state`
  - `test_restore_returns_default_when_no_session`
  - `test_clear_removes_state_from_session`
  - `test_session_key_generates_correct_prefix`

**Implementação:**
- `app/Services/Datatable/TableStateService.php` com `save()`, `restore()`, `clear()`, `sessionKey()`.

**Gate:** `php artisan test --filter=TableStateServiceTest` verde; `composer quality`.

### T0.2 — Trait `PersistsTableState` + `filterKeys()` no `DatatableConfig`

**Testes primeiro:**
- `tests/Feature/Datatable/PersistsTableStateTest.php`:
  - `test_resolve_uses_session_when_no_request_params`
  - `test_resolve_request_params_override_session`
  - `test_resolve_partial_request_overrides_only_provided`
  - `test_resolve_saves_consolidated_state_to_session`

**Implementação:**
- `app/Http/Controllers/Concerns/PersistsTableState.php` com `resolveTableState()` e `extractTableParamsFromRequest()`.
- Adicionar `filterKeys()` em `app/Services/Datatable/DatatableConfig.php`.

**Gate:** `php artisan test --filter=PersistsTableStateTest` verde; `composer quality`.

### T0.3 — Controller `DatatableStateController` + Rota

**Testes primeiro:**
- `tests/Feature/Datatable/DatatableStateControllerTest.php`:
  - `test_destroy_clears_state_for_entity`
  - `test_destroy_requires_authentication`
  - `test_destroy_returns_404_for_invalid_entity`
  - `test_destroy_requires_workspace_membership`

**Implementação:**
- `app/Http/Controllers/DatatableStateController.php` com método `destroy()`.
- Rota: `Route::delete('datatable/{entity}/state', ...)->name('datatable.state.destroy')` em `routes/web.php`.

**Gate:** `php artisan test --filter=DatatableStateControllerTest` verde; `composer quality`.

---

## Fase 1 — Backend: Integração nos Controllers de DataTable

### T1.1 — Integrar `TransactionController` com persistência

**Testes:**
- `tests/Feature/Datatable/TransactionStatePersistenceTest.php`:
  - `test_index_returns_initial_state_from_session`
  - `test_datatable_saves_state_to_session`
  - `test_datatable_restores_state_when_no_params`
  - `test_datatable_request_params_override_session`
  - `test_datatable_partial_params_override_session`
  - `test_state_is_isolated_per_table`

**Implementação:**
- Modificar `TransactionController::index()` para ler estado da sessão e passar `initialState`.
- Modificar `TransactionController::datatable()` para usar `resolveTableState()`.

**Gate:** `php artisan test --filter=TransactionStatePersistence` verde.

### T1.2 — Integrar `IncomeController` com persistência

**Testes:**
- `tests/Feature/Datatable/IncomeStatePersistenceTest.php`:
  - `test_index_returns_initial_state_from_session`
  - `test_datatable_saves_state_to_session`
  - `test_datatable_restores_state_when_no_params`
  - `test_datatable_request_params_override_session`
  - `test_origin_filter_persists_correctly`

**Implementação:**
- Modificar `IncomeController::index()` e `IncomeController::datatable()`.

**Gate:** `php artisan test --filter=IncomeStatePersistence` verde.

### T1.3 — Integrar `RecurrenceController` com persistência

**Testes:**
- `tests/Feature/Datatable/RecurrenceStatePersistenceTest.php`:
  - `test_index_returns_initial_state_from_session`
  - `test_datatable_saves_state_to_session`
  - `test_datatable_restores_state_when_no_params`
  - `test_datatable_request_params_override_session`

**Implementação:**
- Modificar `RecurrenceController::index()` e `RecurrenceController::datatable()`.

**Gate:** `php artisan test --filter=RecurrenceStatePersistence` verde.

---

## Fase 2 — Frontend: Hook + Componente

### T2.1 — Tipos `TableState` + modificação `useDataTable`

**Implementação:**
- Adicionar `TableState` em `resources/js/types/datatable.ts`.
- Modificar `useDataTable` para aceitar `initialState?: TableState` e `onStateChange?: (state: DataTableParams) => void`.
- Inicializar `params` com `initialState` se fornecido.
- Chamar `onStateChange` (debounced 300ms) quando params mudam.

**Gate:** `npx tsc --noEmit` limpo; `npm run quality`.

### T2.2 — Componente `ActiveFilters`

**Implementação:**
- `resources/js/Components/DataTable/ActiveFilters.tsx`:
  - Props: `filters: ActiveFilter[]`, `onRemoveFilter(key)`, `onClearAll()`.
  - Renderiza badges (shadcn `Badge`) com botão X.
  - Botão "Limpar Filtros" visível quando há filtros.

**Gate:** `npm run quality`; `npm run build`.

### T2.3 — Modificar `DataTable` para suportar `initialState` + `onStateChange`

**Implementação:**
- Adicionar props `initialState?: TableState` e `onStateChange?: (state) => void`.
- Passar para `useDataTable`.
- Passar `activeFilters` para o consumer (page) via callback ou render prop.

**Gate:** `npm run quality`; `npm run build`.

---

## Fase 3 — Frontend: Integração nas Pages

### T3.1 — Integrar `Transactions/Index.tsx`

**Implementação:**
- Receber `initialState` como prop.
- Passar para `<DataTable>`.
- Renderizar `<ActiveFilters>` com badges.
- Implementar `handleClearFilters()` com DELETE ao endpoint.
- Implementar `handleRemoveFilter(key)` com `setFilter(key, '')`.

**Gate:** `npm run build`; Cypress despesas smoke.

### T3.2 — Integrar `Incomes/Index.tsx`

**Implementação:**
- Idem T3.1 para incomes.
- Labels de filtro `origin` (Recorrente/Avulsa).

**Gate:** `npm run build`; Cypress receitas smoke.

### T3.3 — Integrar `Recurrences/Index.tsx`

**Implementação:**
- Idem T3.1 para recurrences.
- Labels de filtro `status` (Ativa/Pausada/Esgotada).

**Gate:** `npm run build`; Cypress recorrências smoke.

---

## Fase 4 — E2E + Verificação Final

### T4.1 — Cypress E2E: State Persistence

**Testes:**
- `e2e/datatable/state-persistence.cy.js`:
  - `test_persist_filters_across_navigation`
  - `test_programmatic_override_via_url`
  - `test_clear_filters_button`
  - `test_individual_filter_removal`
  - `test_state_isolation_between_tables`

**Gate:** Cypress E2E verde.

### T4.2 — Gates finais

- `php artisan test` (suíte inteira) verde
- `composer quality` verde
- `npm run quality` verde
- `npm run build` verde

---

## Dependências

```
T0.1 ─ T0.2 ─ T0.3
               │
     ┌─────────┼─────────┐
     T1.1      T1.2      T1.3
     (parallel após T0.3) │
               │
              T2.1 ─ T2.2 ─ T2.3
                          │
                ┌─────────┼─────────┐
               T3.1      T3.2      T3.3
               (parallel após T2.3) │
                          │
                    T4.1 ─ T4.2
```

T1.1, T1.2, T1.3 são paralelizáveis entre si após T0.3.
T3.1, T3.2, T3.3 são paralelizáveis entre si após T2.3.

---

## Notas de implementação

- **Filtros com sufixo** (`_min`, `_max`, `_from`, `_to`): o `extractTableParamsFromRequest` deve ler keys com sufixo e agrupar no array de filtros. O `ActiveFilters` deve exibir label amigável (ex.: "Valor: 100 - 500").
- **Labels de filtros FK**: para `select` filters, o label do badge é a opção correspondente ao valor (resolvido via props `options` da page).
- **Session key**: usar `datatable.{entity}` (ex.: `datatable.transactions`).
- **Namespace de query params**: evitar conflito entre filtros de tabela e outros params (ex.: `page` de paginação). Filtros sempre usam keys declarados no `DatatableConfig`.
