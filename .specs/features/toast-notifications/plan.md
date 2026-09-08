# Plano: Mapeamento e Padronização de Toasts (Sonner)

## Contexto

Diversas ações no sistema (criação, edição, exclusão e outras mutações) finalizam seu processamento sem dar retorno visual ao usuário. Este plano mapeia todas as ações pendentes e define a implementação de notificações via Sonner (shadcn/ui) seguindo o padrão de referência já implementado.

## Padrão de Referência

A ação **"Gerar agora"** da funcionalidade de Recorrências (`RecurrenceController::generateNow()`) é a referência:

```php
try {
    $transaction = $recurrenceService->generateNextInstance($recurrence);
    return redirect()->back()
        ->with('success', "Transação \"{$transaction->description}\" gerada com sucesso.");
} catch (RecurrenceGenerationException $e) {
    return redirect()->back()
        ->with('error', $e->getMessage());
}
```

**Infraestrutura já existente (não precisa alterar):**
- `sonner: ^2.0.8` instalado
- `<Toaster position="top-right" closeButton />` no `AuthenticatedLayout`
- `useEffect` no `AuthenticatedLayout` que observa `flash.success` / `flash.error` e dispara `toast.success()` / `toast.error()`
- `HandleInertiaRequests` middleware já compartilha `flash.success` e `flash.error` da session

**Portanto: a implementação é 100% backend.** Basta adicionar `->with('success', '...')` e `->with('error', '...')` nos redirects dos controllers.

## Escopo: 37 ações em 12 controllers

### Legenda
- ✅ = já tem toast
- ❌ = sem toast (precisa implementar)
- ⚠️ = usa `->with('status', ...)` (precisa converter para `->with('success', ...)`)

---

### 1. AccountController (3 ações)
| Ação | Redirect atual | Status |
|------|---------------|--------|
| `store` | `redirect()->route('accounts.index', $workspace)` | ❌ |
| `update` | `redirect()->route('accounts.index', $workspace)` | ❌ |
| `destroy` | `redirect()->route('accounts.index', $workspace)` | ❌ |

### 2. TransactionController (5 ações)
| Ação | Redirect atual | Status |
|------|---------------|--------|
| `store` | `redirect()->route('transactions.index', $workspace)` | ❌ |
| `update` | `redirect()->route('transactions.index', $workspace)` | ❌ |
| `destroy` | `redirect()->route('transactions.index', $workspace)` | ❌ |
| `pay` | `redirect()->back()` | ❌ |
| `unpay` | `redirect()->back()` | ❌ |

### 3. IncomeController (5 ações)
| Ação | Redirect atual | Status |
|------|---------------|--------|
| `store` | `redirect()->route('incomes.index', $workspace)` | ❌ |
| `update` | `redirect()->route('incomes.index', $workspace)` | ❌ |
| `destroy` | `redirect()->route('incomes.index', $workspace)` | ❌ |
| `pay` | `redirect()->back()` | ❌ |
| `unpay` | `redirect()->back()` | ❌ |

### 4. RecurrenceController (4 ações — generateNow já feito ✅)
| Ação | Redirect atual | Status |
|------|---------------|--------|
| `generateNow` | `redirect()->back()` | ✅ já tem toast |
| `update` | `redirect()->route('recurrences.index', $workspace)` | ❌ |
| `destroy` | `redirect()->route('recurrences.index', $workspace)` | ❌ |
| `pause` | `redirect()->back()` | ❌ |
| `restore` | `redirect()->back()` | ❌ |

### 5. CategoryController (3 ações)
| Ação | Redirect atual | Status |
|------|---------------|--------|
| `store` | `redirect()->route('categories.index', $workspace)` | ❌ |
| `update` | `redirect()->route('categories.index', $workspace)` | ❌ |
| `destroy` | `redirect()->route('categories.index', $workspace)` | ❌ |

### 6. TagController (3 ações)
| Ação | Redirect atual | Status |
|------|---------------|--------|
| `store` | `redirect()->route('tags.index', $workspace)` | ❌ |
| `update` | `redirect()->route('tags.index', $workspace)` | ❌ |
| `destroy` | `redirect()->route('tags.index', $workspace)` | ❌ |

### 7. CreditCardController (3 ações)
| Ação | Redirect atual | Status |
|------|---------------|--------|
| `store` | `redirect()->route('cards.index', $workspace)` | ❌ |
| `update` | `redirect()->route('cards.index', $workspace)` | ❌ |
| `destroy` | `redirect()->route('cards.index', $workspace)` | ❌ |

### 8. CardExpenseController (3 ações)
| Ação | Redirect atual | Status |
|------|---------------|--------|
| `store` | `redirect()->route('cards.show', [$workspace, $card])` | ❌ |
| `update` | `redirect()->route('cards.show', [$workspace, $card])` | ❌ |
| `destroy` | `redirect()->route('cards.show', [$workspace, $card])` | ❌ |

### 9. CreditCardBillController (2 ações)
| Ação | Redirect atual | Status |
|------|---------------|--------|
| `pay` | `redirect()->back()` | ❌ |
| `unpay` | `redirect()->back()` | ❌ |

### 10. WorkspaceController (1 ação)
| Ação | Redirect atual | Status |
|------|---------------|--------|
| `store` | `redirect()->route('dashboard', [...])` | ❌ |

### 11. WorkspaceMemberController (2 ações — converter de `status`)
| Ação | Redirect atual | Status |
|------|---------------|--------|
| `updateRole` | `back()->with('status', 'Papel atualizado com sucesso.')` | ⚠️ converter |
| `destroy` | `back()->with('status', 'Membro removido do workspace.')` | ⚠️ converter |

### 12. InviteController (3 ações — converter de `status`)
| Ação | Redirect atual | Status |
|------|---------------|--------|
| `store` | `back()->with('status', 'Convite enviado...')` | ⚠️ converter |
| `accept` | `redirect()->route('dashboard', [...])->with('status', ...)` | ⚠️ converter |
| `decline` | `back()->with('status', 'Convite recusado.')` | ⚠️ converter |

---

## Mensagens de Sucesso (pt-BR)

| Controller | Ação | Mensagem |
|------------|------|----------|
| Account | store | "Conta criada com sucesso." |
| Account | update | "Conta atualizada com sucesso." |
| Account | destroy | "Conta removida com sucesso." |
| Transaction | store | "Despesa criada com sucesso." |
| Transaction | update | "Despesa atualizada com sucesso." |
| Transaction | destroy | "Despesa removida com sucesso." |
| Transaction | pay | "Despesa paga com sucesso." |
| Transaction | unpay | "Pagamento desfeito com sucesso." |
| Income | store | "Receita criada com sucesso." |
| Income | update | "Receita atualizada com sucesso." |
| Income | destroy | "Receita removida com sucesso." |
| Income | pay | "Receita recebida com sucesso." |
| Income | unpay | "Recebimento desfeito com sucesso." |
| Recurrence | update | "Recorrência atualizada com sucesso." |
| Recurrence | destroy | "Recorrência removida com sucesso." |
| Recurrence | pause | "Recorrência pausada com sucesso." |
| Recurrence | restore | "Recorrência reativada com sucesso." |
| Category | store | "Categoria criada com sucesso." |
| Category | update | "Categoria atualizada com sucesso." |
| Category | destroy | "Categoria removida com sucesso." |
| Tag | store | "Tag criada com sucesso." |
| Tag | update | "Tag atualizada com sucesso." |
| Tag | destroy | "Tag removida com sucesso." |
| CreditCard | store | "Cartão criado com sucesso." |
| CreditCard | update | "Cartão atualizado com sucesso." |
| CreditCard | destroy | "Cartão removido com sucesso." |
| CardExpense | store | "Despesa de cartão criada com sucesso." |
| CardExpense | update | "Despesa de cartão atualizada com sucesso." |
| CardExpense | destroy | "Despesa de cartão removida com sucesso." |
| CreditCardBill | pay | "Fatura paga com sucesso." |
| CreditCardBill | unpay | "Pagamento da fatura desfeito com sucesso." |
| Workspace | store | "Workspace criado com sucesso." |
| WorkspaceMember | updateRole | "Papel atualizado com sucesso." |
| WorkspaceMember | destroy | "Membro removido do workspace." |
| Invite | store | "Convite enviado com sucesso." |
| Invite | accept | "Você entrou no workspace." |
| Invite | decline | "Convite recusado." |

## Padrão de Implementação por Action

### Para actions com `redirect()->route()` (CRUD create/edit/destroy):

```php
public function store(StoreAccountRequest $request, Workspace $workspace): RedirectResponse
{
    try {
        $account = $this->accountService->create($workspace, $request->validated());
        return redirect()->route('accounts.index', $workspace)
            ->with('success', 'Conta criada com sucesso.');
    } catch (\Exception $e) {
        return redirect()->back()
            ->with('error', $e->getMessage());
    }
}
```

### Para actions com `redirect()->back()` (toggle/inline):

```php
public function pay(Workspace $workspace, Transaction $transaction): RedirectResponse
{
    try {
        $this->transactionService->pay($transaction);
        return redirect()->back()
            ->with('success', 'Despesa paga com sucesso.');
    } catch (\Exception $e) {
        return redirect()->back()
            ->with('error', $e->getMessage());
    }
}
```

### Para conversão de `->with('status', ...)` → `->with('success', ...)`:

```php
// Antes:
return back()->with('status', 'Papel atualizado com sucesso.');

// Depois:
return back()->with('success', 'Papel atualizado com sucesso.');
```

## Ordem de Implementação (fases atômicas)

Cada fase = 1 controller. Após cada fase: rodar testes + quality gate.

### Fase 1: AccountController (3 ações)
- Adicionar try-catch + `->with('success', ...)` em store, update, destroy
- Atualizar testes para assertar `session('success')`

### Fase 2: TransactionController (5 ações)
- Adicionar try-catch + `->with('success', ...)` em store, update, destroy, pay, unpay
- Atualizar testes

### Fase 3: IncomeController (5 ações)
- Adicionar try-catch + `->with('success', ...)` em store, update, destroy, pay, unpay
- Atualizar testes

### Fase 4: RecurrenceController (4 ações — generateNow já feito)
- Adicionar try-catch + `->with('success', ...)` em update, destroy, pause, restore
- Atualizar testes

### Fase 5: CategoryController (3 ações)
- Adicionar try-catch + `->with('success', ...)` em store, update, destroy
- Atualizar testes

### Fase 6: TagController (3 ações)
- Adicionar try-catch + `->with('success', ...)` em store, update, destroy
- Atualizar testes

### Fase 7: CreditCardController (3 ações)
- Adicionar try-catch + `->with('success', ...)` em store, update, destroy
- Atualizar testes

### Fase 8: CardExpenseController (3 ações)
- Adicionar try-catch + `->with('success', ...)` em store, update, destroy
- Atualizar testes

### Fase 9: CreditCardBillController (2 ações)
- Adicionar try-catch + `->with('success', ...)` em pay, unpay
- Atualizar testes

### Fase 10: WorkspaceController (1 ação)
- Adicionar try-catch + `->with('success', ...)` em store
- Atualizar testes

### Fase 11: WorkspaceMemberController (2 ações — converter de `status`)
- Converter `->with('status', ...)` → `->with('success', ...)` em updateRole, destroy
- Adicionar try-catch
- Atualizar testes

### Fase 12: InviteController (3 ações — converter de `status`)
- Converter `->with('status', ...)` → `->with('success', ...)` em store, accept, decline
- Adicionar try-catch
- Atualizar testes

## Testes

Para cada action modificada, o teste existente de redirect deve ser atualizado para:

```php
// Success case:
$response->assertRedirect();
$response->assertSessionHas('success');

// Error case (se aplicável):
$response->assertSessionHas('error');
```

## Quality Gate (após cada fase)

```bash
composer quality   # Pint + PHPMD
composer test      # PHPUnit
npm run quality    # ESLint + Prettier
```

## Riscos e Mitigações

| Risco | Mitigação |
|-------|-----------|
| Try-catch muito genérico pode mascarar bugs | Capturar `\Exception` (não `\Throwable`) para não pegar TypeError/Error |
| `->with('status', ...)` em Members/Invites não é observado pelo toast | Converter para `->with('success', ...)` — o `AuthenticatedLayout` só observa `success`/`error` |
| Mensagens de erro expostas ao usuário | Usar `$e->getMessage()` apenas; se a mensagem for técnica demais, usar mensagem genérica "Ocorreu um erro. Tente novamente." |

## Fora do Escopo

- **Auth actions** (login, register, forgot password, etc.): já têm `->with('status', ...)` e são exibidas via `GuestLayout` banner. Não são mutações de dados do workspace.
- **Frontend**: nenhuma alteração necessária. A infraestrorna (Toaster + useEffect) já está pronta.
- **Loading states**: o padrão de referência não usa `toast.promise()` / `toast.loading()`. Seguir estritamente o padrão.
