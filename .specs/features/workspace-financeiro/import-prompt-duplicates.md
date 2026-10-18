# Import: Duplicate Detection Prompt Tuning + Category/Tag Selectors Fix

## Context

Two issues reported:
1. Category and tag selectors on the import confirmation screen always render empty (no options, nothing selected).
2. AI extraction prompt doesn't handle cross-document name variations when detecting duplicates.

## Root Causes

### Issue 1: Empty Selectors
`ImportController::store()` returns only `preview` in the Inertia response — it does NOT include `categories` or `tags`. Since Inertia doesn't persist props between requests, when the page renders the preview stage after a file upload, the `categories` and `tags` props are `undefined`. The frontend has fallbacks (`categories ?? []`, `tags ?? []`) so the Select components render but with zero options.

### Issue 2: Prompt Doesn't Handle Name Variations
The current prompt in `TransactionParser::instructions()` says "descrição similar" — too vague for the AI to detect duplicates when the same merchant appears with different names across documents. Example: "Mercado São João" on a receipt vs. "SUPERMERCADO SÃO JOÃO LTDA" on a bank statement.

## Implementation Plan

### Step 1: Fix `ImportController::store()` — Add categories and tags to preview response

**File**: `app/Http/Controllers/ImportController.php`

Add `categories` and `tags` to the return value of `store()`, same as `create()` already does:

```php
return inertia('Imports/Index', [
    'preview' => $preview,
    'categories' => CategoryResource::collection(
        $workspace->categories()->orderBy('name')->get()
    ),
    'tags' => TagResource::collection(
        $workspace->tags()->orderBy('name')->get()
    ),
]);
```

This must also be added to the error/empty return paths for consistency.

### Step 2: Enhance `TransactionParser` prompt for duplicate detection

**File**: `app/Ai/Agents/TransactionParser.php`

Replace the `query_transactions` tool instruction in `instructions()` with enhanced guidance about cross-document name variations:

```
2. **query_transactions** — Consulte transações existentes no workspace para detectar duplicatas. Uma transação é potencialmente duplicada quando:
   - Existe transação existente com valor igual (±1% para arredondamento) E data próxima (±3 dias), E
   - A descrição refere-se ao mesmo estabelecimento, mesmo que com nomes diferentes entre documentos.

   **Importante sobre variação de nomes:** O mesmo estabelecimento pode aparecer com nomes completamente diferentes dependendo do tipo de documento:
   - Comprovante/Pedido: "Mercado São João", "Padaria do João", "Farmácia Popular"
   - Extrato bancário: "SUPERMERCADO SÃO JOÃO LTDA", "PADARIA JOÃO EIRELI", "FARM POPULAR S.A."
   - Fatura de cartão: "SUPERM SAO JOAO", "SJ MERCADO", "FARMACIA POP"

   Para detectar duplicatas corretamente:
   - Normalize nomes removendo sufixos jurídicos (LTDA, EIRELI, S.A., MEI, LTDA., etc.)
   - Ignore maiúsculas/minúsculas
   - Foque no "nome core" do estabelecimento (ex: "São João" em "SUPERMERCADO SÃO JOÃO LTDA" e "Mercado São João")
   - Use query_transactions primeiro filtrando por valor e data (±3 dias), depois compare as descrições normalizadas
   - Considere duplicata quando o nome core coincidir, mesmo que o nome completo seja diferente
```

### Step 3: Update tests

**File**: `tests/Feature/Import/ImportControllerTest.php`

Add assertions to verify `store()` returns categories and tags:
```php
->has('categories')
->has('tags')
```

This should be added to existing tests that check `store()` responses (`test_store_returns_preview_with_parsed_items`, etc.) and/or a new dedicated test.

## Verification

- Run `php artisan test --filter=Import` to confirm all tests pass
- Run `composer quality` to check code style
- Verify the smoke test still passes

## Requirement Traceability

- IMPT-02: "All parsed transactions in a table... Editable fields (date, description, value, category, tags)" — selectors must work
- IMPT-04: "duplicate detection runs THEN system SHALL compare parsed transactions against existing ones by date+value+description similarity" — enhanced prompt for cross-document matching
