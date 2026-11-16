# Feature: ExpenseGroup — Estratificação Macro de Gastos

Issue: `[Feature] Estratificação Macro de Gastos (ExpenseGroup) e Visualizações no Dashboard`

## Decisões de Design

- **Sem seed automático**: nenhum grupo padrão criado ao criar workspace.
- **Resolução do grupo efetivo** (transação): `transactions.expense_group_id` → senão `categories.expense_group_id` → senão `null` ("Sem Grupo"). Implementado como `Transaction::effectiveExpenseGroup()` e computado no `TransactionResource` (`effective_expense_group`).
- **CRUD de grupos**: página dedicada `Pages/ExpenseGroups/{Index,Create,Edit}` (espelha Categories) + item "Grupos de Despesa" na sidebar (seção Categorização) + card/atalho na tela de Categorias. Rotas `expense-groups.*` no prefixo `w/{workspace}`.
- **Filtro "Grupo" no datatable de despesas**: `Filter::select` com closure custom — `none` = `whereNull(transactions.expense_group_id)` + categoria sem grupo; uuid do grupo = match direto OU (herdado da categoria).
- **Totalizadores reagindo a filtros**: `DatatableService::paginate` aceita closure opcional de totais; `transactions.datatable` retorna `meta.totals` (total/paid/pending com filtros aplicados); `Transactions/Index` exibe totais ao vivo via novo callback `onMetaChange` do `DataTable`.
- **Dashboard**: rota `dashboard` migrada de closure para `DashboardController@index` (nome mantido). `DashboardService` agrega por grupo efetivo (inclui bucket "Sem Grupo") e ranking de categorias do mês. Charts em Tailwind puro (barra empilhada + barras horizontais) — sem lib de gráfico. KPI cards também recebem valores reais.
- **Sem categoria**: `transactions.category_id` é NOT NULL (não alterar).
- Idioma UI: pt-BR. Código: inglês.

## Tarefas (execução sequencial, cada uma verificada)

1. **Backend ExpenseGroup** — migrations (`expense_groups`, colunas nullable `expense_group_id` nullOnDelete em `categories` e `transactions`), Model + Factory, relações (`Workspace::expenseGroups()`, `Category::expenseGroup()`, `Transaction::expenseGroup()`), `ExpenseGroupResource`, `Store/UpdateExpenseGroupRequest`, `ExpenseGroupService`, `ExpenseGroupPolicy`, `ExpenseGroupController` (resource completo, Toast), rotas, feature tests (CRUD, nullOnDelete, isolamento por workspace, smoke).
2. **Frontend gestão de grupos** — páginas `ExpenseGroups/{Index,Create,Edit}` (ColorPicker), item na AppSidebar, card/atalho em `Categories/Index`.
3. **Vínculo em categorias** — fillable/requests/service/Resource, eager loads, Select de grupo em `Categories/{Create,Edit}`, indicador de grupo em `Categories/Index`, tests.
4. **Override em despesas + coluna/filtro Grupo** — `TransactionResource` (`expense_group`, `effective_expense_group`), requests/service passthrough, filtro `expense_group` no datatable, `meta.totals` filtrado, coluna "Grupo" com bolinha colorida + tooltip, filtro select (grupos + "Sem Grupo"), `buildActiveFilters`, seletor com herança/override em `Transactions/{Create,Edit}`, tests.
5. **Dashboard** — `DashboardController` + `DashboardService` (KPIs, breakdown por grupo efetivo com % e cores, ranking de categorias), `Home.tsx` + `Components/Dashboard/*` (empty state com CTA), tests (agregação, mês, smoke).
6. **E2E Cypress** — specs: CRUD de grupos + toasts; override/herança na tabela de despesas + filtro; dashboard com estratificação.
7. **Verificação final** — `php artisan test`, `composer quality`, `npm run quality`, correções.

## Status

- [x] 1. Backend ExpenseGroup — migrations, model/factory/relações, Resource, FormRequests, Service, Policy, Controller, rotas, feature tests (CRUD, nullOnDelete, isolamento, smoke)
- [x] 2. Frontend gestão de grupos — `ExpenseGroups/{Index,Create,Edit}`, sidebar, card/atalho em Categories
- [x] 3. Vínculo em categorias — Select em `Categories/{Create,Edit}`, indicador no card, tests
- [x] 4. Override em despesas + coluna/filtro Grupo — herança/override, filtro com "Sem Grupo", `meta.totals` filtrado, seletor em `Transactions/{Create,Edit}`, tests
- [x] 5. Dashboard — `DashboardController` + `DashboardService` (KPIs, agregação por grupo efetivo, ranking), `Home.tsx` com charts e empty state, tests
- [x] 6. E2E Cypress — `expense-groups/crud.cy.js` (5), `transactions/expense-group.cy.js` (8), `dashboard/expense-groups.cy.js` (5)
- [x] 7. Verificação final — gates verdes: PHPUnit 799/799, Cypress 167/167, `composer quality`, `npm run quality`

### Ajustes feitos na verificação final (Task 7)

A adição da coluna "Grupo" como primeira coluna da tabela de Despesas deslocou os
índices posicionais de select-triggers usados por specs E2E pré-existentes. Specs
foram atualizadas para localizar o filtro pelo **texto do header da coluna**
(helper `openColumnFilter(columnName)`) em vez de índice posicional — robusto a
mudanças de ordem de colunas:

- `cypress/e2e/datatable/state-persistence.cy.js` — 4 seletores posicionais substituídos
- `cypress/e2e/cards/card-expense-guard.cy.js` — 2 seletores posicionais substituídos (Cartão, Fatura)
- Demais usos de `select-trigger` auditados: diálogos/outras tabelas, não afetados

Nota de ambiente: rodar `php artisan migrate` (DB E2E MySQL) e `npm run build`
(manifest Vite) antes do `npx cypress run`; o binary do Cypress exige `npx cypress install`.

## Convenções aplicadas

- UUIDs em rotas; ApiResource obrigatório; FormRequests por ação; Services para lógica; Policy por model; Toast (Sonner/flash) nas mutações; factories exigem `workspace_id`/`created_by` explícitos; smoke test por rota GET.
