# Planejamento Futuro Specification

## Problem Statement

Usuários tomam decisões financeiras no presente sem visibilidade do futuro. Ao receber o salário, planejam gastos manualmente, mas ao longo do mês perdem a noção do quanto já está comprometido. Parcelas de cartão, recorrências e despesas futuras criam obrigações invisíveis que dificultam decidir se um novo gasto é viável.

O sistema precisa projetar automaticamente gastos e rendas futuras com base nas transações já cadastradas, permitindo que o usuário veja o impacto de decisões antes de tomá-las.

## Goals

- [ ] Usuário consegue ver projeção de gastos e rendas mês-a-mês com base em transações existentes
- [ ] Usuário consegue selecionar período de projeção flexível (data início/fim ou presets)
- [ ] Usuário consegue ver detalhamento de um mês específico (quais despesas/receitas compõem o total)
- [ ] Projeção considera 100% das transações (avulsas, parcelas, recorrências) sem necessidade de cadastro adicional

## Out of Scope

| Feature                          | Reason                                              |
| -------------------------------- | --------------------------------------------------- |
| Simulação "e se..."              | Decisão manual do usuário com base na projeção      |
| Orçamento por categoria          | Segunda fase de desenvolvimento                     |
| Entidade "despesa planejada"     | Projeção 100% derivada de transações existentes     |
| Saldo projetado considerando contas | Foco em fluxo (renda - gasto), não saldo acumulado |

---

## User Stories

### P1: Ver Projeção Mês-a-Mês ⭐ MVP

**User Story**: Como usuário, quero ver uma tabela com gastos, rendas e saldo projetado mês-a-mês para entender meu fluxo de caixa futuro.

**Why P1**: Visão macro essencial para tomada de decisão.

**Acceptance Criteria**:

1. WHEN usuário acessa `/w/{workspace}/planning` THEN sistema SHALL exibir tabela com colunas: Mês, Gastos, Rendas, Saldo
2. WHEN tabela é renderizada THEN sistema SHALL calcular Gastos como soma de todas transações type=Expense no mês (incluindo parcelas e recorrências)
3. WHEN tabela é renderizada THEN sistema SHALL calcular Rendas como soma de todas transações type=Income no mês (incluindo recorrências)
4. WHEN tabela é renderizada THEN sistema SHALL calcular Saldo como Rendas - Gastos
5. WHEN transação possui data futura THEN sistema SHALL incluí-la no mês correspondente à data
6. WHEN transação é parcela de cartão THEN sistema SHALL incluí-la no mês da data de vencimento da parcela

**Independent Test**: Acessar `/planning`, ver tabela com pelo menos 6 meses, valores batem com soma manual de transações.

---

### P1: Selecionar Período de Projeção

**User Story**: Como usuário, quero escolher o período da projeção (data início/fim ou presets) para focar no horizonte relevante.

**Why P1**: Flexibilidade para diferentes necessidades de planejamento.

**Acceptance Criteria**:

1. WHEN usuário acessa `/planning` THEN sistema SHALL exibir seletor de período com presets: "Próximo mês", "Próximos 3 meses", "Próximos 6 meses", "Próximos 9 meses", "Próximos 12 meses"
2. WHEN usuário seleciona preset THEN sistema SHALL recalcular tabela para o período escolhido (mês atual + N meses)
3. WHEN usuário clica em "Personalizado" THEN sistema SHALL exibir dois date pickers (data início, data fim)
4. WHEN usuário define datas customizadas THEN sistema SHALL recalcular tabela apenas para meses dentro do intervalo
5. WHEN data início é anterior ao mês atual THEN sistema SHALL permitir (projeção histórica também válida)
6. WHEN data fim é anterior à data início THEN sistema SHALL exibir erro de validação

**Independent Test**: Selecionar "Próximos 3 meses", ver tabela com 3 linhas. Selecionar "Personalizado" com datas específicas, ver tabela ajustada.

---

### P1: Ver Detalhamento de Mês Específico

**User Story**: Como usuário, quero clicar em um mês da tabela e ver quais despesas/receitas compõem aquele total para entender a composição.

**Why P1**: Macro sem detalhe não permite ação concreta.

**Acceptance Criteria**:

1. WHEN usuário clica em linha da tabela (ex: "Set/26") THEN sistema SHALL abrir modal com detalhamento daquele mês
2. WHEN modal abre THEN sistema SHALL listar todas transações type=Expense do mês com: descrição, valor, categoria, data
3. WHEN modal abre THEN sistema SHALL listar todas transações type=Income do mês com: descrição, valor, categoria, data
4. WHEN modal exibe lista THEN sistema SHALL agrupar por tipo (Gastos / Rendas) com subtotal
5. WHEN modal exibe lista THEN sistema SHALL ordenar transações por data (ascendente)
6. WHEN usuário clica fora do modal ou em "Fechar" THEN sistema SHALL fechar modal e retornar à tabela

**Independent Test**: Clicar em mês com parcelas de cartão, ver lista com cada parcela individual. Clicar em mês com recorrências, ver transações geradas.

---

### P1: Ver Totais Agregados

**User Story**: Como usuário, quero ver cards no topo com totais de gastos, rendas e saldo do período selecionado para ter visão rápida do compromisso.

**Why P1**: Resumo executivo sem precisar somar mentalmente.

**Acceptance Criteria**:

1. WHEN página carrega THEN sistema SHALL exibir 3 cards: "Gastos Comprometidos", "Rendas Programadas", "Saldo Projetado"
2. WHEN cards são renderizados THEN sistema SHALL calcular "Gastos Comprometidos" como soma de todos gastos no período
3. WHEN cards são renderizados THEN sistema SHALL calcular "Rendas Programadas" como soma de todas rendas no período
4. WHEN cards são renderizados THEN sistema SHALL calcular "Saldo Projetado" como Rendas - Gastos
5. WHEN usuário muda período selecionado THEN sistema SHALL recalcular cards automaticamente
6. WHEN saldo projetado é negativo THEN sistema SHALL exibir valor em vermelho (indicador visual de risco)

**Independent Test**: Ver cards no topo, valores batem com soma da tabela. Mudar período, ver cards atualizarem.

---

### P2: Filtrar por Tipo na Projeção

**User Story**: Como usuário, quero filtrar a projeção para ver apenas gastos ou apenas rendas para focar na análise específica.

**Why P2**: Análise focada sem ruído da outra categoria.

**Acceptance Criteria**:

1. WHEN usuário acessa `/planning` THEN sistema SHALL exibir toggle/filtro: "Todos", "Apenas Gastos", "Apenas Rendas"
2. WHEN usuário seleciona "Apenas Gastos" THEN sistema SHALL ocultar coluna "Rendas" da tabela e recalcular saldo como 0 - Gastos
3. WHEN usuário seleciona "Apenas Rendas" THEN sistema SHALL ocultar coluna "Gastos" da tabela e recalcular saldo como Rendas - 0
4. WHEN usuário seleciona "Todos" THEN sistema SHALL exibir todas colunas normalmente
5. WHEN filtro é aplicado THEN sistema SHALL atualizar cards do topo para refletir apenas o tipo selecionado

**Independent Test**: Selecionar "Apenas Gastos", ver tabela sem coluna Rendas. Selecionar "Apenas Rendas", ver tabela sem coluna Gastos.

---

### P3: Orçamento por Categoria (Fase 2)

**User Story**: Como usuário, quero definir orçamento por categoria e ver quanto já está comprometido vs disponível para tomar decisões granulares.

**Why P3**: Refinamento pós-MVP. Foco macro primeiro.

**Acceptance Criteria**:

1. WHEN usuário acessa `/planning` THEN sistema SHALL exibir opção "Ver por categoria"
2. WHEN usuário clica "Ver por categoria" THEN sistema SHALL exibir breakdown por categoria dentro do modal de mês
3. WHEN categoria possui orçamento definido THEN sistema SHALL exibir barra de progresso (comprometido vs disponível)
4. WHEN categoria não possui orçamento THEN sistema SHALL exibir apenas valor comprometido

**Independent Test**: Definir orçamento de R$500 para "Mercado", ver barra de progresso no modal.

---

## Edge Cases

- WHEN mês não possui transações THEN sistema SHALL exibir linha com Gastos=R$0, Rendas=R$0, Saldo=R$0
- WHEN todas transações são avulsas (sem parcelas/recorrências) THEN sistema SHALL projetar normalmente com base nas datas
- WHEN usuário possui parcelas de cartões diferentes THEN sistema SHALL somar todas parcelas do mês independente do cartão
- WHEN recorrência está pausada THEN sistema SHALL NÃO incluir transações futuras da recorrência pausada
- WHEN recorrência possui `until_date` no passado THEN sistema SHALL NÃO incluir transações além do `until_date`
- WHEN transação possui data no mês atual e já foi paga THEN sistema SHALL incluir na projeção (já foi gasto/recebido)
- WHEN transação possui data no mês atual e NÃO foi paga THEN sistema SHALL incluir na projeção (compromisso pendente)
- WHEN período selecionado possui mais de 24 meses THEN sistema SHALL limitar a 24 meses (performance)
- WHEN workspace não possui transações THEN sistema SHALL exibir tabela vazia com mensagem "Nenhuma transação cadastrada"

---

## Requirement Traceability

| Requirement ID | Story                              | Phase  | Status |
| -------------- | ---------------------------------- | ------ | ------ |
| PLAN-01        | P1: Ver Projeção Mês-a-Mês         | Done   | ✅     |
| PLAN-02        | P1: Selecionar Período             | Done   | ✅     |
| PLAN-03        | P1: Ver Detalhamento de Mês        | Done   | ✅     |
| PLAN-04        | P1: Ver Totais Agregados           | Done   | ✅     |
| PLAN-05        | P2: Filtrar por Tipo               | Done   | ✅     |
| PLAN-06        | P3: Orçamento por Categoria        | -      | ⏳     |

**Coverage:** 6 total, 5 mapped to tasks, 1 deferred (P3) ✅

---

## Success Criteria

- [ ] Usuário consegue ver projeção completa em < 2 segundos
- [ ] Valores projetados batem 100% com soma manual de transações
- [ ] Usuário consegue identificar mês com maior compromisso em < 5 segundos
- [ ] Modal de detalhamento exibe todas transações do mês sem truncamento
- [ ] Zero erros de cálculo em cenários com parcelas + recorrências + avulsas
