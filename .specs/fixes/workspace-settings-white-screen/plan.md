# Fix: Tela de configurações de workspace não abre

## Resumo do Bug

`TypeError: Cannot read properties of undefined (reading 'uuid')` ao acessar `/w/{workspace}/settings` ou `/w/{workspace}/members`. A tela fica branca.

## Causa Raiz

### 1. `MemberResource.php` — bug primário (linha 15)

```php
'user' => new UserResource($this->whenLoaded('user', $this)),
```

O controller chama `$workspace->members()->withPivot(...)->get()`, que retorna **modelos `User`** (não uma entidade "member" com relação `user`). O `whenLoaded('user', $this)` procura uma relação `user` no modelo `User` — que **não existe** (o `User` só tem `workspaces()`). Resultado: `whenLoaded` retorna um `MissingValue`, e `UserResource` é construído sobre ele, sem `uuid`.

O correto: `$this` já é o User. Deve ser:
```php
'user' => new UserResource($this),
```

### 2. `InviteResource.php` — bug secundário (linha 22)

```php
'workspace' => new WorkspaceResource($this->whenLoaded('workspace')),
```

O controller eager-loads apenas `inviter`, não `workspace`. Então `whenLoaded('workspace')` retorna `MissingValue`. O `PendingInvitesList.tsx` lê `invite.workspace.name` e crasha.

Duas opções de fix:
- (a) Eager-load `workspace` nos controllers; ou
- (b) Envolver com `when()` para omitir quando não carregado.

**Escolha: (a)** — o `workspace` é necessário no frontend (exibido no convite), então deve ser carregado.

## Arquivos a Modificar

### Fix 1: `app/Http/Resources/MemberResource.php`

```php
// Linha 15 — ANTES:
'user' => new UserResource($this->whenLoaded('user', $this)),

// DEPOIS:
'user' => new UserResource($this),
```

### Fix 2: `app/Http/Controllers/WorkspaceController.php` (linha 70)

```php
// ANTES:
'invites' => InviteResource::collection(
    $workspace->invites()->with('inviter')->get()
),

// DEPOIS:
'invites' => InviteResource::collection(
    $workspace->invites()->with(['inviter', 'workspace'])->get()
),
```

### Fix 3: `app/Http/Controllers/WorkspaceMemberController.php` (linha 30)

```php
// ANTES:
'invites' => InviteResource::collection(
    $workspace->invites()->with('inviter')->get()
),

// DEPOIS:
'invites' => InviteResource::collection(
    $workspace->invites()->with(['inviter', 'workspace'])->get()
),
```

## Por Que os Testes Não Pegaram?

`WorkspaceSettingsTest` usa `->has('members', 2)` — verifica **quantidade**, não a **forma** do item. O membro retornado tem `user: { uuid: null, ... }` (MissingValue), mas a contagem é 2. Nenhum teste verifica `members.0.user.uuid`.

## Testes a Adicionar/Corrigir

### 4. `tests/Feature/Workspace/WorkspaceSettingsTest.php`

Reforçar o teste existente `test_settings_page_returns_members_and_invites` para verificar a **forma** dos dados, não apenas contagem:

```php
$response->assertInertia(fn ($page) => $page
    ->has('members', 2)
    ->has('invites', 1)
    ->where('isAdmin', true)
    ->where('members.0.user.uuid', $admin->uuid)  // NOVO
    ->where('members.1.user.uuid', $member->uuid) // NOVO
    ->where('invites.0.workspace.uuid', $workspace->uuid) // NOVO
);
```

### 5. `tests/Feature/Workspace/MemberResourceTest.php` (NOVO)

Seguindo o padrão de `RecurrenceResourceTest` (resolver e assertar keys/tipos):

- `test_member_resource_returns_expected_shape` — asserta `user.uuid`, `role`, `joined_at`
- `test_member_resource_user_has_uuid_name_email` — asserta tipos

### 6. `tests/Feature/Workspace/InviteResourceTest.php` (NOVO)

- `test_invite_resource_returns_expected_shape` — asserta keys: uuid, email, role, status, inviter, workspace
- `test_invite_resource_types` — asserta `uuid` é string, `inviter.uuid` é string, `workspace.uuid` é string

### 7. Smoke test: `tests/Feature/Workspace/WorkspaceSmokeTest.php` (NOVO)

Um teste por rota GET do workspace group (prefixo `/w/{workspace}`), garantindo HTTP 200 para cada:

- `GET /w/{workspace}` (dashboard)
- `GET /w/{workspace}/members`
- `GET /w/{workspace}/settings`
- `GET /w/{workspace}/accounts` (resource index)
- `GET /w/{workspace}/categories`
- `GET /w/{workspace}/tags`
- `GET /w/{workspace}/transactions`
- `GET /w/{workspace}/incomes`
- `GET /w/{workspace}/recurrences`
- `GET /w/{workspace}/planning`
- `GET /w/{workspace}/cards`

Cada teste: cria user + workspace, faz GET, assert 200 + componente correto.

> Nota: A issue pergunta "não deveria existir um smoke test para cada rota?". A resposta é sim — o padrão do projeto é feature tests por ação, mas smoke tests que garantem que cada rota responde com 200 (e não 500/TypError) são uma camada extra de segurança exatamente para pegar regressões como esta.

## Ordem de Implementação

1. Fix `MemberResource.php` (linha 15)
2. Fix `WorkspaceController.php` eager-load workspace (linha 70)
3. Fix `WorkspaceMemberController.php` eager-load workspace (linha 30)
4. Reforçar `WorkspaceSettingsTest` com assertions de forma
5. Criar `MemberResourceTest`
6. Criar `InviteResourceTest`
7. Criar `WorkspaceSmokeTest`
8. Rodar `php artisan test` — tudo verde
9. Rodar `composer quality` + `npm run quality`

## Rastreabilidade

- Issue: tela de configurações não abre
- Root cause: `whenLoaded('user', $this)` em `MemberResource` — relação inexistente
- Fix: 3 arquivos backend (2 resources/controllers)
- Prevenção: 4 testes (1 reforçado, 3 novos)
