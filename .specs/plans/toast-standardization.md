# Plan — Mapeamento e Padronização de Toasts (Sonner)

## Problem

Diversas ações no sistema (criação, edição, exclusão, pagamento, etc.) finalizam seu
processamento sem dar retorno visual ao usuário. Apenas a rotina "Gerar agora" das
Recorrências possui feedback completo via toast.

## Reference Pattern — "Gerar agora" (Recorrências)

A convenção de referência segue o fluxo **backend flash → layout toast bridge**:

### Backend (`RecurrenceController::generateNow`)
```php
return redirect()->back()
    ->with('success', "Transação \"{$transaction->description}\" gerada com sucesso.");
// ou
return redirect()->back()
    ->with('error', $e->getMessage());
```

### Bridge (`HandleInertiaRequests`)
```php
$shared['flash'] = [
    'success' => session('success'),
    'error' => session('error'),
];
```

### Frontend (`AuthenticatedLayout`)
```tsx
import { Toaster, toast } from 'sonner';
const { flash } = usePage().props;
useEffect(() => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
}, [flash?.success, flash?.error]);
// ...
<Toaster position="top-right" closeButton />
```

### Text conventions (pt-BR)
- **Success**: `"Entidade \"Nome\" ação com sucesso."` — nome da entidade entre aspas duplas, termina com ponto final.
- **Error**: mensagem bruta da exceção (`$e->getMessage()`).

---

## Implementation Strategy

Seguir estritamente o padrão de referência: **backend flash + bridge existente**.
Nenhuma mudança é necessária no `AuthenticatedLayout` — ele já faz o trabalho de
renderizar toasts a partir do `flash` compartilhado. O trabalho é adicionar
`->with('success'/'error')` nos controllers silenciosos e converter flashes `status`
para `success`/`error`.

### Por que backend flash (não toast direto no frontend)?
- O `AuthenticatedLayout` já é o ponto centralizado de renderização.
- Funciona tanto para redirects (store → index) quanto para `redirect()->back()` (ações in-place).
- Zero duplicação de lógica de toast em cada página/componente.
- Consistência total com a rotina de referência.

---

## Task Breakdown (ordered, atomic)

### T1 — Type safety: adicionar `flash` em `types/index.d.ts`

Atualmente `flash: { success, error }` não está tipado em `PageProps`, embora o layout
o consuma. Adicionar a tipação para eliminar acesso solto.

**File**: `resources/js/types/index.d.ts`

**Gate**: `npx tsc --noEmit` passa sem erros.

---

### T2 — Accounts: flash em store/update/destroy

Adicionar mensagens de sucesso nos redirects do `AccountController`.

| Action | Flash success |
|---|---|
| `store` | `"Conta \"{$account->name}\" criada com sucesso."` |
| `update` | `"Conta \"{$account->name}\" atualizada com sucesso."` |
| `destroy` | `"Conta \"{$account->name}\" excluída com sucesso."` |

**File**: `app/Http/Controllers/AccountController.php`

**Gate**: feature test existente passa; novo test opcional para assertar flash.

---

### T3 — Categories: flash em store/update/destroy

Mesmo padrão de T2, adaptado para Category.

| Action | Flash success |
|---|---|
| `store` | `"Categoria \"{$category->name}\" criada com sucesso."` |
| `update` | `"Categoria \"{$category->name}\" atualizada com sucesso."` |
| `destroy` | `"Categoria \"{$category->name}\" excluída com sucesso."` |

**File**: `app/Http/Controllers/CategoryController.php`

---

### T4 — Tags: flash em store/update/destroy

Mesmo padrão, adaptado para Tag.

| Action | Flash success |
|---|---|
| `store` | `"Tag \"{$tag->name}\" criada com sucesso."` |
| `update` | `"Tag \"{$tag->name}\" atualizada com sucesso."` |
| `destroy` | `"Tag \"{$tag->name}\" excluída com sucesso."` |

**File**: `app/Http/Controllers/TagController.php`

---

### T5 — Credit Cards: flash em store/update/destroy

| Action | Flash success |
|---|---|
| `store` | `"Cartão \"{$card->name}\" criado com sucesso."` |
| `update` | `"Cartão \"{$card->name}\" atualizado com sucesso."` |
| `destroy` | `"Cartão \"{$card->name}\" excluído com sucesso."` |

**File**: `app/Http/Controllers/CreditCardController.php`

---

### T6 — Transactions (Despesas débito): flash em store/update/destroy/pay/unpay

| Action | Flash success |
|---|---|
| `store` | `"Despesa \"{$transaction->description}\" criada com sucesso."` |
| `update` | `"Despesa \"{$transaction->description}\" atualizada com sucesso."` |
| `destroy` | `"Despesa \"{$transaction->description}\" excluída com sucesso."` |
| `pay` | `"Despesa \"{$transaction->description}\" paga com sucesso."` |
| `unpay` | `"Pagamento da despesa \"{$transaction->description}\" desfeito com sucesso."` |

**File**: `app/Http/Controllers/TransactionController.php`

---

### T7 — Incomes (Receitas): flash em store/update/destroy/pay/unpay

| Action | Flash success |
|---|---|
| `store` | `"Receita \"{$income->description}\" criada com sucesso."` |
| `update` | `"Receita \"{$income->description}\" atualizada com sucesso."` |
| `destroy` | `"Receita \"{$income->description}\" excluída com sucesso."` |
| `pay` | `"Receita \"{$income->description}\" recebida com sucesso."` |
| `unpay` | `"Recebimento da receita \"{$income->description}\" desfeito com sucesso."` |

**File**: `app/Http/Controllers/IncomeController.php`

---

### T8 — Recurrences: flash em update/destroy/pause/restore

| Action | Flash success |
|---|---|
| `update` | `"Recorrência \"{$recurrence->description}\" atualizada com sucesso."` |
| `destroy` | `"Recorrência \"{$recurrence->description}\" excluída com sucesso."` |
| `pause` | `"Recorrência \"{$recurrence->description}\" pausada com sucesso."` |
| `restore` | `"Recorrência \"{$recurrence->description}\" reativada com sucesso."` |

**File**: `app/Http/Controllers/RecurrenceController.php`

---

### T9 — Card Expenses: flash em store/update/destroy

| Action | Flash success |
|---|---|
| `store` | `"Despesa de cartão adicionada com sucesso."` |
| `update` | `"Despesa de cartão atualizada com sucesso."` |
| `destroy` | `"Despesa de cartão excluída com sucesso."` |

**File**: `app/Http/Controllers/CardExpenseController.php`

---

### T10 — Credit Card Bills (Faturas): flash em pay/unpay

| Action | Flash success |
|---|---|
| `pay` | `"Fatura paga com sucesso."` |
| `unpay` | `"Pagamento da fatura desfeito com sucesso."` |

**File**: `app/Http/Controllers/CreditCardBillController.php`

---

### T11 — Workspace: flash em store/activate

| Action | Flash success |
|---|---|
| `store` | `"Workspace \"{$workspace->name}\" criado com sucesso."` |
| `activate` | `"Workspace ativado com sucesso."` |

**File**: `app/Http/Controllers/WorkspaceController.php`

---

### T12 — Members & Invites: converter `status` → `success`/`error`

Os controllers `WorkspaceMemberController` e `InviteController` usam `->with('status', ...)`,
que é invisível em páginas autenticadas (o layout só escuta `flash.success`/`flash.error`).
Converter para o padrão de referência.

| Controller | Action | Flash |
|---|---|---|
| `WorkspaceMemberController` | `remove` | `->with('success', 'Membro removido do workspace.')` |
| `WorkspaceMemberController` | `updateRole` | `->with('success', 'Papel atualizado com sucesso.')` |
| `InviteController` | `store` | `->with('success', 'Convite enviado com sucesso.')` |
| `InviteController` | `decline` | `->with('success', 'Convite recusado.')` |
| `InviteController` | `accept` | `->with('success', 'Convite aceito. Você entrou no workspace.')` |

**Files**:
- `app/Http/Controllers/WorkspaceMemberController.php`
- `app/Http/Controllers/InviteController.php`

---

### T13 — Quality gates

- `composer quality` (Pint + PHPMD)
- `npm run quality` (ESLint + Prettier)
- `npx tsc --noEmit` (typecheck strict)
- `php artisan test` (feature tests)

---

## Files Changed Summary

| File | Change |
|---|---|
| `resources/js/types/index.d.ts` | Adicionar tipagem de `flash` |
| `app/Http/Controllers/AccountController.php` | Flash success em store/update/destroy |
| `app/Http/Controllers/CategoryController.php` | Flash success em store/update/destroy |
| `app/Http/Controllers/TagController.php` | Flash success em store/update/destroy |
| `app/Http/Controllers/CreditCardController.php` | Flash success em store/update/destroy |
| `app/Http/Controllers/TransactionController.php` | Flash success em store/update/destroy/pay/unpay |
| `app/Http/Controllers/IncomeController.php` | Flash success em store/update/destroy/pay/unpay |
| `app/Http/Controllers/RecurrenceController.php` | Flash success em update/destroy/pause/restore |
| `app/Http/Controllers/CardExpenseController.php` | Flash success em store/update/destroy |
| `app/Http/Controllers/CreditCardBillController.php` | Flash success em pay/unpay |
| `app/Http/Controllers/WorkspaceController.php` | Flash success em store/activate |
| `app/Http/Controllers/WorkspaceMemberController.php` | Converter `status` → `success` |
| `app/Http/Controllers/InviteController.php` | Converter `status` → `success` |

---

## Risks / Notes

- **Nenhuma mudança no frontend** (além da tipagem): o `AuthenticatedLayout` já renderiza
  toasts a partir do `flash` compartilhado. Todo o trabalho é no backend.
- **Textos em pt-BR**: seguem a convenção da rotina de referência (entidade entre aspas, ponto final).
- **Error flashes**: controllers que usam `try/catch` com exceções de domínio devem propagar
  `$e->getMessage()` como `->with('error', ...)` — mesmo padrão do `generateNow`.
- **`window.location.reload()`**: alguns deletes (Accounts, Categories, Tags, Cards) usam reload
  completo. O flash de sessão sobrevive ao reload, então funciona. Não é o ideal mas está fora
  do escopo desta task (seguiria como refatoração separada).
- **Guest pages**: `Workspace/Select` e `Workspace/Create` estão fora do `AuthenticatedLayout`
  e não têm `<Toaster>`. O `WorkspaceController::store` redireciona para uma rota autenticada,
  então o toast aparecerá na página de destino. O `activate` também redireciona. OK.

## Out of Scope

- Refatorar `window.location.reload()` → `router.reload()`.
- Adicionar `<Toaster>` em guest pages.
- Instalar shadcn `sonner.tsx` wrapper (raw Sonner já funciona).
- Traduzir ou alterar textos existentes.
- Adicionar toasts em ações de auth (login, register) — já possuem feedback via `status` + GuestLayout.
