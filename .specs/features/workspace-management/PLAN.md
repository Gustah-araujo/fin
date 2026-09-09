# [Feature] Tela de Gerenciamento e Troca de Workspaces

> **Issue:** Tela de Gerenciamento e Troca de Workspaces
> **Status:** Plano — aguardando aprovação
> **Data:** 2026-09-09

---

## 1. Visão Geral

Implementar o fluxo completo de gestão de membros e permissões de um Workspace, além de aprimorar o componente de seleção de workspace na sidebar. O objetivo é permitir que administradores convidem pessoas, alterem papéis de acesso e removam membros do seu workspace, além de facilitar a navegação entre múltiplos workspaces.

### O que já existe (reaproveitar)

| Componente | Local | Status |
|---|---|---|
| `WorkspacePolicy` | `app/Policies/WorkspacePolicy.php` | ✅ Completo — `manageMembers`, `invite`, `viewMembers` |
| `WorkspaceService` | `app/Services/WorkspaceService.php` | ✅ `addMember`, `removeMember`, `changeRole`, `setLastVisited` |
| `InviteService` | `app/Services/InviteService.php` | ✅ `invite`, `accept`, `decline` |
| `WorkspaceMemberController` | `app/Http/Controllers/WorkspaceMemberController.php` | ⚠️ Existe mas retorna arrays manuais (viola convenção ApiResource) |
| `InviteController` | `app/Http/Controllers/InviteController.php` | ✅ Funcional |
| `Workspace/Members.tsx` | `resources/js/Pages/Workspace/Members.tsx` | ⚠️ Existe mas sem link na sidebar |
| `MemberRow`, `InviteDialog`, `RoleBadge`, `RoleSelect` | `resources/js/Components/Workspace/` | ✅ Componentes base existem |
| `HandleInertiaRequests` shared `workspaces` | `app/Http/Middleware/HandleInertiaRequests.php` | ⚠️ Existe mas sem `role` no pivot |
| `WorkspaceResource`, `UserResource`, `MemberResource` | `app/Http/Resources/` | ✅ Existem |

### O que precisa ser criado/modificado

| Componente | Tipo | Prioridade |
|---|---|---|
| Sidebar — ícone de engrenagem + dropdown de workspaces | **Modificar** | Alta |
| `WorkspaceSwitcher` dropdown component | **Criar** | Alta |
| `HandleInertiaRequests` — adicionar `role` ao shared workspaces | **Modificar** | Alta |
| `WorkspaceMemberController` — refactor para usar Resources | **Modificar** | Alta |
| Página `Workspace/Settings.tsx` (gestão completa) | **Criar** | Alta |
| Rota para a página de gestão | **Criar** | Alta |
| Testes de feature (PHPUnit) | **Criar** | Alta |

---

## 2. Plano de Implementação (passo a passo)

### Passo 1: Backend — Enriquecer `HandleInertiaRequests`

**Arquivo:** `app/Http/Middleware/HandleInertiaRequests.php`

**Mudança:** Adicionar `role` ao array `workspaces` compartilhado, para que o frontend possa exibir o papel do usuário em cada workspace e habilitar/desabilitar ações de admin.

```php
// Antes
'workspaces' => $request->user()
    ? WorkspaceResource::collection($request->user()->workspaces)
    : [],

// Depois — adicionar withPivot('role') e garantir que WorkspaceResource retorne role
'workspaces' => $request->user()
    ? WorkspaceResource::collection(
        $request->user()->workspaces()->withPivot('role')->get()
      )
    : [],
```

**Justificativa:** O `WorkspaceResource` já suporta `role` via `whenPivotLoaded`, mas o `workspaces()` relationship no Model não inclui `withPivot('role')` por padrão. Precisamos garantir que o pivot seja carregado.

**Teste:** Verificar que `workspaces` shared contém `role` para cada workspace.

---

### Passo 2: Backend — Refactor `WorkspaceMemberController` para ApiResource

**Arquivo:** `app/Http/Controllers/WorkspaceMemberController.php`

**Mudança:** Substituir os arrays manuais por `MemberResource` e `InviteResource`.

```php
// Antes (index)
return [
    'members' => $workspace->members->map(fn ($u) => [
        'uuid' => $u->uuid,
        'name' => $u->name,
        'email' => $u->email,
        'role' => $u->pivot->role,
        'joined_at' => $u->pivot->created_at->format('d/m/Y'),
    ]),
    'invites' => $workspace->invites->map(fn ($i) => [...]),
];

// Depois
return [
    'members' => MemberResource::collection(
        $workspace->members()->withPivot('role', 'created_at')->get()
    ),
    'invites' => InviteResource::collection(
        $workspace->invites()->with('inviter')->get()
    ),
];
```

**Justificativa:** Convenção do projeto — "ApiResource mandatory". Os Resources já existem e testados.

**Teste:** Feature test existente deve continuar passando.

---

### Passo 3: Backend — Adicionar rota de Workspace Settings

**Arquivo:** `routes/web.php`

**Mudança:** Adicionar rota `GET /w/{workspace}/settings` dentro do grupo `auth/verified`.

```php
// Dentro do grupo /w/{workspace}
Route::get('settings', [WorkspaceController::class, 'settings'])
    ->name('workspace.settings');
```

**Controller method** (adicionar em `WorkspaceController`):

```php
public function settings(Workspace $workspace): InertiaResponse
{
    $this->authorize('viewMembers', $workspace);

    return inertia('Workspace/Settings', [
        'members' => MemberResource::collection(
            $workspace->members()->withPivot('role', 'created_at')->get()
        ),
        'invites' => InviteResource::collection(
            $workspace->invites()->with('inviter')->get()
        ),
        'isAdmin' => $workspace->members()
            ->where('user_id', auth()->id())
            ->first()->pivot->role === WorkspaceRole::Admin->value,
    ]);
}
```

**Teste:** Feature test — admin pode acessar settings; membro normal recebe 403.

---

### Passo 4: Frontend — Criar `WorkspaceSwitcher` Component

**Arquivo:** `resources/js/Components/Workspace/WorkspaceSwitcher.tsx` (novo)

**Descrição:** Componente dropdown que:
- Exibe o nome do workspace ativo
- Mostra um `ChevronDown` para indicar que é clicável
- Ao clicar, abre um `DropdownMenu` com todos os workspaces do usuário
- Cada item mostra nome + role badge
- Ao clicar em outro workspace, faz `POST /workspace/activate` e recarrega a página
- Inclui um ícone de engrenagem (à direita) que navega para `workspace.settings`

**Props:** Nenhuma (lê de `usePage()`)

**Estrutura:**
```tsx
<DropdownMenu>
  <DropdownMenuTrigger asChild>
    <Button variant="ghost" className="w-full justify-between">
      <span>{workspace.name}</span>
      <GearIcon /> {/* separado, onClick stopPropagation → navigate to settings */}
    </Button>
  </DropdownMenuTrigger>
  <DropdownMenuContent>
    {workspaces.map(w => (
      <DropdownMenuItem key={w.uuid} onClick={() => switchWorkspace(w.uuid)}>
        {w.name} <RoleBadge role={w.role} />
      </DropdownMenuItem>
    ))}
  </DropdownMenuContent>
</DropdownMenu>
```

**Dependências shadcn:** `DropdownMenu` (instalar via `npx shadcn@latest add dropdown-menu`)

**Teste:** Renderiza lista de workspaces; clique ativa workspace correto.

---

### Passo 5: Frontend — Modificar `AppSidebar.tsx`

**Arquivo:** `resources/js/Components/AppSidebar.tsx`

**Mudança:** Substituir o bloco inferior (linhas ~237-247) que mostra apenas o nome do workspace por:

```tsx
import { WorkspaceSwitcher } from '@/Components/Workspace/WorkspaceSwitcher';

// No lugar do bloco atual:
<WorkspaceSwitcher />
```

**Detalhes:**
- O `WorkspaceSwitcher` substitui o label "Workspace" + nome + botão de collapse
- O botão de collapse da sidebar permanece separado (no header ou ao lado)
- O ícone de engrenagem fica dentro do `WorkspaceSwitcher`, à direita do nome

**Teste:** Sidebar renderiza switcher; gear icon leva para settings; dropdown lista workspaces.

---

### Passo 6: Frontend — Criar Página `Workspace/Settings.tsx`

**Arquivo:** `resources/js/Pages/Workspace/Settings.tsx` (novo)

**Descrição:** Página completa de gestão do workspace com:
- **Header:** Nome do workspace + botão "Voltar ao Dashboard"
- **Seção "Membros":** Tabela com Nome, Email, Role (com `RoleSelect` dropdown), Ações (botão remover)
- **Seção "Convites Pendentes":** Lista de invites pendentes com opção de cancelar
- **Seção "Convidar":** Formulário com email + seleção de role → `POST /w/{workspace}/invites`
- **Controle de permissão:** Apenas admin vê ações de gestão; membros normais veem lista read-only

**Props (Inertia):**
```typescript
interface Props {
  members: App.Data.MemberData[];
  invites: App.Data.InviteData[];
  isAdmin: boolean;
}
```

**Componentes utilizados:**
- `MemberRow` (existente) — adaptar para receber props tipadas
- `InviteDialog` (existente) — modal de convite
- `RoleBadge` (existente)
- `RoleSelect` (existente) — trocar role
- shadcn: `Table`, `Button`, `Card`, `Dialog`

**Toasts:** Usar `toast.success()` / `toast.error()` do Sonner (padrão já existente no projeto via flash messages do Laravel).

**Teste:** Admin vê todas as ações; membro normal vê apenas lista; convite cria com sucesso; role alterada; membro removido.

---

### Passo 7: Frontend — Atualizar tipos TypeScript

**Arquivo:** `resources/js/types/index.d.ts` (ou onde os tipos globais estão definidos)

**Mudança:** Adicionar tipos para os novos dados:

```typescript
declare global {
  namespace App {
    interface Workspace {
      uuid: string;
      name: string;
      description?: string;
      role?: 'admin' | 'editor' | 'viewer';
    }

    interface MemberData {
      user: {
        uuid: string;
        name: string;
        email: string;
        avatar?: string;
      };
      role: 'admin' | 'editor' | 'viewer';
      joined_at: string;
    }

    interface InviteData {
      uuid: string;
      email: string;
      role: 'admin' | 'editor' | 'viewer';
      status: 'pending' | 'accepted' | 'declined';
      inviter: {
        uuid: string;
        name: string;
      };
    }
  }
}
```

---

### Passo 8: Testes de Feature (PHPUnit)

**Arquivo:** `tests/Feature/Workspace/WorkspaceSettingsTest.php` (novo)

**Testes:**
1. `test_admin_can_access_workspace_settings` — Admin GET `/w/{workspace}/settings` → 200
2. `test_non_admin_cannot_access_workspace_settings` — Editor/Viewer → 403
3. `test_workspace_switcher_activate_endpoint` — POST `/workspace/activate` troca workspace e redireciona
4. `test_shared_workspaces_prop_includes_role` — Verificar que HandleInertiaRequests retorna `role`

**Arquivo:** `tests/Feature/Workspace/MemberManagementTest.php` (atualizar/estender)

**Testes adicionais:**
5. `test_member_resource_returns_correct_structure` — Verificar estrutura do MemberResource
6. `test_admin_can_invite_new_member_via_settings` — Fluxo completo de convite

---

### Passo 9: Qualidade e Verificação

- `composer quality` — Pint + PHPMD
- `npm run quality` — ESLint + Prettier
- `php artisan test --filter=Workspace` — Todos os testes de workspace passando
- `npm run build` — Build TypeScript sem erros

---

## 3. Dependências shadcn a instalar

```bash
npx shadcn@latest add dropdown-menu
npx shadcn@latest add table
npx shadcn@latest add dialog
```

(Verificar quais já existem antes de instalar)

---

## 4. Arquivos Afetados (resumo)

| Arquivo | Ação |
|---|---|
| `app/Http/Middleware/HandleInertiaRequests.php` | Modificar — adicionar role ao shared workspaces |
| `app/Http/Controllers/WorkspaceMemberController.php` | Modificar — usar Resources |
| `app/Http/Controllers/WorkspaceController.php` | Modificar — adicionar método `settings()` |
| `routes/web.php` | Modificar — adicionar rota `workspace.settings` |
| `resources/js/Components/Workspace/WorkspaceSwitcher.tsx` | **Criar** |
| `resources/js/Components/AppSidebar.tsx` | Modificar — integrar WorkspaceSwitcher |
| `resources/js/Pages/Workspace/Settings.tsx` | **Criar** |
| `resources/js/types/index.d.ts` | Modificar — adicionar tipos |
| `tests/Feature/Workspace/WorkspaceSettingsTest.php` | **Criar** |
| `tests/Feature/Workspace/MemberManagementTest.php` | Modificar — estender testes |

---

## 5. Riscos e Mitigações

| Risco | Mitigação |
|---|---|
| `changeRole()` tem bug (empty if block) | Corrigir o guard de last-admin dentro deste plano |
| `MemberResource` espera pivot no User model | Testar e ajustar se necessário — pode precisar de refactor |
| `DropdownMenu` shadcn não instalado | Instalar no início do passo 4 |
| N+1 no `HandleInertiaRequests` | Usar `withPivot` e considerar `withCount` apenas onde necessário |

---

## 6. Fora do Escopo (decisões deliberadas)

- **Edição de nome/descrição do workspace** — Será feature separada
- **Exclusão de workspace** — Requer confirmação complexa, deixar para depois
- **Transferência de ownership** — `transferAdminRole()` existe no service mas não será exposta agora
- **Notificação de convite por email** — O convite é criado mas o email não é enviado (já era assim)
- **Dark mode** — Apenas light mode (conforme design system)

---

## 7. Ordem de Execução Recomendada

```
1. HandleInertiaRequests (adicionar role)
2. WorkspaceMemberController refactor (Resources)
3. Rota + método settings no WorkspaceController
4. Tipos TypeScript
5. WorkspaceSwitcher component
6. AppSidebar integração
7. Página Settings completa
8. Testes PHPUnit
9. Qualidade (composer quality + npm run quality)
```

---

**Aguardando aprovação para iniciar implementação.**
