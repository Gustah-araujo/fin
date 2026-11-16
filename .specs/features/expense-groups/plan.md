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

## Convenções aplicadas

- UUIDs em rotas; ApiResource obrigatório; FormRequests por ação; Services para lógica; Policy por model; Toast (Sonner/flash) nas mutações; factories exigem `workspace_id`/`created_by` explícitos; smoke test por rota GET.
