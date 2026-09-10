# CSV Import Feature — Implementation Plan

## Status: Phase 1 (AI Infrastructure) Complete — Phases 2-5 Needed

## Architecture Overview

The CSV import flow uses a **synchronous Inertia approach** (no background polling needed):

```
Upload CSV → AI Parse (Facade) → Preview (editable table + duplicates) → Confirm → Redirect
```

Since `QUEUE_CONNECTION=sync` is the default, the AI call completes before the response. The preview data is passed directly as Inertia props.

## Route Design

```
GET  /w/{workspace}/transactions/import → import.create (upload form)
POST /w/{workspace}/transactions/import → import.store (parse + preview)
POST /w/{workspace}/transactions/import/confirm → import.confirm (save)

GET  /w/{workspace}/incomes/import → incomes.import.create
POST /w/{workspace}/incomes/import → incomes.import.store
POST /w/{workspace}/incomes/import/confirm → incomes.import.confirm
```

## Implementation Tasks

### Task 1: Backend — ImportService

**File:** `app/Services/ImportService.php`

Methods:
- `parseCsv(string $csvContent, string $type): array` — delegates to `Ai::parse()`, returns structured transactions
- `detectDuplicates(Workspace $workspace, array $transactions, string $type): array` — compares against existing transactions (same value + date within ±2 days), marks duplicates
- `createTransactions(Workspace $workspace, User $user, array $items, string $type): int` — batch creates transactions, returns count

### Task 2: Backend — ImportController

**File:** `app/Http/Controllers/ImportController.php`

Methods:
- `create(Workspace $workspace, string $type): Response` — returns Inertia upload page with `type` prop
- `store(UploadCsvRequest $request, Workspace $workspace): Response` — parses CSV, detects duplicates, returns preview page
- `confirm(ConfirmImportRequest $request, Workspace $workspace): RedirectResponse` — creates transactions, redirects with toast

### Task 3: Backend — FormRequests

**Files:**
- `app/Http/Requests/UploadCsvRequest.php` — validates `file` (required, file, mimes:csv,txt, max:10240) and `type` (required, in:expense,income)
- `app/Http/Requests/ConfirmImportRequest.php` — validates `type` and `items` array with per-item fields

### Task 4: Backend — Resources

**File:** `app/Http/Resources/ImportPreviewResource.php`

Wraps parsed transaction data for the frontend preview.

### Task 5: Backend — Routes

Add to `routes/web.php` under the `w/{workspace}` prefix:
- Import routes for transactions (expenses)
- Import routes for incomes

### Task 6: Frontend — Types

**File:** `resources/js/types/import.ts`

```typescript
interface ImportPreviewItem {
  id: string; // client-side UUID
  description: string;
  value: number;
  date: string;
  type: string;
  category_name: string;
  is_duplicate: boolean;
  selected: boolean;
}

interface ImportPageProps {
  type: 'expense' | 'income';
  workspace: { uuid: string; name: string };
  preview?: ImportPreviewItem[];
  flash?: { success?: string; error?: string };
}
```

### Task 7: Frontend — Import Page (Upload + Preview)

**File:** `resources/js/Pages/Imports/Index.tsx`

Single page that handles both upload and preview states:
- **Upload state**: File input + type indicator + upload button
- **Preview state**: Editable table with checkboxes, duplicate warnings, confirm button

### Task 8: Frontend — ImportPreviewTable Component

**File:** `resources/js/Components/Import/ImportPreviewTable.tsx`

Interactive table with:
- Checkbox per row (toggle selected)
- Editable description, value, date, category fields
- Duplicate warning badge
- Select all / deselect all

### Task 9: Frontend — Import Buttons on Listing Pages

**Files to modify:**
- `resources/js/Pages/Transactions/Index.tsx` — add "Importar Despesas" button
- `resources/js/Pages/Incomes/Index.tsx` — add "Importar Receitas" button

### Task 10: Tests

**Files:**
- `tests/Feature/Import/ImportServiceTest.php` — test parse, detectDuplicates, createTransactions
- `tests/Feature/Import/ImportControllerTest.php` — test create (smoke), store, confirm

## Acceptance Criteria Traceability

| Criteria | Implementation |
|----------|---------------|
| Type isolation | Separate routes per type, type validated in FormRequest |
| AI Facade | ImportService uses `Ai::parse()` exclusively |
| Duplicate detection | ImportService::detectDuplicates() + visual indicator in preview |
| UX & feedback | Editable table + Sonner toasts via flash messages |
| Workspace scope | All queries scoped via `$workspace` route model binding |
