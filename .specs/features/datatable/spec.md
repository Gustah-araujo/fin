# DataTable — Spec

**Story ID:** DTBL-01
**Phase:** P1 — Cross-cutting infra (MVP Core)
**Parent:** `.specs/project/PROJECT.md`
**Design:** `.specs/features/datatable/design.md`

## Contexto

Hoje cada tela de listagem implementa seu próprio render de linhas (stacked `<Card>`/div), filtros e paginação duplicados. Só Transactions e Incomes paginam/filtram (lógica copiada 2×); Recurrences e Members não paginam nem filtram. Não existe `<table>` nem primitiva shadcn de tabela no projeto.

Objetivo: um componente DataTable reutilizável que carrega dados de forma lazy via endpoint JSON, com filtro por coluna e ordenação/paginação server-side, e migrar todas as listas **tabulares** para ele.

## Escopo

- **Dentro:** DataTable genérico (React + shadcn Table), hook de fetching, serviço backend de formatação, endpoints JSON dedicados, migração das listas tabulares (Transactions, Incomes, Recurrences, Members).
- **Fora:** Card grids (Accounts, Cards, Categories, Tags) — permanecem como estão. Listas aninhadas (Bills/Show, Cards/Show) — fora por enquanto.

## Requisitos

### DTBL-01 — Componente DataTable reutilizável
Componente React genérico (`DataTable<T>`), TypeScript strict, construído com primitivas shadcn/ui (`Table`, `Button`, `Input`, `Select`, `Badge`). Recebe colunas declarativas e renderiza thead + tbody.

**AC:** aceita uma definição de colunas tipada; renderiza header, corpo e footer de paginação; sem `<Card>`-por-linha.

### DTBL-02 — Carregamento lazy via endpoint JSON
A DataTable busca seus dados on-mount (e a cada mudança de filtro/sort/página) via requisição HTTP a um endpoint que retorna JSON padronizado. A página Inertia renderiza apenas o shell (layout, títulos, opções de select), sem dados.

**AC:** primeira renderização não recebe linhas via props Inertia; dados chegam por chamada assíncrona.

### DTBL-03 — Filtro por coluna (linha na thead)
Cada coluna pode declarar um filtro. Filtros são renderizados na **2ª linha do `<thead>`**, abaixo da linha de títulos, antes das rows de dados.

**AC:** filtros aparecem em linha dedicada no header, alinhados sob suas colunas.

### DTBL-04 — Tipos de filtro por coluna
Cada coluna aceita um tipo de filtro dentre: `text` (busca textual), `number` (range min/máx), `date` (range de/até), `select` (valor único dentre opções).

**AC:** os 4 tipos implementados; cada um envia os parâmetros corretos ao backend.

### DTBL-05 — Colunas FK usam select com label
Colunas que referenciam outra entidade (FK — ex.: `category`, `account`) usam sempre filtro `select` com as opções da entidade referenciada, exibindo nome/label. O usuário nunca vê IDs nem UUIDs na tela (valor interno é o UUID, exibido é o label).

**AC:** filtro de categoria/conta mostra nomes; nenhum UUID/ID visível no UI.

### DTBL-06 — Ordenação server-side
Clique no header de coluna `sortable` alterna asc/desc. A ordenação é executada no backend sobre whitelist de colunas.

**AC:** clicar alterna direção; backend aplica sort válido; colunas não-sortables ignoram clique.

### DTBL-07 — Paginação server-side
Paginação no backend (25 por página, configurável por entidade), footer com controles prev/next e indicador de página/total.

**AC:** footer exibe página atual e total; troca de página refaz a query JSON.

### DTBL-08 — Serviço backend centralizado
Um serviço (`DatatableService`) centraliza: aplicação de busca, filtros, ordenação, paginação e a **formatação da resposta JSON** em contrato único. Cada controller de listagem delega a ele.

**AC:** nenhuma lógica de filtro/paginação/`where` de listagem vive no controller; resposta sempre no mesmo formato.

### DTBL-09 — Contrato JSON padronizado
Toda resposta de datatable segue:

```json
{
  "data": [ /* entity Resource */ ],
  "meta": {
    "current_page": 1, "per_page": 25, "total": 100,
    "last_page": 4, "from": 1, "to": 25
  }
}
```

**AC:** todos os endpoints retornam exatamente este shape.

### DTBL-10 — Endpoints JSON dedicados
Uma rota JSON dedicada por entidade (`transactions.datatable`, `incomes.datatable`, `recurrences.datatable`, `members.datatable`) que retorna o contrato DTBL-09. O `index` Inertia continua renderizando o shell + opções de select.

**AC:** rota existe, retorna JSON, autenticada e autorizada (policy `viewAny`).

### DTBL-11 — Migração das listas tabulares
Transactions, Incomes, Recurrences e Members passam a usar a DataTable. A lógica de filtro/paginação inline duplicada é removida.

**AC:** as 4 telas usam o componente; zero duplicação de filtro/paginação inline.

### DTBL-12 — Correção de filtro FK (uuid vs int)
Colunas FK são `foreignId` (bigint) no banco, mas o frontend envia UUID. O filtro por FK deve resolver o UUID via relacionamento (`whereRelation`/`whereHas`), nunca comparar a string na coluna int.

**AC:** filtro por categoria/conta funciona com UUID enviado pelo frontend; testes cobrem filtro via UUID.

### DTBL-13 — Card grids permanecem
Accounts, Cards, Categories e Tags continuam com seus card grids atuais.

**AC:** nenhuma mudança nessas 4 telas.

## Não-funcionais

- UI em pt-BR, código em inglês.
- TypeScript strict, sem `.js`/`.jsx` novos.
- ApiResource para todo item de `data`.
- Qualidade: `composer quality` + `npm run quality` verdes antes de concluir.
- TDD-first: testes de feature (PHPUnit) + E2E (Cypress) escritos antes/durante a implementação.
