# DataTable State Persistence — Spec

**Story ID:** DTBL-02
**Phase:** P1 — Cross-cutting infra (MVP Core)
**Parent:** `.specs/project/PROJECT.md`
**Design:** `.specs/features/datatable-state-persistence/design.md`
**Depends:** DTBL-01 (`.specs/features/datatable/`)

## Contexto

Atualmente os filtros, ordenação e paginação vivem exclusivamente no state React local do hook `useDataTable`. Quando o usuário navega para outra tela e volta, o estado da tabela é perdido — tudo volta ao padrão. Além disso, não há como injetar filtros programaticamente (ex.: URL com `?status=paid&category=uuid` para botões de "ação rápida").

Objetivo: persistir o estado de filtros/ordenação na sessão do Laravel (por tabela), hidratar no primeiro render, e permitir sobrescrita programática via query params da URL.

## Escopo

- **Dentro:** Persistência de filtros + ordenação (não paginação) na sessão; isolamento por tabela; sobrescrita via URL; botão "Limpar Filtros"; feedback visual de filtros ativos.
- **Fora:** Paginação (page/per_page) continua efêmera; card grids (Accounts/Cards/Categories/Tags) não são afetados.

## Requisitos

### DTBL-02 — Persistência de estado na sessão
A cada requisição ao endpoint JSON da DataTable, o backend deve salvar os filtros e ordenação aplicados na sessão, vinculados a um identificador único por tabela (ex.: `datatable.transactions`).

**AC:** filtros e ordenação persistem entre navegações; isolar por tabela.

### DTBL-03 — Hidratação do estado no carregamento
Quando o `index` Inertia carrega sem novos query params na URL, o backend deve ler o estado salvo na sessão e injetá-lo como estado inicial da DataTable.

**AC:** ao voltar à tabela, filtros visuais (inputs preenchidos) e ordenação (seta na coluna) refletem o estado persistido.

### DTBL-04 — Prioridade: Request > Sessão
Parâmetros explícitos na request (query params) devem sobrescrever o estado da sessão. O novo estado consolidado deve ser salvo na sessão após a sobrescrita.

**AC:** acessar `?status=paid` via URL substitui qualquer filtro de status anterior na sessão; a interface reflete o novo estado.

### DTBL-05 — Limpar Filtros (Reset)
Ação explícita "Limpar Filtros" que reseta o estado local, limpa a chave da sessão para aquela tabela, e recarrega os dados padrão.

**AC:** botão visível quando há filtros ativos; ao clicar, inputs limpam, ordenação volta ao padrão, sessão é limpa.

### DTBL-06 — Feedback visual de filtros ativos
Exibir um resumo dos filtros ativos (badges com labels) acima da tabela, permitindo ao usuário ver e remover filtros individuais ou todos de uma vez.

**AC:** badges aparecem quando filtros estão ativos; cada badge tem botão de remover individual; botão "Limpar Filtros" remove todos.

### DTBL-07 — Endpoint de limpeza de sessão
Endpoint dedicado para limpar o estado persistido de uma tabela específica (`DELETE /w/{workspace}/datatable/{entity}/state`).

**AC:** endpoint autenticado, autorizado, limpa apenas a sessão da tabela especificada.

---

## Edge Cases

- WHEN a session não tem estado para a tabela THEN o backend deve usar o padrão da config (default sort).
- WHEN query params contêm filtros inválidos THEN o backend deve ignorar (validação existente do DatatableService).
- WHEN dois usuários compartilham a mesma sessão (impossível por design) THEN estados são independentes (sessão por usuário).
- WHEN o usuário limpa filtros com a tabela vazia THEN o estado é limpo normalmente; nenhum erro.
- WHEN a URL tem query params parciais (ex.: só `sort`) THEN apenas o que foi enviado sobrescreve; resto vem da sessão.
- WHEN navegação ocorre via Inertia Link THEN session state é hidratado no `index`.
- WHEN o usuário abre em outra aba THEN mesma sessão, mesmo estado (comportamento esperado).

---

## Requirement Traceability

| ID       | Requirement | Phase | Status |
|----------|-------------|-------|--------|
| DTBL-02  | Persistência na sessão | Todo | ⬜ |
| DTBL-03  | Hidratação no carregamento | Todo | ⬜ |
| DTBL-04  | Prioridade Request > Sessão | Todo | ⬜ |
| DTBL-05  | Limpar Filtros | Todo | ⬜ |
| DTBL-06  | Feedback visual (badges) | Todo | ⬜ |
| DTBL-07  | Endpoint de limpeza | Todo | ⬜ |

**Coverage:** 7 requirements, 7 mapped, 0 unmapped

---

## Success Criteria

- [ ] Filtros e ordenação persistem ao navegar e voltar à tabela
- [ ] Estado é isolado por tabela (transactions ≠ incomes ≠ recurrences)
- [ ] Query params na URL sobrescrevem a sessão e salvam o novo estado
- [ ] Botão "Limpar Filtros" reseta visual + sessão
- [ ] Badges de filtros ativos são exibidos e removíveis individualmente
- [ ] Endpoint de limpeza funciona e é autorizado
- [ ] Testes backend (PHPUnit) cobrem todos os cenários de prioridade
- [ ] Testes E2E (Cypress) cobrem persistência, sobrescrita, e reset
- [ ] Quality gates green: `composer quality` + `npm run quality`

---

## Não-funcionais

- UI em pt-BR, código em inglês.
- TypeScript strict, sem `.js`/`.jsx` novos.
- Session storage no Laravel (driver padrão — file/redis).
- Qualidade: `composer quality` + `npm run quality` verdes antes de concluir.
- TDD-first: testes de feature (PHPUnit) + E2E (Cypress) escritos antes/durante a implementação.
