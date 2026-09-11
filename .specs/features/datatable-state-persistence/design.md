# DataTable State Persistence — Design

**Story ID:** DTBL-02
**Spec:** `.specs/features/datatable-state-persistence/spec.md`
**Parent:** `.specs/project/PROJECT.md`
**Depends:** DTBL-01 (`.specs/features/datatable/design.md`)

## Resumo

Adicionar persistência de estado (filtros + ordenação) na sessão do Laravel, com hidratação automática, prioridade de request sobre sessão, e controle programático via URL. O frontend exibe badges de filtros ativos e botão de limpar.

---

## 1. Backend

### 1.1 `TableStateService` — Serviço de Estado de Tabela

Localização: `app/Services/Datatable/TableStateService.php`

```php
final class TableStateService
{
    private const SESSION_PREFIX = 'datatable.';

    /**
     * Salva o estado (filtros + sort) na sessão para uma tabela.
     */
    public function save(
        Request $request,
        string $tableKey,
        array $filters,
        ?string $sort,
        string $direction,
    ): void;

    /**
     * Recupera o estado persistido. Retorna array com 'filters', 'sort', 'direction'.
     */
    public function restore(Request $request, string $tableKey): array;

    /**
     * Limpa o estado persistido de uma tabela.
     */
    public function clear(Request $request, string $tableKey): void;

    /**
     * Gera a chave de sessão para uma tabela.
     */
    public static function sessionKey(string $tableKey): string;
}
```

**Estrutura na sessão:**

```
datatable.transactions => [
    'filters' => ['category' => 'uuid-here', 'status' => 'paid'],
    'sort' => 'date',
    'direction' => 'desc',
]
datatable.incomes => [
    'filters' => [...],
    'sort' => 'value',
    'direction' => 'asc',
]
```

**Regras:**
- Paginação (page/per_page) **nunca** é persistida — apenas filtros + sort.
- Filtros vazios (`''`) não são salvos (limpos do array antes de persistir).
- Sort `null` ou direção `''` não são persistidos (usa default da config).

### 1.2 Trait `PersistsTableState` (opcional, para reuso)

Localização: `app/Http/Controllers/Concerns/PersistsTableState.php`

```php
trait PersistsTableState
{
    /**
     * Retorna o estado consolidado: request explícito > sessão.
     * Salva o resultado na sessão.
     */
    protected function resolveTableState(
        Request $request,
        string $tableKey,
        DatatableConfig $config,
    ): array;

    /**
     * Extrai parâmetros de tabela da request.
     */
    protected function extractTableParamsFromRequest(Request $request): array;
}
```

**Lógica de prioridade (Request > Sessão):**

```php
// Pseudocódigo da resolução
$requestFilters = $this->extractFiltersFromRequest($request); // só não-vazios
$requestSort = $request->query('sort');
$requestDirection = $request->query('direction');

$sessionState = $this->tableStateService->restore($request, $tableKey);

// Request explícito tem prioridade
$filters = !empty($requestFilters) ? $requestFilters : $sessionState['filters'];
$sort = $requestSort ?? $sessionState['sort'] ?? null;
$direction = $requestDirection ?? $sessionState['direction'] ?? 'asc';

// Salva o consolidado na sessão
$this->tableStateService->save($request, $tableKey, $filters, $sort, $direction);

return compact('filters', 'sort', 'direction');
```

### 1.3 Integração no Controller

Cada controller de DataTable (`TransactionController`, `IncomeController`, `RecurrenceController`) é modificado:

**No `index()`:** passa o estado atual da sessão como prop `initialState`:

```php
public function index(Workspace $workspace): Response
{
    $this->authorize('viewAny', [Transaction::class, $workspace]);

    $state = app(TableStateService::class)->restore(
        request(), 'transactions'
    );

    return inertia('Transactions/Index', [
        'accounts' => AccountResource::collection(...),
        'categories' => CategoryResource::collection(...),
        'tags' => TagResource::collection(...),
        'initialState' => $state, // ['filters' => [], 'sort' => null, 'direction' => 'asc']
    ]);
}
```

**No `datatable()`:** recebe e aplica o estado, salva na sessão:

```php
public function datatable(Workspace $workspace, Request $request): JsonResponse
{
    $this->authorize('viewAny', [Transaction::class, $workspace]);

    // Resolve estado: request > sessão, salva resultado
    $state = $this->resolveTableState($request, 'transactions');

    // Aplica ao request (merge de query params) para o DatatableService ler
    $request->merge([
        'sort' => $state['sort'],
        'direction' => $state['direction'],
        ...$state['filters'],
    ]);

    $query = $workspace->transactions()
        ->where('type', TransactionType::Expense)
        ->with(['account', 'category', 'tags']);

    return app(DatatableService::class)->paginate($query, $request, $this->datatableConfig());
}
```

### 1.4 Endpoint de Limpeza

Nova rota:

```php
Route::delete('datatable/{entity}/state', [DatatableStateController::class, 'destroy'])
    ->name('datatable.state.destroy');
```

**Mapeamento de entity → tableKey:**

```php
// No controller
private const ENTITY_MAP = [
    'transactions' => 'transactions',
    'incomes' => 'incomes',
    'recurrences' => 'recurrences',
];

public function destroy(Request $request, Workspace $workspace, string $entity): JsonResponse
{
    $tableKey = self::ENTITY_MAP[$entity] ?? abort(404);

    app(TableStateService::class)->clear($request, $tableKey);

    return response()->json(['message' => 'Estado limpo.']);
}
```

### 1.5 Helper de Extração de Filtros da Request

Para saber quais filtros vieram explícitos na request (para prioridade), o controller precisa saber quais keys de filtro são válidos. O `DatatableConfig` já tem essa info:

```php
// DatatableConfig — adicionar método
public function filterKeys(): array
{
    return array_keys($this->filters);
}
```

O controller extrai apenas os filtros que existem na config:

```php
private function extractFiltersFromRequest(Request $request, DatatableConfig $config): array
{
    $filters = [];
    foreach ($config->filterKeys() as $key) {
        $value = $request->query($key);
        if ($value !== null && $value !== '') {
            $filters[$key] = $value;
        }
    }
    return $filters;
}
```

---

## 2. Frontend

### 2.1 Tipo `TableState`

```typescript
// resources/js/types/datatable.ts — adicionar
export interface TableState {
    filters: Record<string, string>;
    sort: string | null;
    direction: SortDirection;
}
```

### 2.2 Hook `useDataTable` — Modificação

Adicionar suporte a `initialState` e `syncState`:

```typescript
export function useDataTable<T>(
    endpoint: string,
    initialState?: TableState,      // novo: hidratação da sessão
    onStateChange?: (state: DataTableParams) => void, // novo: callback para persistir
): UseDataTableResult<T> {
    // ... existente, mas inicializa com initialState se fornecido
    const [params, setParams] = useState<DataTableParams>(() => ({
        page: 1,
        per_page: initialState?.per_page ?? DEFAULT_PER_PAGE,
        sort: initialState?.sort ?? null,
        direction: initialState?.direction ?? 'asc',
        filters: initialState?.filters ?? {},
    }));

    // ... restante existente
}
```

**Debounce no callback de mudança:** o `onStateChange` é debounced para não spamar a sessão.

### 2.3 Componente `DataTable` — Modificação

Adicionar props:

```typescript
interface DataTableProps<T> {
    endpoint: string;
    columns: DataTableColumn<T>[];
    initialState?: TableState;       // novo: estado inicial da sessão
    emptyState: ReactNode;
    reloadTrigger?: number;
    onStateChange?: (state: DataTableParams) => void; // novo
}
```

### 2.4 Componente `ActiveFilters` (novo)

Localização: `resources/js/Components/DataTable/ActiveFilters.tsx`

```typescript
interface ActiveFilter {
    key: string;
    label: string;     // label visível (ex.: "Categoria: Alimentação")
    value: string;     // valor interno (ex.: uuid)
}

interface ActiveFiltersProps {
    filters: ActiveFilter[];
    onRemoveFilter(key: string): void;
    onClearAll(): void;
}
```

Renderiza:
- Container flex-wrap com badges (shadcn `Badge` variant="secondary")
- Cada badge: label + botão X para remover
- Botão "Limpar Filtros" (outline, size="sm") visível quando há filtros

### 2.5 Modificação nas Pages (Transactions/Incomes/Recurrences Index)

Cada page passa `initialState` para o DataTable e renderiza `ActiveFilters`:

```typescript
// Exemplo: Transactions/Index.tsx
export default function TransactionsIndex({
    accounts, categories, tags, initialState,
}: Props) {
    const clearState = useForm();

    function handleClearFilters() {
        clearState.delete(route('datatable.state.destroy', {
            workspace: workspace.id,
            entity: 'transactions',
        }), {
            onSuccess: () => {
                // recarrega a página para hidratar estado limpo
                router.reload();
            },
        });
    }

    return (
        <DataTable
            endpoint={route('transactions.datatable', { workspace: workspace.id })}
            columns={columns}
            initialState={initialState}
            emptyState={...}
        />
        <ActiveFilters
            filters={activeFilters}
            onRemoveFilter={(key) => {
                // setFilter(key, '') no DataTable + remove badge
            }}
            onClearAll={handleClearFilters}
        />
    );
}
```

### 2.6 Fluxo de Remoção Individual de Filtro

Quando o usuário clica no X de um badge:
1. Chama `setFilter(key, '')` no hook (limpa o filtro local)
2. Dispara nova requisição ao endpoint JSON
3. Backend salva estado atualizado na sessão (sem o filtro removido)

---

## 3. Data Flows

### 3.1 Carga com hidratação (sem query params)

```
GET /w/{ws}/transactions (Inertia)
  → Controller index() lê session['datatable.transactions']
  → Retorna initialState = { filters: {...}, sort: 'date', direction: 'desc' }
  → Frontend hidratura DataTable com initialState
  → DataTable dispara JSON com filtros/sort hidratados
  → Backend salva mesmo estado na sessão (idempotente)
```

### 3.2 Sobrescrita programática (com query params)

```
GET /w/{ws}/transactions?status=paid&category=uuid-x (Inertia)
  → Controller index() lê session, mas query params têm prioridade
  → Retorna initialState = { filters: { status: 'paid', category: 'uuid-x' }, sort: 'date', direction: 'desc' }
  → Frontend hidratura com novos valores
  → DataTable dispara JSON
  → Backend salva NOVO estado na sessão (sobrescreve anterior)
```

### 3.3 Limpar filtros

```
Usuário clica "Limpar Filtros"
  → Frontend: DELETE /w/{ws}/datatable/transactions/state
  → Backend: session()->forget('datatable.transactions')
  → Frontend: router.reload()
  → index() retorna initialState vazio
  → DataTable carrega padrão
```

### 3.4 Remoção individual de filtro

```
Usuário clica X no badge "Categoria: Alimentação"
  → Frontend: setFilter('category', '')
  → Hook dispara JSON sem category
  → Backend salva estado sem category na sessão
  → Badge desaparece
```

---

## 4. Test Strategy (TDD-first)

### 4.1 PHPUnit — `tests/Feature/Datatable/TableStateServiceTest.php`

- `save()` persiste filtros + sort na sessão.
- `restore()` retorna estado persistido.
- `restore()` retorna padrão vazio quando não há sessão.
- `clear()` remove estado da sessão.
- `sessionKey()` gera chave correta.

### 4.2 PHPUnit — `tests/Feature/Datatable/TransactionStatePersistenceTest.php`

- Estado é salvo na sessão após requisição ao endpoint.
- Estado é restaurado quando request não tem query params.
- Query params explícitos sobrescrevem estado da sessão.
- Parciais: só os enviados sobrescrevem; resto vem da sessão.
- Endpoint `DELETE datatable/{entity}/state` limpa a sessão.
- Endpoint de limpeza é autorizado (403 para não-membros, 404 cross-workspace).
- Isolamento: transactions ≠ incomes (salvar num não afeta o outro).

### 4.3 PHPUnit — `tests/Feature/Datatable/IncomeStatePersistenceTest.php`

- Mesmo conjunto para incomes.
- Filtro `origin` persiste corretamente.

### 4.4 PHPUnit — `tests/Feature/Datatable/RecurrenceStatePersistenceTest.php`

- Mesmo conjunto para recurrences.

### 4.5 Cypress E2E — `e2e/datatable/state-persistence.cy.js`

**Cenário 1 — Persistência:**
1. Acessar despesas
2. Aplicar filtro de categoria
3. Ordenar por valor
4. Navegar para outra rota (ex.: planejamento)
5. Voltar para despesas
6. Assert: filtro de categoria ainda ativo (badge visível), ordenação por valor (seta na coluna)

**Cenário 2 — Sobrescrição programática:**
1. Aplicar filtro X manualmente
2. Navegar para outra rota
3. Acessar despesa via URL com `?status=paid`
4. Assert: filtro de status ativo, categoria anterior foi substituída

**Cenário 3 — Limpar filtros:**
1. Aplicar filtros
2. Clicar "Limpar Filtros"
3. Assert: badges desaparecem, inputs vazios, ordenação padrão

**Cenário 4 — Remoção individual:**
1. Aplicar 2 filtros
2. Clicar X de um badge
3. Assert: badge desaparece, filtro removido, tabela atualiza

**Cenário 5 — Isolamento:**
1. Aplicar filtro em despesas
2. Acessar receitas
3. Assert: receitas sem filtro (estado independente)

---

## 5. Files to Create / Modify

### 5.1 Create

| File | Purpose |
|------|---------|
| `app/Services/Datatable/TableStateService.php` | Serviço de persistência/limpeza de estado na sessão |
| `app/Http/Controllers/Concerns/PersistsTableState.php` | Trait com lógica de prioridade request > sessão |
| `app/Http/Controllers/DatatableStateController.php` | Controller para limpeza de estado (DELETE endpoint) |
| `resources/js/Components/DataTable/ActiveFilters.tsx` | Componente de badges + botão limpar |
| `tests/Feature/Datatable/TableStateServiceTest.php` | Testes unitários do serviço de estado |
| `tests/Feature/Datatable/TransactionStatePersistenceTest.php` | Testes de integração para transactions |
| `tests/Feature/Datatable/IncomeStatePersistenceTest.php` | Testes de integração para incomes |
| `tests/Feature/Datatable/RecurrenceStatePersistenceTest.php` | Testes de integração para recurrences |
| `e2e/datatable/state-persistence.cy.js` | E2E de persistência e limpeza |

### 5.2 Modify

| File | Purpose |
|------|---------|
| `app/Http/Controllers/TransactionController.php` | `index()` passa `initialState`; `datatable()` resolve estado |
| `app/Http/Controllers/IncomeController.php` | Idem |
| `app/Http/Controllers/RecurrenceController.php` | Idem |
| `app/Services/Datatable/DatatableConfig.php` | + método `filterKeys()` |
| `app/Http/Kernel.php` ou `routes/web.php` | + rota `datatable.state.destroy` |
| `resources/js/types/datatable.ts` | + interface `TableState` |
| `resources/js/hooks/use-data-table.ts` | + suporte a `initialState` e `onStateChange` |
| `resources/js/Components/DataTable/DataTable.tsx` | + props `initialState`, `onStateChange` |
| `resources/js/Pages/Transactions/Index.tsx` | Usa `initialState` + renderiza `ActiveFilters` |
| `resources/js/Pages/Incomes/Index.tsx` | Idem |
| `resources/js/Pages/Recurrences/Index.tsx` | Idem |

---

## 6. Risks & Decisions

| Risk | Mitigação |
|------|-----------|
| Estado stale na sessão (ex.: categoria deletada) | Frontend resolve labels via props do `index`; filtro inválido é ignorado pelo backend (whereHas não encontra) |
| Conflito de session driver (file vs redis) | Usa session helper padrão do Laravel; driver-agnóstico |
| Performance: escrita na sessão a cada request | Session I/O é negligible (single key por tabela); sem IO extra no banco |
| Query params na URL do `index` (não do JSON) | `index` lê query params e passa como `initialState`; JSON endpoint também resolve |
| Remoção individual precisa de label resoludo | Frontend manteve mapa key→label via props de options; badge usa label |

---

## 7. Verification Gates

- `php artisan test --filter=TableStateService` verde
- `php artisan test --filter=StatePersistence` verde
- `php artisan test` (suíte inteira) verde
- `composer quality` verde
- `npm run quality` verde
- `npm run build` verde
- Cypress E2E de persistência verde
