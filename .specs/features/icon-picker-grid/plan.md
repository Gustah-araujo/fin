# Icon Picker Grid — Implementation Plan

## Overview

Substituir o campo de texto manual de ícones nos formulários de categoria por um componente visual `IconPicker` com grid, busca reativa e infinite scroll.

---

## Current State Analysis

| Aspecto | Estado Atual |
|---------|-------------|
| Campo de ícone | `<Input>` texto — usuário digita nome Lucide manualmente |
| Biblioteca | `lucide-react` v1.24.0 |
| Renderização | `DynamicIcon` com mapa hardcoded de 20 ícones |
| Validação backend | `string max:50`, nullable — sem allowlist |
| Popover | Disponível via `@/components/ui/popover` (Radix) |
| ColorPicker | Padrão Popover + trigger — referência para o IconPicker |

---

## Architecture

### Component Tree

```
IconPicker (controlled component)
├── TriggerButton          — mostra ícone selecionado + chevron
├── Popover                — container Radix
│   ├── SearchInput        — campo de busca com debounce
│   ├── IconGrid           — grid 4 colunas com scroll
│   │   ├── IconButton     — cada ícone clicável
│   │   └── ... (paginado)
│   ├── LoadingSkeleton    — skeleton ao carregar mais
│   └── EmptyState         — "Nenhum ícone encontrado"
└── (hidden input)         — opcional, para acessibilidade
```

### Data Flow

```
IconCatalog (static array)
    ↓
filter(query) → paginated slice
    ↓
IconGrid renders visible items
    ↓
onSelect(iconKey) → onChange(iconKey) → form.setData('icon', iconKey)
```

---

## Implementation Steps

### Step 1: Extract Icon Catalog

**File:** `resources/js/lib/icon-catalog.ts`

Extrair o mapa de ícones do `DynamicIcon.tsx` para um catálogo compartilhado e expansível.

```typescript
export interface IconDefinition {
  key: string;        // 'shopping-cart'
  name: string;       // 'Shopping Cart' (display name for search)
  component: LucideIcon;
}

export const ICON_CATALOG: IconDefinition[] = [
  { key: 'shopping-cart', name: 'Shopping Cart', component: ShoppingCart },
  // ... todos os 20 existentes + novos adicionados
];

export const ICON_MAP: Record<string, LucideIcon> = ICON_CATALOG.reduce(...);
```

**Rationale:** Centraliza o catálogo para reuso entre `DynamicIcon` e `IconPicker`. Permite busca por `name` (display name) além de `key`.

**Icons to include:** Manter os 20 existentes + adicionar ~40-60 ícones comuns para finanças/pessoais (total ~60-80 ícones). Selecionar da biblioteca Lucide os mais relevantes.

---

### Step 2: Refactor DynamicIcon

**File:** `resources/js/Components/DynamicIcon.tsx`

- Importar `ICON_MAP` do catálogo central em vez de mapa local
- Adicionar suporte a `style` prop (corrige bug de cor no Index.tsx)

```typescript
interface DynamicIconProps {
  name: string | null;
  className?: string;
  size?: number;
  style?: React.CSSProperties;  // NEW: supports color override
}
```

**Zero breaking changes** — apenas adiciona prop opcional.

---

### Step 3: Create IconPicker Component

**File:** `resources/js/Components/IconPicker.tsx`

```typescript
interface IconPickerProps {
  value: string | null;
  onChange: (iconKey: string | null) => void;
  placeholder?: string;
}
```

**Internal state:**
- `isOpen: boolean` — controle do Popover
- `query: string` — termo de busca
- `page: number` — página atual do infinite scroll
- `visibleCount: number` — quantos ícones exibir (incrementa com scroll)

**Pagination logic:**
- `PAGE_SIZE = 24` (6 rows × 4 columns)
- Initial load: 24 icons
- On scroll near bottom: +24 more
- Search resets `visibleCount` to `PAGE_SIZE`

**Search logic:**
- Filter `ICON_CATALOG` by `name` or `key` (case-insensitive includes)
- Reset pagination on query change
- Debounce 150ms (local state, no API)

**Layout:**
```
PopoverContent (w-80 h-96)
├── div.p-2 (sticky top)
│   └── Input (search with Search icon)
├── div.overflow-y-auto.flex-1 (scroll container)
│   └── div.grid.grid-cols-4.gap-1
│       └── Button.variant="ghost" × N
│           └── Icon + tooltip
├── div.py-2 (loading skeleton, conditional)
└── EmptyState (conditional)
```

**Accessibility:**
- Each icon button: `aria-label={icon.name}`
- Selected icon: `aria-pressed="true"` + visual ring
- Keyboard navigation via Radix Popover focus trap

---

### Step 4: Create useInfiniteScroll Hook

**File:** `resources/js/hooks/use-infinite-scroll.ts`

```typescript
interface UseInfiniteScrollProps {
  hasMore: boolean;
  onLoadMore: () => void;
  threshold?: number;  // px from bottom to trigger
}

function useInfiniteScroll({ hasMore, onLoadMore, threshold = 100 }: UseInfiniteScrollProps): RefObject<HTMLDivElement>
```

**Logic:**
- IntersectionObserver on sentinel element at bottom of list
- When sentinel visible AND hasMore → call onLoadMore()
- Cleanup on unmount

---

### Step 5: Integrate into Category Forms

**Files:**
- `resources/js/Pages/Categories/Create.tsx`
- `resources/js/Pages/Categories/Edit.tsx`

Replace the icon `<Input>` block (lines 111-128 / 129-146):

```tsx
<div className="space-y-2">
  <Label>Ícone</Label>
  <IconPicker
    value={data.icon}
    onChange={(icon) => setData('icon', icon ?? '')}
  />
  {errors.icon && <p className="text-sm text-destructive">{errors.icon}</p>}
</div>
```

---

### Step 6: Visual Polish

- **Trigger button:** 40×40px, outline variant, shows selected icon or placeholder `Plus`/`ImageIcon`
- **Selected state:** ring-2 ring-primary on grid item
- **Hover state:** bg-accent on grid item
- **Loading skeleton:** 4 skeleton squares (animate-pulse)
- **Empty state:** centered text "Nenhum ícone encontrado" com `SearchX` icon
- **Scroll area:** max-h-64, smooth scroll, scrollbar styling

---

## Files Changed Summary

| File | Action | Description |
|------|--------|-------------|
| `resources/js/lib/icon-catalog.ts` | **NEW** | Catálogo central de ícones |
| `resources/js/Components/DynamicIcon.tsx` | **EDIT** | Importa do catálogo + aceita `style` prop |
| `resources/js/Components/IconPicker.tsx` | **NEW** | Componente principal do seletor |
| `resources/js/hooks/use-infinite-scroll.ts` | **NEW** | Hook de infinite scroll |
| `resources/js/Pages/Categories/Create.tsx` | **EDIT** | Substitui Input por IconPicker |
| `resources/js/Pages/Categories/Edit.tsx` | **EDIT** | Substitui Input por IconPicker |

**Backend changes:** NENHUM — validação atual (`string max:50`) é suficiente.

---

## Testing Strategy

### Feature Tests (PHPUnit)
Nenhum teste backend necessário — nenhuma rota/controller muda.

### Manual QA Checklist
- [ ] Popover abre ao clicar no trigger
- [ ] Ícone selecionado aparece no trigger após seleção
- [ ] Busca filtra ícones em tempo real
- [ ] Scroll carrega mais ícones automaticamente
- [ ] Estado vazio aparece quando busca não retorna resultados
- [ ] Loading skeleton aparece ao carregar mais
- [ ] Formulário envia o `icon` corretamente (Create + Edit)
- [ ] Ícone persiste ao editar categoria existente
- [ ] Estado "sem ícone selecionado" funciona (placeholder)

### Quality Gates
```bash
composer quality  # Pint + PHPMD
npm run quality   # ESLint + Prettier
```

---

## Acceptance Criteria Mapping

| Critério | Como Atende |
|----------|-------------|
| **1. Usabilidade** | Seleção visual via clique no grid, sem digitar |
| **2. Desempenho** | Renderiza 24 ícones inicialmente, infinite scroll carrega +24 |
| **3. Busca Reativa** | Filtro local com debounce, reseta paginação |
| **4. Integração** | `onChange` → `setData('icon', ...)` via useForm |

---

## Out of Scope (Future)

- Backend API para catálogo de ícones (catálogo é estático no frontend)
- Suporte a múltiplas bibliotecas de ícones (apenas Lucide)
- Upload de ícones customizados
- Favoritos/recentes
- Dark mode (projeto apenas light mode por enquanto)
