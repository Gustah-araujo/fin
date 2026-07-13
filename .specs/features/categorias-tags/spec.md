# Categorias e Tags — Specification

**Story ID:** CAT-01
**Phase:** P1 — MVP Core
**Parent Spec:** `.specs/features/workspace-financeiro/spec.md`

## Problem Statement

Workspace members need to organize transactions with categories and tags so that filtering, reporting, and AI insights become possible. Without categorization, every debit expense, credit card purchase, and income entry is just raw data — indistinguishable, unsearchable, and useless for generating insights like "you spent 40% more on restaurants". Categories define the transaction's nature (income vs expense context); tags provide cross-cutting labels that can overlap categories (e.g., "Urgente", "Recorrente").

## Goals

- [ ] CRUD de categorias com nome, tipo (income/expense/both), cor e ícone, escopo workspace
- [ ] CRUD de tags com nome e cor, escopo workspace
- [ ] Categoria padrão "Sem Categoria" que serve como fallback para reassignment futuro
- [ ] Ambos modelos com soft deletes desde o dia 1 (archive, não hard delete)
- [ ] Foundation pronta para relacionamentos com transações (DEBT-01, CCXP-01, INCM-01)

## Key Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Separação de recursos | Categorias e Tags são **controllers, policies e services separados** | Modelos e regras de negócio diferentes; type só existe em Category |
| Escopo | Workspace-scoped (FK `workspace_id`) | Não são globais — cada workspace tem suas próprias categorias |
| Category.type | Enum PHP `TransactionType`: `Income`, `Expense`, `Both` | Determina onde a categoria aparece nos forms (receitas, despesas, ou ambos) |
| Category.icon | String com nome de ícone Lucide (ex: `shopping-cart`) | Facilita renderização no frontend; sem upload de imagens |
| Category.color / Tag.color | String hex de 7 chars (`#FF5733`) | Padrão web; sem formatos alternativos |
| Categoria "Sem Categoria" | Criada automaticamente na criação do workspace | Infraestrutura futura: serve como destino de reassignment quando categorias são deletadas |
| Soft deletes | Laravel `SoftDeletes` em ambos | Segurança: transações futuras referenciam categorias/tags deletadas |
| Tags: relacionamento | Many-to-many polimórfico (`taggables`) — **migration agora, pivots quando transações existirem** | A tabela `taggables` só será populada em DEBT-01+; hoje é schema-only |
| Ordenação | Coluna `position` (integer nullable) para ordenação manual | Ordenação customizada é desejo comum; default = created_at |

## Out of Scope

| Feature | Reason |
|---------|--------|
| Atribuir categoria/tag a transações | Transações não existem (DEBT-01, CCXP-01, INCM-01) |
| Filtrar transações por categoria/tag | Depende de transações existirem |
| Hierarquia de categorias (subcategorias) | Complexidade desnecessária para v1; tags suprem necessidade de granularidade |
| Cores predefinidas (paleta) | Campo livre hex; paleta é decisão de UI para o Design |
| Importação/exportação de categorias | Fora do escopo v1 |
| Categoria "transfer" ou "saldo inicial" | Categorias de sistema não são necessárias agora; só "Sem Categoria" |

---

## User Stories

### P1: CRUD de Categorias ⭐ MVP

**User Story**: As a workspace member, I want to create, edit, view and delete categories with name, type, color and icon so that I can organize my income and expenses meaningfully.

**Why P1**: Categorias são o esqueleto da organização financeira. Sem elas, insights da IA, filtros e dashboards ficam impossíveis. Todo transaction future (DEBT-01, CCXP-01, INCM-01) depende de categorias existirem.

**Acceptance Criteria**:

1. WHEN a user with create permission submits category details (name, type, color, icon) THEN system SHALL create the category linked to the current workspace and redirect to the category list.

2. WHEN a user views the category list THEN system SHALL display categories grouped by type (income/expense/both) with their name, color indicator, and icon.

3. WHEN a user edits a category's name, type, color or icon THEN system SHALL update the record and preserve existing references (no cascade issues since transactions don't exist yet).

4. WHEN a user deletes a category THEN system SHALL soft-delete it (archive). The category SHALL no longer appear in lists but SHALL remain in the database for historical integrity when transactions exist.

5. WHEN the default "Sem Categoria" is displayed THEN system SHALL not offer delete option for it (it is the permanent fallback).

6. WHEN a user without create/edit permission (viewer role) attempts mutations THEN system SHALL return 403.

7. WHEN a user attempts to access a category from a different workspace THEN system SHALL return 404 (scoped route model binding).

**Independent Test**: Create 3 categories (Alimentação/expense, Salário/income, Transporte/expense) with different colors/icons → verify they appear on list grouped by type → edit one name → verify update → delete one → verify hidden from list, still in DB (soft deleted).

---

### P1: CRUD de Tags ⭐ MVP

**User Story**: As a workspace member, I want to create, edit, view and delete tags with name and color so that I can apply cross-cutting labels to any transaction regardless of its type.

**Why P1**: Tags complementam categorias adicionando uma dimensão ortogonal de organização (ex: uma despesa "Alimentação" pode ter tags "Urgente" e "Viagem"). Essencial para filtros avançados e insights.

**Acceptance Criteria**:

1. WHEN a user with create permission submits tag details (name, color) THEN system SHALL create the tag linked to the current workspace and redirect to the tag list.

2. WHEN a user views the tag list THEN system SHALL display all active tags with name and color indicator. Tags SHALL be distinct from categories in the UI (separate section/page).

3. WHEN a user edits a tag's name or color THEN system SHALL update the record.

4. WHEN a user deletes a tag THEN system SHALL soft-delete it. The tag SHALL no longer appear in lists.

5. WHEN a user without create/edit permission (viewer role) attempts mutations THEN system SHALL return 403.

6. WHEN a user attempts to access a tag from a different workspace THEN system SHALL return 404.

**Independent Test**: Create 2 tags (Urgente/red, Viagem/blue) → verify they appear on list → edit one name → verify update → delete one → verify hidden from list.

---

### P1: Categoria Padrão "Sem Categoria" ⭐ MVP

**User Story**: As a workspace owner, I want a default uncategorized category to exist automatically so that future transactions always have a valid category reference, even when user categories are deleted.

**Why P1**: A spec exige que transações órfãs sejam reassinadas para "Sem Categoria" quando a categoria original é deletada (AC #3 do workspace-financeiro spec). Essa categoria precisa existir antes das transações.

**Acceptance Criteria**:

1. WHEN a workspace is created THEN system SHALL automatically create a "Sem Categoria" category with type "Both", gray color (#9CA3AF) and "folder" icon.

2. WHEN any user views the category list THEN the default category SHALL be visible but marked as non-deletable (no delete button).

3. WHEN the default category's name, color or icon is edited THEN system SHALL allow the update (workspace owners may want to customize it, e.g., "Outros" instead of "Sem Categoria").

4. WHEN an attempt is made to delete the default category (via direct API call bypassing UI) THEN the service SHALL reject the operation.

**Independent Test**: Create workspace → verify "Sem Categoria" exists in category list → try deleting via API → 422 or 403.

---

## Edge Cases

- WHEN category name is empty THEN system SHALL reject with validation error "O nome é obrigatório".
- WHEN category name exceeds 255 characters THEN system SHALL reject with "O nome não pode ter mais de 255 caracteres".
- WHEN category type is not a valid TransactionType enum value THEN system SHALL reject with validation error.
- WHEN category color is not a valid hex color THEN system SHALL reject with "Cor inválida. Use o formato #RRGGBB".
- WHEN category icon is an empty string THEN system SHALL accept it (icon is optional; category can exist without icon).
- WHEN tag name is empty THEN system SHALL reject with validation error.
- WHEN tag name exceeds 255 characters THEN system SHALL reject.
- WHEN tag color is invalid THEN system SHALL reject with same hex validation.
- WHEN duplicate category name exists in same workspace THEN system SHALL accept it (users may have legitimate duplicates, e.g., two "Alimentação" for different contexts — tags differentiate).
- WHEN duplicate tag name exists in same workspace THEN system SHALL reject with "Já existe uma tag com esse nome" (tags have no type differentiation; duplicates serve no purpose).
- WHEN category list is empty (excluding default) THEN system SHALL show empty state with CTA "Criar primeira categoria".
- WHEN tag list is empty THEN system SHALL show empty state with CTA "Criar primeira tag".
- WHEN a workspace has >50 categories THEN list SHALL paginate or use a scrollable layout.
- WHEN a user types a hex color manually THEN system SHALL accept both uppercase and lowercase (store normalized as uppercase).
- WHEN a user provides color without `#` prefix THEN system SHALL auto-prepend `#`.

---

## Requirement Traceability

| ID       | Story                                  | Phase  | Status  |
| -------- | -------------------------------------- | ------ | ------- |
| CAT-01   | P1: CRUD de Categorias                 | Tasks   | T4-T7, T10-T12, T16-T17 |
| CAT-02   | P1: CRUD de Tags                       | Tasks   | T8-T9, T13-T15, T16, T18 |
| CAT-03   | P1: Categoria Padrão "Sem Categoria"   | Tasks   | T7 |

**Coverage:** 3 requirements, 3 mapped, 0 unmapped

---

## Success Criteria

- [ ] User can create a category in < 15 seconds from the workspace settings
- [ ] Category list renders with color + icon visual indicators for quick scanning
- [ ] Default "Sem Categoria" exists in every workspace automatically
- [ ] Tags and categories are visually distinct in the UI
- [ ] Soft delete preserves data for future transaction integrity
- [ ] Role enforcement prevents unauthorized category/tag mutations (403)
- [ ] Cross-workspace isolation (404 for accessing another workspace's data)
- [ ] Schema is forward-compatible: category FK on transactions, taggable morph table ready
