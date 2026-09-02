# DataTable — Design

**Story ID:** DTBL-01
**Spec:** `.specs/features/datatable/spec.md`
**Parent:** `.specs/project/PROJECT.md`

## Resumo

Componente `DataTable<T>` headless-leve (sem TanStack) composto sobre primitivas shadcn/ui. Dados lazy via `axios` contra endpoints JSON dedicados. Backend centraliza query + formatação num `DatatableService`. Filtros tipados por coluna renderizados na 2ª linha do `<thead>`.

---

## 1. Backend

### 1.1 `DatatableService`

Centraliza **tudo** que hoje é duplicado/espalhado nos controllers de listagem.

Localização: `app/Services/Datatable/DatatableService.php` (subpasta `Datatable/` para as classes de suporte).

```php
final class DatatableService
{
    public function paginate(Builder $query, Request $request, DatatableConfig $config): JsonResponse;
}
```

Fluxo interno:

1. **Search** — `where` LIKE `%v%` sobre cada coluna `searchable` (OR entre elas).
2. **Filters** — aplica cada filtro declarado, lendo o request param correspondente.
3. **Sort** — valida contra whitelist `sortable`; aplica `orderBy(col, dir)`; fallback `defaultSort`.
4. **Paginate** — `->paginate($config->perPage())`.
5. **Format** — `response()->json([...])` no contrato DTBL-09.

Resposta (usa `items()` para evitar o `links` automático do paginator do Laravel):

```php
return response()->json([
    'data' => $config->resourceClass()::collection($paginator->items()),
    'meta' => [
        'current_page' => $paginator->currentPage(),
        'per_page'     => $paginator->perPage(),
        'total'        => $paginator->total(),
        'last_page'    => $paginator->lastPage(),
        'from'         => $paginator->firstItem(),
        'to'           => $paginator->lastItem(),
    ],
]);
```

### 1.2 `DatatableConfig` (value object fluente)

```php
DatatableConfig::make(TransactionResource::class)
    ->searchable(['description'])
    ->filter('category', Filter::relation('category', 'uuid'))
    ->filter('account',  Filter::relation('account', 'uuid'))
    ->filter('value',    Filter::numberRange('value'))
    ->filter('date',     Filter::dateRange('date'))
    ->filter('status',   Filter::select(fn (Builder $q, string $v) =>
        $v === 'paid' ? $q->whereNotNull('paid_at') : $q->whereNull('paid_at')))
    ->sortable(['date', 'value', 'description'])
    ->defaultSort('date', 'desc')
    ->perPage(25);
```

Campos: `resourceClass`, `searchable[]`, `filter[]` (mapa key→Filter), `sortable[]`, `defaultSort`, `perPage`.

### 1.3 `Filter` — tipos de filtro

| Tipo | Construção | Param(s) lidos | SQL aplicado |
|------|-----------|----------------|--------------|
| Texto | `Filter::text($col)` | `{key}` | `WHERE $col LIKE %v%` |
| Número (range) | `Filter::numberRange($col)` | `{key}_min`, `{key}_max` | `>=` / `<=` |
| Data (range) | `Filter::dateRange($col)` | `{key}_from`, `{key}_to` | `whereDate >=` / `<=` |
| Select FK | `Filter::relation($relation, $relCol)` | `{key}` | `whereHas($relation, uuid = v)` |
| Select enum | `Filter::select(callable)` | `{key}` | closure arbitrário |

Regras:

- **Select FK** resolve o UUID via `whereHas($relation, fn($q) => $q->where($relCol, $v))` — **nunca** compara string na coluna int. Corrige o bug atual (`category_id`/`account_id` são `foreignId`, UI manda UUID).
- **Range** (number/date): só aplica o lado presente (min e/ou max).
- Param vazio/ausente ⇒ filtro ignorado.
- Todos filtros combinam com **AND** entre si.

### 1.4 Endpoints dedicados

`routes/web.php`, dentro do grupo `/w/{workspace}`:

```php
Route::get('transactions/datatable', [TransactionController::class, 'datatable'])->name('transactions.datatable');
Route::get('incomes/datatable',       [IncomeController::class, 'datatable'])->name('incomes.datatable');
Route::get('recurrences/datatable',   [RecurrenceController::class, 'datatable'])->name('recurrences.datatable');
Route::get('members/datatable',       [WorkspaceMemberController::class, 'datatable'])->name('members.datatable');
```

Método `datatable()` por controller:

```php
public function datatable(Workspace $workspace, Request $request): JsonResponse
{
    Gate::authorize('viewAny', [Transaction::class, $workspace]); // policy existente
    $query = $workspace->transactions()->with(['account','category','tags']);
    return app(DatatableService::class)->paginate($query, $request, $this->datatableConfig());
}
```

O `index()` Inertia continua retornando: shell + **opções de select** (`accounts`, `categories`, `tags`) — agora usadas como `options` dos filtros FK — mas **sem** os dados paginados.

### 1.5 Config por entidade

#### Transactions (Despesas)

| Coluna | Filter | Sortable |
|--------|--------|----------|
| description | `Filter::text('description')` | ✓ |
| value | `Filter::numberRange('value')` | ✓ |
| date | `Filter::dateRange('date')` | ✓ |
| account | `Filter::relation('account','uuid')` | — |
| category | `Filter::relation('category','uuid')` | — |
| status | `Filter::select(paid/unpaid → paid_at null/notnull)` | — |

`searchable: ['description']`, `defaultSort: date desc`, `perPage: 25`.

#### Incomes (Receitas)

Igual a Transactions + filtro `origin` (`Filter::select` → `whereNotNull('recurrence_id')` / `whereNull`) e mantém o filtro oculto `recurrence` (deep-link "Ver instâncias") — exposto como filtro `recurrence` do tipo `select` FK sobre a relação `recurrence`. Query base: `where('type', Income)`.

#### Recurrences

| Coluna | Filter | Sortable |
|--------|--------|----------|
| description | text | ✓ |
| value | numberRange | ✓ |
| next_date | dateRange | ✓ |
| status | select (active/paused/exhausted) | — |
| account | relation('account','uuid') | — |
| category | relation('category','uuid') | — |

`defaultSort: next_date asc` (NULLS last via `orderByRaw`), `perPage: 25`. Hoje não pagina — passa a paginar.

#### Members

| Coluna | Filter | Sortable |
|--------|--------|----------|
| name | text (sobre `users.name`) | ✓ |
| email | text | — |
| role | select (admin/editor/viewer) | — |

Usa `MemberResource` (hoje **não usado** — controller mapeia array inline; viola D-01). Migração conserta isso.

### 1.6 `RecurrenceResource` — chave `id` → `uuid`

`RecurrenceResource` hoje retorna `'id'` (uuid) em vez de `'uuid'` — inconsistente com todo o resto (D-04). DataTable unifica para `uuid`. Frontend de Recurrences passa a ler `uuid`.

---

## 2. Frontend

### 2.1 Novas primitivas / deps

- `npx shadcn@latest add table` → `resources/js/components/ui/table.tsx` (lowercase, shadcn-managed).
- `npm i axios` → lib de requests (decisão D-55). Alternativa considerada: `ky`/`ofetch` (mais leves), mas axios já é dep transitiva e é o mais difundido.

> Convenção de casing real: primitivas shadcn em `resources/js/components/ui/` (lowercase, alias `@/components/ui`); componentes nossos em `resources/js/Components/` (capital, alias `@/Components`).

### 2.2 Tipos compartilhados — `resources/js/types/datatable.ts`

```ts
export interface PaginatedMeta {
  current_page: number; per_page: number; total: number;
  last_page: number; from: number | null; to: number | null;
}
export interface Paginated<T> { data: T[]; meta: PaginatedMeta; }

export type DataTableFilterType = 'text' | 'number' | 'date' | 'select';

export interface SelectOption { label: string; value: string; }

export interface DataTableFilter {
  type: DataTableFilterType;
  options?: SelectOption[];       // obrigatório para 'select'
  placeholder?: string;
}

export interface DataTableColumn<T> {
  key: string;                    // campo + chave do query param
  header: string;                 // título pt-BR
  sortable?: boolean;
  filter?: DataTableFilter;
  align?: 'left' | 'right';
  cell?: (row: T) => ReactNode;   // render custom (badge, moeda, ações)
}

export type SortDirection = 'asc' | 'desc';

export interface DataTableParams {
  page: number;
  per_page: number;
  sort: string | null;
  direction: SortDirection;
  filters: Record<string, string>;  // inclui sufixos _min/_max/_from/_to
}
```

### 2.3 `lib/api.ts` — wrapper axios

```ts
const api = axios.create({ headers: { Accept: 'application/json' } });

export async function getJson<T>(url: string, params?: object, signal?: AbortSignal): Promise<T>
```

- Extrai `data`, propaga erro padronizado, suporta `AbortController` (aborta request anterior na troca rápida de filtro).
- XSRF: requisições GET não precisam de token; o middleware de sessão cuida da auth.

### 2.4 `hooks/use-data-table.ts`

```ts
function useDataTable<T>(endpoint: string, initial: Partial<DataTableParams>) {
  return {
    rows: T[]; meta: PaginatedMeta; loading: boolean; error: string | null;
    params: DataTableParams;
    setPage(n): void; setSort(key): void; setFilter(key, value): void; clearFilters(): void;
    reload(): void;
  };
}
```

Comportamento:

- **Debounce 300ms** para filtro `text`; demais filtros disparam imediato (padrão atual mantido).
- Mudança de filtro reseta `page` para 1.
- `useEffect` dispara `getJson<Paginated<T>>` a cada mudança de `params` (com `AbortController`).
- State local no hook (não na URL). Navegação de volta = filtros resetados — aceito como trade-off v1 (ver Risks).

### 2.5 `Components/DataTable/`

```
Components/DataTable/
├── DataTable.tsx              # genérico <DataTable<T>/>
├── DataTableColumnHeader.tsx  # título + toggle de sort (ArrowUpDown/ChevronsUpDown)
├── DataTableFilterRow.tsx     # 2ª linha do thead com os inputs de filtro
├── DataTableFilterInput.tsx   # renderiza o input conforme DataTableFilter.type
└── DataTablePagination.tsx    # footer prev/next + "Página X de Y"
```

`DataTable.tsx` (assinatura):

```tsx
interface DataTableProps<T> {
  endpoint: string;              // route('transactions.datatable', { workspace })
  columns: DataTableColumn<T>[];
  initialFilters?: Record<string, string>;
  emptyState: ReactNode;
}

function DataTable<T extends { uuid: string }>(props: DataTableProps<T>): JSX.Element
```

Render:

```tsx
<Table>
  <TableHeader>
    <TableRow>                     {/* 1ª linha: títulos */}
      {columns.map(c => <TableHead key={c.key}><DataTableColumnHeader .../></TableHead>)}
    </TableRow>
    {hasAnyFilter && (
      <TableRow>                   {/* 2ª linha: filtros */}
        {columns.map(c => <TableHead key={c.key}><DataTableFilterInput .../></TableHead>)}
      </TableRow>
    )}
  </TableHeader>
  <TableBody>
    {loading ? <Skeleton rows/> : rows.map(r => <TableRow>{columns.map(c => <TableCell>{cell ?? r[c.key]}</TableCell>)}</TableRow>)}
    {empty && <TableRow><TableCell colSpan>{emptyState}</TableCell></TableRow>}
  </TableBody>
</Table>
<DataTablePagination meta={meta} .../>
```

**`DataTableFilterInput`** — switch por `filter.type`:

| type | Controle(s) | Param(s) |
|------|-------------|----------|
| `text` | `<Input type="text">` | `{key}` |
| `number` | 2× `<Input type="number">` (Mín/Máx) | `{key}_min`, `{key}_max` |
| `date` | 2× `<Input type="date">` (De/Até) | `{key}_from`, `{key}_to` |
| `select` | `<Select>` com `options` (label visível, value=uuid) | `{key}` |

**FK sempre `select`**: o value enviado é o `uuid` (invisível ao usuário), o label é o nome. Opções vêm das props Inertia do `index` (`accounts`, `categories`, `tags`) mapeadas para `SelectOption[]` (`{ label: name, value: uuid }`).

**`DataTableColumnHeader`** — se `sortable`, botão ghost com ícone indicando `asc`/`desc`/neutro; dispara `setSort(key)`.

**`DataTablePagination`** — `prev`/`next` (desabilitados nos extremos), texto "Página {current_page} de {last_page}", total de registros.

### 2.6 Colunas por entidade (frontend)

#### Transactions
| key | header | align | cell | filter |
|-----|--------|-------|------|--------|
| description | Descrição | left | texto | text |
| value | Valor | right | `formatCurrency` (emerald/amber por paid) | number |
| date | Data | left | `formatDate` | date |
| account | Conta | left | `row.account?.name` | select (options=accounts) |
| category | Categoria | left | dot + name | select (options=categories) |
| status | Status | left | glyph ✓/○ | select (paid/unpaid) |
| actions | — | right | Pagar/Desmarcar, Editar, Excluir | — |

#### Incomes — idem + coluna `origin` (badge "Recorrente") e filtro `origin` select (Recorrentes/Avulsas). Mantém deep-link `recurrence` como filtro oculto (sem input, injetado via `initialFilters`).

#### Recurrences
| key | header | cell | filter |
|-----|--------|------|--------|
| description | Descrição | texto | text |
| value | Valor | `formatCurrency` | number |
| next_date | Próxima | `formatDate` | date |
| status | Status | badge colorido | select (active/paused/exhausted) |
| account | Conta | name | select |
| category | Categoria | dot+name | select |
| actions | — | Editar/Pausar/Gerar agora/Ver instâncias | — |

#### Members
| key | header | cell | filter |
|-----|--------|------|--------|
| name | Nome | avatar + name | text |
| email | E-mail | email | text |
| role | Papel | RoleBadge | select (admin/editor/viewer) |
| actions | — | DropdownMenu | — |

---

## 3. Data Flows

### 3.1 Carga lazy + filtro

```
GET /w/{ws}/transactions  (Inertia) → shell + accounts/categories/tags + colunas
  └─ on-mount: DataTable → GET /w/{ws}/transactions/datatable?page=1&per_page=25&sort=date&direction=desc
       → DatatableService → JSON {data, meta}
  └─ usuário digita em "Descrição" (text, debounce 300ms) → params.filters.description=X, page=1
       → GET .../datatable?search? não: ?description=X&page=1 → JSON
  └─ usuário escolhe categoria (select FK) → ?category=<uuid> → whereHas category.uuid
```

### 3.2 Sort

```
clique header "Data" → setSort('date') → ?sort=date&direction=asc (alterna) → DatatableService orderBy whitelist
```

### 3.3 Paginação

```
clique "Próxima" → setPage(2) → ?page=2 → paginate(25) → meta atualiza → footer "Página 2 de N"
```

---

## 4. Test Strategy (TDD-first)

### 4.1 PHPUnit — `tests/Feature/Datatable/`

`DatatableServiceTest.php` (usa uma entidade real — Transaction — para exercitar o serviço):

- search case-insensitive em `description`.
- filtro `text` ignora quando vazio.
- filtro `numberRange` aplica só `_min`, só `_max`, e ambos.
- filtro `dateRange` aplica `whereDate` `_from`/`_to`.
- filtro `relation` (category/account) **resolve por uuid** (envia uuid, filtra corretamente) — regressão do bug D-12.
- filtro `select` (status paid/unpaid).
- combinação AND entre filtros.
- sort válido aplica; coluna fora da whitelist **ignorada** (não injeta orderBy arbitrário).
- paginação 25; `meta` com `current_page/last_page/total/from/to` corretos.
- resposta usa o Resource (shape dos itens).

`TransactionDatatableEndpointTest.php` — rota `transactions.datatable`: autenticada, autorizada (viewer pode ver, não-workspace 404), retorna JSON `{data,meta}`.

`IncomeDatatableEndpointTest.php` — idem + filtro `origin` + filtro oculto `recurrence`.

`RecurrenceDatatableEndpointTest.php` — paginação nova + sort `next_date` + filtro status.

`MemberDatatableEndpointTest.php` — usa `MemberResource` (shape `user`/`role`), autorização `viewMembers`.

### 4.2 Atualizar testes existentes

- `TransactionFilteringTest` / `IncomeFilteringTest`: passam a bater no endpoint JSON (assert `json`) e usar **uuid** nos filtros FK (hoje usam int `->id` — inconsistente com a UI). Contagem de paginação vira `meta.total`/`data` do JSON.

### 4.3 Cypress E2E

- Despesas: listar → filtrar por texto → filtrar por categoria (select mostra **nomes**, não uuid) → ordenar por valor → paginar (seed >25).
- Receitas: filtro origem + status + confirmação de recebimento ainda funciona após migração.
- Recorrências: lista paginada + filtro status.
- Membros: lista renderiza, role select presente.

---

## 5. Files to Create / Modify

### 5.1 Create

| File | Purpose |
|------|---------|
| `app/Services/Datatable/DatatableService.php` | centraliza query + resposta JSON |
| `app/Services/Datatable/DatatableConfig.php` | config fluente por entidade |
| `app/Services/Datatable/Filter.php` | tipos de filtro (text/number/date/relation/select) |
| `resources/js/components/ui/table.tsx` | primitiva shadcn (via CLI) |
| `resources/js/lib/api.ts` | wrapper axios |
| `resources/js/types/datatable.ts` | tipos compartilhados |
| `resources/js/hooks/use-data-table.ts` | hook de fetching/estado |
| `resources/js/Components/DataTable/DataTable.tsx` | componente genérico |
| `resources/js/Components/DataTable/DataTableColumnHeader.tsx` | header + sort toggle |
| `resources/js/Components/DataTable/DataTableFilterRow.tsx` | 2ª linha do thead |
| `resources/js/Components/DataTable/DataTableFilterInput.tsx` | input por tipo de filtro |
| `resources/js/Components/DataTable/DataTablePagination.tsx` | footer |
| `tests/Feature/Datatable/*` | testes do serviço + endpoints |

### 5.2 Modify

| File | Purpose |
|------|---------|
| `app/Http/Controllers/TransactionController.php` | + `datatable()`, `index` sem dados paginados |
| `app/Http/Controllers/IncomeController.php` | idem |
| `app/Http/Controllers/RecurrenceController.php` | idem + paginar |
| `app/Http/Controllers/WorkspaceMemberController.php` | idem + usar `MemberResource` |
| `app/Http/Resources/RecurrenceResource.php` | `id` → `uuid` |
| `routes/web.php` | + rotas datatable |
| `resources/js/Pages/Transactions/Index.tsx` | usa DataTable |
| `resources/js/Pages/Incomes/Index.tsx` | usa DataTable |
| `resources/js/Pages/Recurrences/Index.tsx` | usa DataTable (lê `uuid`) |
| `resources/js/Pages/Workspace/Members.tsx` | usa DataTable |
| `package.json` | + axios |
| `tests/Feature/Transactions/TransactionFilteringTest.php` | JSON + uuid |
| `tests/Feature/Incomes/IncomeFilteringTest.php` | JSON + uuid |

---

## 6. Risks & Decisions

| Risk | Mitigação |
|------|-----------|
| Abandona parcialmente D-08 (Inertia puro) para listagens | Decisão D-52/D-54 explícita; shell continua Inertia, só dados viram JSON |
| Filtro FK uuid vs int (bug atual) | `Filter::relation` resolve via `whereHas`; testes cobrem uuid |
| `RecurrenceResource` usa `id` em vez de `uuid` | unifica para `uuid` (D-04); atualiza frontend + testes |
| Members controller ignora `MemberResource` | migração adota o Resource |
| Filtros em state local (não na URL) | perde deep-link de filtro ao voltar; aceito v1; deep-link `recurrence` mantido via `initialFilters` |
| Requisições em rajada ao digitar | debounce 300ms + `AbortController` no axios |
| `numberRange` sobre `value` decimal | cast `(float)` no backend |
| Primitiva `table` shadcn | instalar via CLI (`npx shadcn add table`), nunca editar manual |

## 7. Verification Gates

- `php artisan test --filter=Datatable` verde
- `php artisan test` (suíte inteira) verde
- `composer quality` verde
- `npm run build` verde
- `npm run quality` verde
- Cypress E2E de listagens verde
- Smoke manual: filtrar/ordenar/paginar Despesas, Receitas, Recorrências, Membros; filtro FK mostra nomes (não uuid)
