# Categorias e Tags — Tasks

**Design**: `.specs/features/categorias-tags/design.md`
**Spec**: `.specs/features/categorias-tags/spec.md`
**Status**: Draft

---

## Execution Plan

### Phase 1: Prerequisites (Parallel)

```
T1 [P]  ──┐
           ├──→ T3
T2 [P]  ──┘
```

### Phase 2: Data Layer (Sequential)

```
T3 → T4
```

### Phase 3: Category Backend Core (Sequential)

T4 writes the test first, then builds the full backend stack:
enum → migration → model → factory → resource → policy → service → form request → controller → routes → test passes.

```
T4
```

### Phase 4: Category Remaining + Tag Core (Parallel)

T4's controller and service have all methods. T5-T7 add form requests + tests (new files). T8 builds the full tag backend stack (routes/web.php appended after categories).

```
       ┌→ T5 [P]
       ├→ T6 [P]
T4 ───┼→ T7 [P] (modifies WorkspaceService)
       └→ T8     (modifies routes/web.php, creates tag backend)
```

### Phase 5: Tag Remaining Tests (Parallel after T8)

```
T8 ──→ T9 [P]
```

### Phase 6: Frontend Pages (Parallel)

All pages depend on T4 (categories routes) and T8 (tags routes). No shared files.

```
       ┌→ T10 [P]
       ├→ T11 [P]
       ├→ T12 [P]
T4 ───┼→ T13 [P]
 T8    ├→ T14 [P]
       └→ T15 [P]
```

### Phase 7: Sidebar + E2E (Sequential)

```
T10-T15 → T16 → T17, T18 [P]
```

---

## Task Breakdown

### T1: Install shadcn Primitives [P]

**What**: Install all 7 shadcn/ui primitives needed for categories, tags, and ColorPicker
**Where**: `resources/js/Components/ui/`
**Depends on**: None
**Reuses**: Existing `components.json` shadcn config (style: base-nova, icon: lucide)
**Requirement**: CAT-01, CAT-02 (UI dependencies)

**Done when**:
- [ ] `npx shadcn@latest add button` succeeds
- [ ] `npx shadcn@latest add input` succeeds
- [ ] `npx shadcn@latest add label` succeeds
- [ ] `npx shadcn@latest add card` succeeds
- [ ] `npx shadcn@latest add badge` succeeds
- [ ] `npx shadcn@latest add select` succeeds
- [ ] `npx shadcn@latest add popover` succeeds
- [ ] All 7 `.tsx` files exist in `resources/js/Components/ui/`
- [ ] Gate: `npx tsc --noEmit` (no TypeScript errors)

**Tests**: none
**Gate**: `npx tsc --noEmit`

---

### T2: Install react-colorful + Build ColorPicker [P]

**What**: Install react-colorful via npm and build the custom ColorPicker component
**Where**:
- `package.json` (modify — add react-colorful)
- `resources/js/Components/ui/color-picker.tsx` (new)
**Depends on**: T1 (Popover, Button, Input shadcn primitives exist)
**Reuses**: shadcn Popover, Button, Input, Label from T1
**Requirement**: CAT-01, CAT-02 (color input for forms)

**Done when**:
- [ ] `npm install react-colorful` succeeds
- [ ] `react-colorful` appears in `package.json` dependencies
- [ ] `ColorPicker` component exists at `resources/js/Components/ui/color-picker.tsx`
- [ ] Component API: `<ColorPicker value={string} onChange={(color: string) => void} />`
- [ ] Trigger: 28×28px color circle inside a `Button variant="outline"`
- [ ] Popover content: `HexColorPicker` from react-colorful + `Input` for hex text
- [ ] Hex input and picker stay in sync bidirectionally
- [ ] Empty/null value shows dashed-border circle (no color selected state)
- [ ] Gate: `npx tsc --noEmit`

**Tests**: none (tested via E2E in T17, T18)
**Gate**: `npx tsc --noEmit`

---

### T3: Data Layer Foundation

**What**: Create TransactionType enum, all 3 migrations (categories, tags, taggables), both models, both factories, both resources
**Where**:
- `app/Enums/TransactionType.php`
- `database/migrations/XXXX_create_categories_table.php`
- `database/migrations/XXXX_create_tags_table.php`
- `database/migrations/XXXX_create_taggables_table.php`
- `app/Models/Category.php`
- `app/Models/Tag.php`
- `database/factories/CategoryFactory.php`
- `database/factories/TagFactory.php`
- `app/Http/Resources/CategoryResource.php`
- `app/Http/Resources/TagResource.php`
**Depends on**: T1, T2 (prerequisites only; no code dependency)
**Reuses**: AccountType enum pattern, Account model pattern, AccountFactory pattern, AccountResource pattern
**Requirement**: CAT-01, CAT-02, CAT-03

**Done when**:
- [ ] `TransactionType` enum exists with `Income`, `Expense`, `Both` backed by string + `label()` method
- [ ] Categories migration creates table with: uuid, workspace_id FK, created_by FK, name, type, color(7), icon nullable, position nullable, softDeletes, timestamps
- [ ] Tags migration creates table with: uuid, workspace_id FK, created_by FK, name, color(7), composite unique index on (workspace_id, name), softDeletes, timestamps
- [ ] Taggables migration creates table with: tag_id FK, taggable_type, taggable_id (uuid), timestamps (schema-only, no model)
- [ ] Migration runs: `php artisan migrate:fresh`
- [ ] `Category` model: `HasFactory`, `SoftDeletes`, `$fillable`, `getRouteKeyName()` → `"uuid"`, `type` cast to `TransactionType`
- [ ] `Tag` model: `HasFactory`, `SoftDeletes`, `$fillable`, `getRouteKeyName()` → `"uuid"`
- [ ] Both models: `belongsTo(Workspace::class)`, `belongsTo(User::class, 'created_by')`
- [ ] `CategoryFactory`: generates uuid, name, type, color (random hex), icon (optional)
- [ ] `TagFactory`: generates uuid, name, color (random hex)
- [ ] `CategoryResource`: returns `{ uuid, name, type, color, icon, position, created_at }`
- [ ] `TagResource`: returns `{ uuid, name, color, created_at }`
- [ ] Gate: `php artisan migrate:fresh && php artisan tinker --execute="echo App\Models\Category::factory()->make()->toJson(); echo App\Models\Tag::factory()->make()->toJson();"`

**Tests**: none (data layer; tested implicitly via feature tests in T4-T11)
**Gate**: `php artisan migrate:fresh`

---

### T4: Category Backend Core + CategoryCreationTest (TDD)

**What**: Write CategoryCreationTest first (6 tests fail), then build the complete category backend stack to make them pass.
**Where**:
- `tests/Feature/Categories/CategoryCreationTest.php`
- `app/Http/Requests/StoreCategoryRequest.php`
- `app/Policies/CategoryPolicy.php`
- `app/Services/CategoryService.php`
- `app/Http/Controllers/CategoryController.php`
- `routes/web.php` (modify — add `Route::resource('categories', CategoryController::class)`)
**Depends on**: T3 (model, factory, migration exist)
**Reuses**: AccountPolicy role-check pattern, AccountService UUID pattern, AccountController resource pattern, StoreAccountRequest validation pattern
**Requirement**: CAT-01 (category creation + list acceptance criteria)

**TDD sequence**:
1. Write `CategoryCreationTest` with 6 test methods (all fail — no routes)
2. Create `StoreCategoryRequest` (name, type, color, icon rules + pt-BR messages)
3. Create `CategoryPolicy` with all methods: `viewAny`, `create`, `update`, `delete`
4. Create `CategoryService` with all methods: `create`, `update`, `archive`, `ensureDefaultExists`
   - `create()` normalizes color (uppercase, prepend `#` if missing)
   - `update()` same normalization
   - `archive()` soft-deletes; placeholder for future transaction reassignment
   - `ensureDefaultExists()` finds or creates "Sem Categoria" (type=both, color=#9CA3AF, icon=folder)
5. Create `CategoryController` with all methods: `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`
   - `store()` authorizes via Policy, calls `CategoryService::create()`, redirects to index
   - `index()` authorizes via Policy, returns Inertia page with paginated categories via CategoryResource
   - `show()` redirects to index (no detail view for categories)
   - `destroy()` authorizes via Policy, calls `CategoryService::archive()`
6. Register `Route::resource('categories', CategoryController::class)` in web.php
7. Run `CategoryCreationTest` — all 6 tests pass
8. Run full test suite to verify no regressions

**Done when**:
- [ ] `CategoryCreationTest` passes: `php artisan test --filter=CategoryCreationTest`
  - `test_user_can_create_category` → 302, DB has category
  - `test_category_list_is_grouped_by_type` → 200 Inertia, categories present
  - `test_validation_errors_on_create` → 302 with session errors
  - `test_rejects_invalid_color_hex` → 422 validation error
  - `test_accepts_empty_icon` → 302, icon = null in DB
  - `test_color_is_normalized_to_uppercase` → 302, DB has "#FF5733" (normalized)
- [ ] All existing tests still pass: `php artisan test`
- [ ] Controller uses `$this->authorize()` before service calls
- [ ] Service generates UUID via `Str::orderedUuid()`
- [ ] Service normalizes colors (uppercase, auto-prepend `#`)

**Tests**: feature (CategoryCreationTest — 6 tests)
**Gate**: `php artisan test --filter=CategoryCreationTest` (6 pass, 0 fail)

---

### T5: Category Update + UpdateCategoryRequest (TDD) [P]

**What**: Write CategoryUpdateTest first (2 tests fail — UpdateCategoryRequest missing), then create the form request and verify.
**Where**:
- `tests/Feature/Categories/CategoryUpdateTest.php`
- `app/Http/Requests/UpdateCategoryRequest.php`
**Depends on**: T4 (CategoryController, CategoryService, routes, policy exist)
**Reuses**: CategoryFactory from T3, CategoryService::update from T4, StoreCategoryRequest pattern
**Requirement**: CAT-01.3 (edit acceptance criteria)

**TDD sequence**:
1. Write `CategoryUpdateTest` with 2 test methods (fail — UpdateCategoryRequest doesn't exist)
2. Create `UpdateCategoryRequest` (name sometimes, type sometimes, color sometimes, icon sometimes + pt-BR messages + same hex validation)
3. Run tests — all pass (CategoryController::edit, update already exist from T4)

**Done when**:
- [ ] `CategoryUpdateTest` passes: `php artisan test --filter=CategoryUpdateTest`
  - `test_user_can_update_category` → 302, DB updated
  - `test_cannot_update_category_with_invalid_type` → 422 validation error
- [ ] Gate: `php artisan test --filter=CategoryUpdateTest` (2 pass, 0 fail)

**Tests**: feature (CategoryUpdateTest — 2 tests)
**Gate**: `php artisan test --filter=CategoryUpdateTest`

---

### T6: Category Deletion + Authorization Tests [P]

**What**: Write CategoryDeletionTest + CategoryAuthorizationTest (6 tests). Most should pass already from T4 implementation — verify and fix if needed.
**Where**:
- `tests/Feature/Categories/CategoryDeletionTest.php`
- `tests/Feature/Categories/CategoryAuthorizationTest.php`
**Depends on**: T4 (CategoryController, CategoryService, CategoryPolicy exist)
**Reuses**: CategoryFactory from T3
**Requirement**: CAT-01.4 (delete), CAT-01.6 (viewer 403), edge case (cross-workspace 404)

**Done when**:
- [ ] `CategoryDeletionTest` passes: `php artisan test --filter=CategoryDeletionTest`
  - `test_soft_deletes_category` → 302, deleted_at set, still in DB
- [ ] `CategoryAuthorizationTest` passes: `php artisan test --filter=CategoryAuthorizationTest`
  - `test_viewer_cannot_create_category` → 403
  - `test_viewer_cannot_update_category` → 403
  - `test_viewer_cannot_delete_category` → 403
  - `test_cannot_access_category_from_other_workspace` → 404
- [ ] Gate: `php artisan test --filter=CategoryDeletionTest && php artisan test --filter=CategoryAuthorizationTest` (5 pass, 0 fail)

**Tests**: feature (CategoryDeletionTest — 1 test, CategoryAuthorizationTest — 4 tests)
**Gate**: `php artisan test --filter=CategoryDeletionTest && php artisan test --filter=CategoryAuthorizationTest`

---

### T7: Default Category + WorkspaceService Integration [P]

**What**: Write DefaultCategoryTest (2 tests fail — WorkspaceService not wired). Wire CategoryService::ensureDefaultExists() into WorkspaceService::create(). Default category deletion guard must work.
**Where**:
- `tests/Feature/Categories/DefaultCategoryTest.php`
- `app/Services/WorkspaceService.php` (modify — inject CategoryService, call ensureDefaultExists)
**Depends on**: T4 (CategoryService::ensureDefaultExists exists)
**Reuses**: WorkspaceService create flow, CategoryFactory from T3
**Requirement**: CAT-03 (default category acceptance criteria)

**TDD sequence**:
1. Write `DefaultCategoryTest` with 2 test methods (fail — WorkspaceService doesn't create default yet)
2. Inject `CategoryService` into `WorkspaceService` constructor
3. Call `$this->categoryService->ensureDefaultExists($workspace)` at end of `WorkspaceService::create()`
4. Run tests — all pass (CategoryService::archive guards against default deletion from T4)

**Done when**:
- [ ] `DefaultCategoryTest` passes: `php artisan test --filter=DefaultCategoryTest`
  - `test_default_category_is_created_with_workspace` → "Sem Categoria" exists in DB with correct defaults
  - `test_cannot_delete_default_category` → 422 or 403
- [ ] Gate: `php artisan test --filter=DefaultCategoryTest` (2 pass, 0 fail)

**Tests**: feature (DefaultCategoryTest — 2 tests)
**Gate**: `php artisan test --filter=DefaultCategoryTest`

---

### T8: Tag Backend Core + TagCreationTest (TDD)

**What**: Write TagCreationTest first (5 tests fail), then build the complete tag backend stack to make them pass.
**Where**:
- `tests/Feature/Tags/TagCreationTest.php`
- `app/Http/Requests/StoreTagRequest.php`
- `app/Policies/TagPolicy.php`
- `app/Services/TagService.php`
- `app/Http/Controllers/TagController.php`
- `routes/web.php` (modify — add `Route::resource('tags', TagController::class)`)
**Depends on**: T3 (model, factory, migration exist)
**Reuses**: CategoryController pattern, CategoryService pattern, StoreCategoryRequest pattern
**Requirement**: CAT-02 (tag creation + list acceptance criteria)

**TDD sequence**:
1. Write `TagCreationTest` with 5 test methods (all fail — no routes)
2. Create `StoreTagRequest` (name, color rules + pt-BR messages)
3. Create `TagPolicy` with all methods: `viewAny`, `create`, `update`, `delete`
4. Create `TagService` with all methods: `create`, `update`, `archive`
   - `create()` normalizes color, validates uniqueness per workspace (throws ValidationException if duplicate name)
   - `update()` same normalization + uniqueness check
   - `archive()` soft-deletes
5. Create `TagController` with all methods: `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`
   - `store()` authorizes via Policy, calls `TagService::create()`, redirects to index
   - `index()` authorizes via Policy, returns Inertia page with paginated tags via TagResource
   - `show()` redirects to index
   - `destroy()` authorizes via Policy, calls `TagService::archive()`
6. Append `Route::resource('tags', TagController::class)` in web.php (after categories route)
7. Run `TagCreationTest` — all 5 tests pass
8. Run full test suite to verify no regressions

**Done when**:
- [ ] `TagCreationTest` passes: `php artisan test --filter=TagCreationTest`
  - `test_user_can_create_tag` → 302, DB has tag
  - `test_tag_list_shows_all_tags` → 200 Inertia, tags present
  - `test_user_can_update_tag` → 302, DB updated
  - `test_rejects_duplicate_tag_name` → 422 "Já existe uma tag com esse nome"
  - `test_rejects_invalid_color_hex` → 422 validation error
- [ ] All existing tests still pass: `php artisan test`
- [ ] Tag names are unique per workspace (composite unique index + service validation)
- [ ] Color normalization (uppercase, auto-prepend `#`) matches CategoryService

**Tests**: feature (TagCreationTest — 5 tests)
**Gate**: `php artisan test --filter=TagCreationTest` (5 pass, 0 fail)

---

### T9: Tag Deletion + Authorization Tests [P]

**What**: Write TagDeletionTest + TagAuthorizationTest (5 tests). Verify against TagPolicy and routes.
**Where**:
- `tests/Feature/Tags/TagDeletionTest.php`
- `tests/Feature/Tags/TagAuthorizationTest.php`
**Depends on**: T8 (TagController, TagService, TagPolicy exist)
**Reuses**: TagFactory from T3
**Requirement**: CAT-02.4 (delete), CAT-02.5 (viewer 403), edge case (cross-workspace 404)

**Done when**:
- [ ] `TagDeletionTest` passes: `php artisan test --filter=TagDeletionTest`
  - `test_soft_deletes_tag` → 302, deleted_at set, still in DB
- [ ] `TagAuthorizationTest` passes: `php artisan test --filter=TagAuthorizationTest`
  - `test_viewer_cannot_create_tag` → 403
  - `test_viewer_cannot_update_tag` → 403
  - `test_viewer_cannot_delete_tag` → 403
  - `test_cannot_access_tag_from_other_workspace` → 404
- [ ] Gate: `php artisan test --filter=TagDeletionTest && php artisan test --filter=TagAuthorizationTest` (5 pass, 0 fail)

**Tests**: feature (TagDeletionTest — 1 test, TagAuthorizationTest — 4 tests)
**Gate**: `php artisan test --filter=TagDeletionTest && php artisan test --filter=TagAuthorizationTest`

---

### T10: Categories Index Page [P]

**What**: Build the category list page with grouped display, color + icon indicators, and default category distinction
**Where**: `resources/js/Pages/Categories/Index.tsx`
**Depends on**: T4 (routes + CategoryController::index returns Inertia props)
**Reuses**: `AuthenticatedLayout`, `Card/CardContent/CardHeader/CardTitle`, `Badge`, `Button`, Home.tsx grid layout pattern
**Requirement**: CAT-01.2 (list grouped by type)

**Done when**:
- [ ] Page wraps in `AuthenticatedLayout`
- [ ] Typed Props: `{ categories: { uuid, name, type, color, icon, position, created_at }[] }`
- [ ] 3 sections: "Receitas" (type=income), "Despesas" (type=expense), "Ambos" (type=both)
- [ ] Section divider: "Nenhuma categoria" when group is empty (excluding default)
- [ ] Each item shows:
  - Color dot: 16px circle with `backgroundColor = category.color`
  - Icon: dynamically rendered Lucide icon from `category.icon` string
  - Name: text
  - Type: subtle badge (opcional, já agrupado por seção)
- [ ] Default "Sem Categoria": no delete button, subtle "Padrão" badge
- [ ] Each category has "Editar" link → `route('categories.edit', { workspace, category })`
- [ ] Each category has "Excluir" button (hidden for default) with `useForm().delete()`
- [ ] Header: "Categorias" title + "Nova Categoria" button → `route('categories.create', { workspace })`
- [ ] Empty state when only default exists: "Nenhuma categoria criada" + CTA
- [ ] Gate: `npx tsc --noEmit`

**Tests**: none (tested via E2E in T17)
**Gate**: `npx tsc --noEmit`

---

### T11: Categories Create Page [P]

**What**: Build the category creation form with ColorPicker
**Where**: `resources/js/Pages/Categories/Create.tsx`
**Depends on**: T4 (routes exist), T2 (ColorPicker exists)
**Reuses**: `AuthenticatedLayout`, `Card/CardContent/CardHeader/CardTitle`, `Input`, `Label`, `Button`, `Select` (T1), `ColorPicker` (T2), `useForm()`
**Requirement**: CAT-01.1 (create acceptance criteria)

**Done when**:
- [ ] Page wraps in `AuthenticatedLayout`
- [ ] Form uses `useForm({ name: '', type: 'expense', color: '#9CA3AF', icon: '' })`
- [ ] Fields: name (Input), type (Select: Receita/Despesa/Ambos), color (ColorPicker), icon (Input with helper text)
- [ ] Labels in pt-BR: "Nome", "Tipo", "Cor", "Ícone"
- [ ] Submit calls `form.post(route('categories.store', { workspace }))`
- [ ] Cancel link → `route('categories.index', { workspace })`
- [ ] Inline errors shown below each field via `form.errors`
- [ ] Submit button disabled during `form.processing`
- [ ] Gate: `npx tsc --noEmit`

**Tests**: none (tested via E2E in T17)
**Gate**: `npx tsc --noEmit`

---

### T12: Categories Edit Page [P]

**What**: Build the category edit form (same structure as Create, pre-populated)
**Where**: `resources/js/Pages/Categories/Edit.tsx`
**Depends on**: T4 (routes exist), T11 (copy form pattern)
**Reuses**: Same components as T11, `useForm()` with initial values from props
**Requirement**: CAT-01.3 (edit acceptance criteria)

**Done when**:
- [ ] Page wraps in `AuthenticatedLayout`
- [ ] Typed Props: `{ category: { uuid, name, type, color, icon, position } }`
- [ ] Form pre-populated: `useForm({ name, type, color, icon })` from `category` prop
- [ ] Same form structure as Create
- [ ] Submit calls `form.put(route('categories.update', { workspace, category }))`
- [ ] Cancel link → `route('categories.index', { workspace })`
- [ ] Gate: `npx tsc --noEmit`

**Tests**: none (tested via E2E in T17)
**Gate**: `npx tsc --noEmit`

---

### T13: Tags Index Page [P]

**What**: Build the tag list page with color pills
**Where**: `resources/js/Pages/Tags/Index.tsx`
**Depends on**: T8 (routes + TagController::index returns Inertia props)
**Reuses**: `AuthenticatedLayout`, `Badge`, `Button`
**Requirement**: CAT-02.2 (tag list)

**Done when**:
- [ ] Page wraps in `AuthenticatedLayout`
- [ ] Typed Props: `{ tags: { uuid, name, color, created_at }[] }`
- [ ] Tag list: grid or list layout
- [ ] Each item: color pill (Badge with `backgroundColor = tag.color`), name, edit/delete buttons
- [ ] Each tag has "Editar" link → `route('tags.edit', { workspace, tag })`
- [ ] Each tag has "Excluir" button with `useForm().delete()`
- [ ] Header: "Tags" title + "Nova Tag" button → `route('tags.create', { workspace })`
- [ ] Empty state: "Nenhuma tag cadastrada" + CTA
- [ ] Gate: `npx tsc --noEmit`

**Tests**: none (tested via E2E in T18)
**Gate**: `npx tsc --noEmit`

---

### T14: Tags Create Page [P]

**What**: Build the tag creation form with ColorPicker
**Where**: `resources/js/Pages/Tags/Create.tsx`
**Depends on**: T8 (routes exist), T2 (ColorPicker exists)
**Reuses**: `AuthenticatedLayout`, `Card/CardContent/CardHeader/CardTitle`, `Input`, `Label`, `Button`, `ColorPicker` (T2), `useForm()`
**Requirement**: CAT-02.1 (create acceptance criteria)

**Done when**:
- [ ] Page wraps in `AuthenticatedLayout`
- [ ] Form uses `useForm({ name: '', color: '#3B82F6' })`
- [ ] Fields: name (Input), color (ColorPicker)
- [ ] Labels in pt-BR: "Nome", "Cor"
- [ ] Submit calls `form.post(route('tags.store', { workspace }))`
- [ ] Cancel link → `route('tags.index', { workspace })`
- [ ] Inline errors shown below each field via `form.errors`
- [ ] Submit button disabled during `form.processing`
- [ ] Gate: `npx tsc --noEmit`

**Tests**: none (tested via E2E in T18)
**Gate**: `npx tsc --noEmit`

---

### T15: Tags Edit Page [P]

**What**: Build the tag edit form (same structure as Create, pre-populated)
**Where**: `resources/js/Pages/Tags/Edit.tsx`
**Depends on**: T8 (routes exist), T14 (copy form pattern)
**Reuses**: Same components as T14, `useForm()` with initial values from props
**Requirement**: CAT-02.3 (edit acceptance criteria)

**Done when**:
- [ ] Page wraps in `AuthenticatedLayout`
- [ ] Typed Props: `{ tag: { uuid, name, color } }`
- [ ] Form pre-populated: `useForm({ name: tag.name, color: tag.color })` from `tag` prop
- [ ] Same form structure as Create
- [ ] Submit calls `form.put(route('tags.update', { workspace, tag }))`
- [ ] Cancel link → `route('tags.index', { workspace })`
- [ ] Gate: `npx tsc --noEmit`

**Tests**: none (tested via E2E in T18)
**Gate**: `npx tsc --noEmit`

---

### T16: Sidebar Navigation Update

**What**: Add "Categorias" and "Tags" navigation items to AppSidebar
**Where**: `resources/js/Components/AppSidebar.tsx` (modify)
**Depends on**: T2 (workspace shared in Inertia from accounts T2), T4 (categories routes), T8 (tags routes)
**Reuses**: Existing nav item pattern, `usePage().props.workspace`
**Requirement**: CAT-01, CAT-02 (navigation to categories and tags)

**Done when**:
- [ ] Add "Categorias" nav item with `route('categories.index', { workspace })`
- [ ] Add "Tags" nav item with `route('tags.index', { workspace })`
- [ ] Icons: use appropriate Lucide icons (e.g., `Tag` for tags, `Folders` for categories)
- [ ] Links resolve to correct workspace-scoped URLs
- [ ] Gate: `npx tsc --noEmit`

**Tests**: none (verified manually + covered by E2E nav in T17, T18)
**Gate**: `npx tsc --noEmit`

---

### T17: Cypress E2E — Categories CRUD Journey

**What**: Write end-to-end test covering full create → view → edit → delete → default category user journey
**Where**: `cypress/e2e/categories/crud.cy.js`
**Depends on**: T10, T11, T12 (all category pages rendered), T16 (sidebar nav)
**Reuses**: Existing Cypress patterns (cy.visit, cy.get, cy.contains)
**Requirement**: CAT-01, CAT-03

**Done when**:
- [ ] Test 1: "creates a category and sees it in the list"
  - Nav → "Categorias" → click "Nova Categoria" → fill name, select type, pick color, enter icon → submit → redirected to list → new category visible
- [ ] Test 2: "shows validation errors on empty submission"
  - Nav to create → submit empty → error messages visible
- [ ] Test 3: "edits an existing category"
  - On list → click "Editar" → change name → submit → updated name on list
- [ ] Test 4: "deletes a category"
  - On list → click "Excluir" (non-default) → confirm dialog → category removed from DOM
- [ ] Test 5: "shows default category and empty state"
  - New workspace → nav to categories → "Sem Categoria" visible with no delete option
- [ ] Gate: `npx cypress run --spec "cypress/e2e/categories/crud.cy.js"` (5 tests pass)

**Tests**: e2e (5 test scenarios)
**Gate**: `npx cypress run --spec "cypress/e2e/categories/crud.cy.js"`

---

### T18: Cypress E2E — Tags CRUD Journey [P]

**What**: Write end-to-end test covering full create → view → edit → delete tag user journey
**Where**: `cypress/e2e/tags/crud.cy.js`
**Depends on**: T13, T14, T15 (all tag pages rendered), T16 (sidebar nav)
**Reuses**: Existing Cypress patterns
**Requirement**: CAT-02

**Done when**:
- [ ] Test 1: "creates a tag and sees it in the list"
  - Nav → "Tags" → click "Nova Tag" → fill name, pick color → submit → redirected to list → new tag visible
- [ ] Test 2: "shows duplicate name error"
  - On list → "Nova Tag" → enter existing name → submit → error "Já existe uma tag com esse nome"
- [ ] Test 3: "edits an existing tag"
  - On list → click "Editar" → change name → submit → updated name on list
- [ ] Test 4: "deletes a tag"
  - On list → click "Excluir" → confirm dialog → tag removed from DOM
- [ ] Test 5: "shows empty state"
  - New workspace → nav to tags → "Nenhuma tag cadastrada" + CTA
- [ ] Gate: `npx cypress run --spec "cypress/e2e/tags/crud.cy.js"` (5 tests pass)

**Tests**: e2e (5 test scenarios)
**Gate**: `npx cypress run --spec "cypress/e2e/tags/crud.cy.js"`

---

## Parallel Execution Map

```
Phase 1 (Parallel):
  T1 [P] ──┐  Install shadcn primitives
           ├──→ (both complete) → T3
  T2 [P] ──┘  Install react-colorful + ColorPicker

Phase 2 (Sequential):
  T3 → T4
  Data layer → Category backend core

Phase 3 (Sequential):
  T4 → T5,T6,T7,T8
  Category core → parallel tests + tag core

Phase 4 (Parallel after T4):
  T4 complete, then:
    ├── T5 [P]  Category update tests + form request (new files)
    ├── T6 [P]  Category deletion + auth tests (new files)
    ├── T7 [P]  Default category tests (new file, modifies WorkspaceService)
    └── T8      Tag backend core (modifies routes/web.php, creates new files)

Phase 5 (Parallel after T8):
  T8 complete → T9 [P]  Tag remaining tests (new files)

Phase 6 (Parallel after T4+T8):
  T4 categories routes + T8 tags routes ready, then:
    ├── T10 [P] Categories Index
    ├── T11 [P] Categories Create
    ├── T12 [P] Categories Edit
    ├── T13 [P] Tags Index
    ├── T14 [P] Tags Create
    └── T15 [P] Tags Edit

Phase 7 (Sequential):
  T10-T15 complete → T16 (Sidebar)
  T16 complete → T17 [P], T18 [P] (E2E)

Total: 18 tasks
Max parallel workers: 7 (T10-T15 in phase 6)
```

---

## Task Granularity Check

| Task | Scope | Status |
|------|-------|--------|
| T1: Install shadcn primitives | 7 CLI commands, same directory | ⚠️ Cohesive unit |
| T2: ColorPicker component | 1 npm install + 1 new file | ✅ Granular |
| T3: Data Layer Foundation | 10 files (enum, 3 migrations, 2 models, 2 factories, 2 resources) — coupled | ⚠️ Cohesive unit |
| T4: Category Backend Core | 6 files + 6 tests — minimum stack for feature test to pass | ⚠️ Vertical slice |
| T5: Category Update | 2 files (1 form request + 1 test) | ✅ Granular |
| T6: Category Delete + Auth | 2 test files (new only) | ✅ Granular |
| T7: Default Category | 2 files (1 test + modify WorkspaceService) | ✅ Granular |
| T8: Tag Backend Core | 6 files + 5 tests — same pattern as T4 | ⚠️ Vertical slice |
| T9: Tag Delete + Auth | 2 test files (new only) | ✅ Granular |
| T10: Categories Index | 1 component | ✅ Granular |
| T11: Categories Create | 1 component | ✅ Granular |
| T12: Categories Edit | 1 component | ✅ Granular |
| T13: Tags Index | 1 component | ✅ Granular |
| T14: Tags Create | 1 component | ✅ Granular |
| T15: Tags Edit | 1 component | ✅ Granular |
| T16: Sidebar Update | 1 file modified | ✅ Granular |
| T17: Cypress Categories | 1 test file | ✅ Granular |
| T18: Cypress Tags | 1 test file | ✅ Granular |

T1, T3, T4, T8 are the largest tasks but are cohesive units that can't be split without breaking TDD (test needs model + migration + service + controller + routes to pass).

---

## Commit Plan

| Task | Commit Message |
|------|---------------|
| T1 | `chore: install shadcn/ui primitives (button, input, label, card, badge, select, popover)` |
| T2 | `feat(ui): add ColorPicker component with react-colorful` |
| T3 | `feat(categories-tags): add TransactionType enum, migrations, models, factories, resources` |
| T4 | `feat(categories): add category creation backend with feature tests` |
| T5 | `feat(categories): add category update with validation and tests` |
| T6 | `test(categories): add deletion and authorization tests` |
| T7 | `feat(categories): wire default category into workspace creation` |
| T8 | `feat(tags): add tag creation backend with feature tests` |
| T9 | `test(tags): add deletion and authorization tests` |
| T10 | `feat(categories): add category list page with grouped display` |
| T11 | `feat(categories): add category creation form page` |
| T12 | `feat(categories): add category edit form page` |
| T13 | `feat(tags): add tag list page` |
| T14 | `feat(tags): add tag creation form page` |
| T15 | `feat(tags): add tag edit form page` |
| T16 | `feat(categories-tags): wire sidebar navigation to categories and tags` |
| T17 | `test(e2e): add Cypress E2E tests for category CRUD` |
| T18 | `test(e2e): add Cypress E2E tests for tag CRUD` |

---

## Diagram-Definition Cross-Check

| Task | Depends On | Diagram Shows | Status |
|------|-----------|---------------|--------|
| T1 | None | None → T1 [P] | ✅ Match |
| T2 | T1 | None → T2 [P] | ✅ Match |
| T3 | T1, T2 | T1,T2 → T3 | ✅ Match |
| T4 | T3 | T3 → T4 | ✅ Match |
| T5 | T4 | T4 → T5 [P] | ✅ Match |
| T6 | T4 | T4 → T6 [P] | ✅ Match |
| T7 | T4 | T4 → T7 [P] | ✅ Match |
| T8 | T3 | T4 → T8 | ✅ Match |
| T9 | T8 | T8 → T9 [P] | ✅ Match |
| T10-T15 | T4, T8 | T4,T8 → T10-T15 [P] | ✅ Match |
| T16 | T10-T15 | T10-T15 → T16 | ✅ Match |
| T17, T18 | T16 | T16 → T17,T18 [P] | ✅ Match |

All dependencies match. No conflicts.
