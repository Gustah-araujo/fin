# Plano: Unificação da Rotina de Importação

**Issue**: [Feature] Unificar rotina de importação em um sidemenu próprio
**Status**: Planejado — aguardando implementação
**Criado**: 2026-10-18

---

## 📋 Resumo da Mudança

Atualmente existem dois fluxos de importação separados — "Importar Despesas" na listagem de transactions e "Importar Receitas" na listagem de incomes. O usuário pode receber um extrato com ambos os tipos e precisa fazer duas importações.

**Objetivo**: Criar um fluxo unificado de importação acessível pelo sidebar, onde:
1. A IA classifica cada transação como débito (despesa) OU crédito (receita) automaticamente
2. A tela de confirmação suporta ambos os tipos simultaneamente
3. Cada linha da pré-visualização inclui Select de Categoria e MultiSelect de Tags
4. Os botões de importação são removidos das listagens individuais

---

## 🏗️ Arquitetura Alvo

```
[Sidebar: Importações] → [GET /w/{workspace}/import]
                                ↓
                    [Sem selector de tipo — IA decide]
                                ↓
                    [Upload Form → POST /w/{workspace}/import]
                                ↓
                    [IA classifica debit/credit por item]
                                ↓
              [Tela de Pré-visualização: despesas + receitas]
              [Category Select + Tags MultiSelect por linha]
                                ↓
                    [POST /w/{workspace}/import/confirm]
                                ↓
              [ImportService cria transações com tipo por item]
                                ↓
              [Redirect para Dashboard com toast de sucesso]
```

---

## 📝 Mudanças por Camada

### 1. Backend — Laravel

#### 1.1 Rotas (`routes/web.php`)

**Antes (6 rotas)**:
```
transactions/import/{create,store,confirm} — type=expense forçado
incomes/import/{create,store,confirm} — type=income forçado
```

**Depois (3 rotas unificadas)**:
```php
Route::get('import', [ImportController::class, 'create'])
    ->name('import.create');
Route::post('import', [ImportController::class, 'store'])
    ->name('import.store');
Route::post('import/confirm', [ImportController::class, 'confirm'])
    ->name('import.confirm');
```

- Remover as 6 rotas antigas (`transactions.import.*` e `incomes.import.*`)
- Adicionar 3 rotas novas sem prefixo de tipo

#### 1.2 ImportController

- `create()`: Remover lógica de `$type = routeIs(...)`. Agora retorna `categories` e `tags` como props.
- `store()`: Remover validação de `type` do request. Passar `type = null` para `parseFile()` (IA decide).
- `confirm()`: Remover `$type` do validated data. Usar o tipo individual de cada item (`expense` ou `income`). Redirect para dashboard.

```php
// create() — nova assinatura
public function create(Workspace $workspace): Response
{
    $this->authorize('create', [Transaction::class, $workspace]);

    return inertia('Imports/Index', [
        'categories' => CategoryResource::collection(
            $workspace->categories()->orderBy('name')->get()
        ),
        'tags' => TagResource::collection(
            $workspace->tags()->orderBy('name')->get()
        ),
    ]);
}
```

#### 1.3 ImportService

- `parseFile()`: Remover parâmetro `$type`. Delegar ao `Ai::parseFile()` sem tipo forçado.
- `createTransactions()`: Remover parâmetro `$type`. Usar `type` de cada item individualmente.

```php
public function parseFile(Workspace $workspace, UploadedFile $file): array
{
    return Ai::parseFile($workspace, $file);
}

public function createTransactions(Workspace $workspace, User $user, array $items): int
{
    $account = $this->getOrCreateImportAccount($workspace);
    $count = 0;

    foreach ($items as $item) {
        if (($item['is_duplicate'] ?? false) && ! ($item['confirm_duplicate'] ?? false)) {
            continue;
        }

        $itemType = TransactionType::tryFrom($item['type']) ?? TransactionType::Expense;
        $category = $this->resolveOrCreateCategory($workspace, $item, $itemType);

        $data = [
            'description' => $item['description'],
            'value' => $item['value'],
            'date' => $item['date'],
            'type' => $itemType->value,
            'account_id' => $account->uuid,
            'category_id' => $category->uuid,
        ];

        $transaction = app(TransactionService::class)->create($workspace, $user, $data);

        if (! empty($item['tags'])) {
            app(TransactionService::class)->syncTags($transaction, $item['tags']);
        }

        $count++;
    }

    return $count;
}
```

#### 1.4 AiService

- Remover parâmetro `$type` de `parseFile()`.
- Atualizar prompt do userMessage: remover "Tipo esperado: ..." — a IA deve classificar livremente.

```php
public function parseFile(Workspace $workspace, UploadedFile $file): array
{
    $agent = new TransactionParser($workspace);
    // ...
    $userMessage = "Extraia as transações financeiras.";
    // ...
}
```

#### 1.5 FakeAiService

- Remover parâmetro `$type` de `parseFile()`.
- Usar coluna opcional `type` no CSV (se presente: `debit`/`credit`; se ausente: `debit` como default para backward compatibility).

```php
'type' => ($columns[4] ?? 'debit') === 'credit' ? 'credit' : 'debit',
```

#### 1.6 TransactionParser (AI Agent)

- Atualizar `instructions()`: remover referência a tipo forçado. Instruir a IA a classificar cada transação como debit (saída) ou crédito (entrada) baseado no contexto do documento.

#### 1.7 FormRequests

- **UploadFileRequest**: Remover regra `type` (não é mais necessário).
- **ConfirmImportRequest**: Remover regra `type` do root. Adicionar `items.*.type` como required com `Rule::in(['expense', 'income'])`.

#### 1.8 ImportPreviewResource (novo, opcional)

Um resource leve para o preview pode ser útil, mas como o controller já monta o array manualmente, podemos manter o array_map inline por enquanto.

---

### 2. Frontend — React + TypeScript

#### 2.1 Sidebar (`AppSidebar.tsx`)

- Adicionar item "Importações" na seção "Movimentações" com ícone `FileUp` ou `Import` do lucide-react.

```tsx
{
    label: 'Importações',
    href: route('import.create', { workspace: workspaceUuid }),
    icon: FileUp, // ou Import
    testId: 'sidebar-imports',
},
```

#### 2.2 Types (`types/import.ts`)

```typescript
export interface ImportPreviewItem {
    id: string;
    description: string;
    value: number;
    date: string;
    type: string;              // "expense" | "income"
    category_name: string;
    category_uuid: string | null;
    tags: string[];            // array de UUIDs
    tag_names: string[];       // array de nomes para exibição
    is_duplicate: boolean;
    confirm_duplicate: boolean;
}

export interface CategoryOption {
    uuid: string;
    name: string;
    type: string;              // "expense" | "income" | "both"
    color: string;
    icon: string | null;
}

export interface TagOption {
    uuid: string;
    name: string;
    color: string;
}

export interface ImportPageProps {
    categories?: CategoryOption[];
    tags?: TagOption[];
    preview?: ImportPreviewItem[];
    flash?: {
        success?: string;
        error?: string;
    };
}
```

#### 2.3 ImportPreviewTable (`Components/Import/ImportPreviewTable.tsx`)

- Adicionar prop `categories` e `tags`
- Adicionar coluna "Tipo" (badge: Despesa/Receita com cores)
- Substituir Input de Categoria por **Select** usando o componente `Select` existente
  - Filtrar categorias por tipo do item (expense → Expense+Both, income → Income+Both)
  - AI-selected category como valor selecionado
- Adicionar coluna **Tags** com MultiSelect
  - Usar `Command` + `CommandItem` com checkboxes (cmdk multi-select pattern)
  - AI-selected tags como valores selecionados
- Valor: usar cor por item type (`type === 'income' ? emerald : destructive`)

#### 2.4 Imports/Index.tsx

- Remover lógica de `isIncome`, `storeRoute`, `confirmRoute` baseados em tipo
- Rotas fixas: `import.store`, `import.confirm`, `import.create`
- Redirect do botão Cancelar → dashboard
- Receber props `categories` e `tags` e repassar ao `ImportPreviewTable`
- Estado de "selecionadas" filtra por `confirm_duplicate` (mantém comportamento)
- Badge de contagem separa por tipo: "X despesas + Y receitas selecionadas"

#### 2.5 Transactions/Index.tsx

- Remover botão "Importar Despesas" (linhas 389-397)
- Manter apenas "Nova Despesa"

#### 2.6 Incomes/Index.tsx

- Remover botão "Importar Receitas" (linhas 397-405)
- Manter apenas "Nova Receita"

---

### 3. Testes

#### 3.1 PHPUnit — ImportControllerTest

- **Modificar**:
  - `test_import_create_page_renders_for_expenses` → `test_import_create_page_renders` (rota unificada)
  - `test_import_create_page_renders_incomes` → removido (não existe mais)
  - `test_guest_cannot_access_import_pages` → usar `import.create`
  - `test_non_member_cannot_access_import` → usar `import.create`
  - `test_store_*` → remover `'type' => 'expense'` do payload POST
  - `test_confirm_*` → remover `'type' => 'expense'/'income'` do payload; adicionar `'type'` per-item
  - `test_confirm_redirects_to_transactions_index` → redirect para dashboard
  - `test_confirm_redirects_to_incomes_index` → removido

- **Adicionar**:
  - `test_store_accepts_mixed_types_in_items` — CSV com debit e credit
  - `test_confirm_creates_mixed_expense_and_income_transactions` — um item expense + um item income
  - `test_confirm_assigns_category_from_uuid` — category_uuid existe, transação é criada com ela
  - `test_confirm_assigns_tags_to_transaction` — tags array com UUIDs válidos
  - `test_confirm_creates_category_when_only_name_provided` — category_uuid null, category_name fornecido

#### 3.2 PHPUnit — ImportSmokeTest

- Usar `import.create` em vez de `transactions.import.create` e `incomes.import.create`
- Um único test: `test_import_create_page_renders`

#### 3.3 PHPUnit — ImportServiceTest

- Atualizar chamadas `parseFile()` sem tipo
- Atualizar chamadas `createTransactions()` sem tipo
- Adicionar teste de items com tipos mistos

#### 3.4 PHPUnit — AiServiceTest

- Atualizar `parseFile()` sem parâmetro tipo
- FakeAiService: testar com CSV incluindo coluna `type`

#### 3.5 PHPUnit — TransactionParserAgentTest

- Verificar que prompt não contém "Tipo esperado"
- Verificar que schema ainda aceita debit/credit

#### 3.6 Cypress — import.cy.js

- Smoke test: sidebar → Importações → página renderiza
- Remover testes de rota separada (`transactions/import`, `incomes/import`)
- Atualizar seletor do sidebar: `[data-testid="sidebar-imports"]`
- Adicionar teste: upload de CSV com tipos mistos → preview mostra despesas e receitas
- Adicionar teste: categoria como Select — abre, seleciona opção, valor persiste
- Adicionar teste: tags como MultiSelect — seleciona múltiplas, badges aparecem
- Atualizar fixture `import.csv` para incluir coluna type (opcional)

#### 3.7 Fixture — cypress/fixtures/import.csv

- Adicionar linha com tipo credit para testar mixed:
```
description,value,date,category,type
Supermercado XYZ,150.00,2026-09-01,Alimentação,debit
Farmácia ABC,45.90,2026-09-02,Saúde,debit
Uber Viagem,23.50,2026-09-03,Transporte,debit
Salário Empresa,5000.00,2026-09-05,Trabalho,credit
```

---

## 📁 Lista de Arquivos

### Novos (0)
Nenhum arquivo novo necessário — a unificação reutiliza a estrutura existente.

### Modificados (15)

| Arquivo | Camada | Mudança |
|---------|--------|---------|
| `routes/web.php` | Backend | 6 rotas → 3 rotas unificadas |
| `app/Http/Controllers/ImportController.php` | Backend | Remover $type de create/store/confirm; adicionar categories+tags props |
| `app/Services/ImportService.php` | Backend | Remover $type de parseFile/createTransactions; tipo por item |
| `app/Services/AiService.php` | Backend | Remover $type de parseFile; prompt sem tipo forçado |
| `app/Services/FakeAiService.php` | Backend | Remover $type; ler do CSV se disponível |
| `app/Ai/Agents/TransactionParser.php` | Backend | Atualizar instructions — IA classifica livremente |
| `app/Http/Requests/UploadFileRequest.php` | Backend | Remover regra `type` |
| `app/Http/Requests/ConfirmImportRequest.php` | Backend | Remover `type` root; adicionar `items.*.type` |
| `resources/js/Components/AppSidebar.tsx` | Frontend | Adicionar nav item "Importações" |
| `resources/js/Pages/Imports/Index.tsx` | Frontend | Remover lógica de tipo; usar rotas unificadas; passar categories+tags |
| `resources/js/Pages/Transactions/Index.tsx` | Frontend | Remover botão "Importar Despesas" |
| `resources/js/Pages/Incomes/Index.tsx` | Frontend | Remover botão "Importar Receitas" |
| `resources/js/Components/Import/ImportPreviewTable.tsx` | Frontend | Category Select + Tags MultiSelect + Tipo badge |
| `resources/js/types/import.ts` | Frontend | Atualizar interfaces |
| `cypress/e2e/import.cy.js` | E2E | Atualizar para fluxo unificado + testes de category/tags |

### Modificados — Testes (5)

| Arquivo | Mudança |
|---------|---------|
| `tests/Feature/Import/ImportControllerTest.php` | Adaptar rotas, remover type, adicionar testes mixed |
| `tests/Feature/Import/ImportSmokeTest.php` | Rota unificada, 1 teste |
| `tests/Feature/Import/ImportServiceTest.php` | Sem tipo nos parâmetros |
| `tests/Feature/Import/AiServiceTest.php` | Sem tipo; testar FakeAiService com coluna type |
| `tests/Feature/Import/TransactionParserAgentTest.php` | Verificar prompt sem tipo forçado |

### Modificados — Fixture (1)

| Arquivo | Mudança |
|---------|---------|
| `cypress/fixtures/import.csv` | Adicionar coluna type + linha credit |

---

## 🔢 Ordem de Implementação (Steps Atômicos)

Cada step é um commit atômico, com seus próprios testes passing antes de prosseguir.

### Step 1: Backend — Atualizar AI Layer
- Atualizar `TransactionParser::instructions()` — remover tipo forçado
- Atualizar `AiService::parseFile()` — remover `$type`, atualizar prompt
- Atualizar `FakeAiService::parseFile()` — remover `$type`, ler type do CSV
- Atualizar `AiServiceTest` — testes sem parâmetro tipo
- **Gate**: `php artisan test --filter=Import` + `composer quality`

### Step 2: Backend — Atualizar ImportService
- `ImportService::parseFile()` — sem `$type`
- `ImportService::createTransactions()` — sem `$type`, tipo por item, tags sync
- Atualizar `ImportServiceTest`
- **Gate**: `php artisan test --filter=Import` + `composer quality`

### Step 3: Backend — Atualizar FormRequests + Controller
- `UploadFileRequest` — remover `type`
- `ConfirmImportRequest` — remover `type` root, adicionar `items.*.type`
- `ImportController` — create sem tipo, store sem tipo, confirm com redirect p/ dashboard
- **Gate**: `php artisan test --filter=Import` + `composer quality`

### Step 4: Backend — Atualizar Rotas
- Remover 6 rotas antigas (`transactions.import.*`, `incomes.import.*`)
- Adicionar 3 rotas unificadas (`import.create`, `import.store`, `import.confirm`)
- Atualizar `ImportControllerTest` — novas rotas, remover type, adicionar mixed tests
- Atualizar `ImportSmokeTest` — rota unificada
- **Gate**: `php artisan test --filter=Import` + `composer quality`

### Step 5: Frontend — Atualizar Types + ImportPreviewTable
- Atualizar `types/import.ts` — adicionar CategoryOption, TagOption, category_uuid, tag_names
- Atualizar `ImportPreviewTable.tsx` — Category Select, Tags MultiSelect, Tipo badge
- Instalar `npx shadcn@latest add` se faltar algum componente (tudo já existe)
- **Gate**: `npm run quality`

### Step 6: Frontend — Atualizar Pages
- `Imports/Index.tsx` — rotas unificadas, sem tipo, categories+tags props
- `Transactions/Index.tsx` — remover botão "Importar Despesas"
- `Incomes/Index.tsx` — remover botão "Importar Receitas"
- **Gate**: `npm run quality`

### Step 7: Frontend — Atualizar Sidebar
- `AppSidebar.tsx` — adicionar item "Importações" na seção Movimentações
- Importar ícone `FileUp` do lucide-react
- **Gate**: `npm run quality`

### Step 8: E2E — Atualizar Cypress Tests
- Atualizar `cypress/e2e/import.cy.js` — rota unificada, sidebar-imports
- Atualizar fixture `import.csv` — adicionar coluna type + linha credit
- Novos testes: mixed types, category select, tags multi-select
- **Gate**: `npx cypress run --spec cypress/e2e/import.cy.js`

### Step 9: Quality Gate Final
- `composer quality` (Pint + PHPMD)
- `npm run quality` (ESLint + Prettier)
- `php artisan test` (todos os testes passam)
- **Gate**: sem erros

---

## ⚠️ Riscos & Mitigações

| Risco | Mitigação |
|-------|-----------|
| FakeAiService não classifica tipos mistos | Adicionar coluna `type` no CSV para testes determinísticos |
| Category Select precisa filtrar por tipo | Frontend filtra `categories.where(type === itemType \|\| type === 'both')` |
| MultiSelect de Tags não existe como componente | Usar Command + Popover + Checkbox pattern (todos os primitives já existem) |
| Redirect após confirm com tipos mistos | Redirect para Dashboard — neutro para ambos os tipos |
| Quebra de rotas existentes nos testes | Atualizar TODOS os testes em um step dedicado |

---

## ✅ Critérios de Aceite (novos/atualizados)

| ID | Critério | Validação |
|----|----------|-----------|
| AC-N1 | Upload único gera preview com despesas E receitas | PHPUnit: CSV com debit+credit → preview com ambos |
| AC-N2 | Tela de preview mostra Select de categoria com AI-selected | Cypress: abrir select, verificar opção selecionada |
| AC-N3 | Tela de preview mostra MultiSelect de tags com AI-selected | Cypress: tags selecionadas aparecem como badges |
| AC-N4 | Sidebar possui item "Importações" | Cypress: sidebar-imports visible, click → import page |
| AC-N5 | Botões de importação removidos das listagens | Cypress: transactions.index não tem "Importar Despesas" |
| AC-N6 | Confirm cria transações com tipo por item | PHPUnit: mixed items → transações com types corretos |
| AC-N7 | Confirm vincula category_uuid e tags | PHPUnit: category_uuid e tags persistem na transação |
| AC-N8 | Redirect após confirm vai para dashboard | PHPUnit: assertRedirect route dashboard |
| AC-N9 | Rotas antigas não existem mais | PHPUnit: route('transactions.import.create') → 404 |
