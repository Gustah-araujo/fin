# Workspace Management — Design Document

> **Feature:** Tela de Gerenciamento e Troca de Workspaces + Notificação de Convite por Email
> **Status:** Design — aguardando aprovação
> **Data:** 2026-09-09
> **Baseado em:** `.specs/features/workspace-management/PLAN.md` (revisado)

---

## 1. Visão Geral do Design

Este documento de design cobre duas áreas principais:

1. **Gestão de Workspaces** — troca via sidebar, página de settings, gestão de membros
2. **Notificação de Convite por Email** — envio de email quando um convite é criado

### Princípios de Design

- **Convention over Configuration:** Usar o sistema nativo de Notifications do Laravel
- **API Resource Mandatory:** Toda resposta passa por Resource
- **Service Pattern:** Lógica de negócio em Services, não em Controllers
- **TDD-First:** Testes escritos antes da implementação
- **UI em pt-BR, código em inglês**

---

## 2. Arquitetura da Notificação de Email

### 2.1 Diagrama de Fluxo

```
┌─────────────┐     ┌──────────────────┐     ┌─────────────────┐
│ Admin clica │────▶│ InviteController │────▶│ InviteService   │
│ "Convidar"  │     │ store()          │     │ invite()        │
└─────────────┘     └──────────────────┘     └────────┬────────┘
                                                       │
                                                       ▼
                                              ┌─────────────────┐
                                              │ Invite::create() │
                                              └────────┬────────┘
                                                       │
                                                       ▼
                                              ┌─────────────────┐
                                              │ Event: Invite    │
                                              │ Created          │
                                              └────────┬────────┘
                                                       │
                                                       ▼
                                              ┌─────────────────┐
                                              │ Listener: Send   │
                                              │ Invite Email     │
                                              └────────┬────────┘
                                                       │
                                                       ▼
                                              ┌─────────────────┐
                                              │ Notification:    │
                                              │ InviteNotificat.  │
                                              └────────┬────────┘
                                                       │
                                                       ▼
                                              ┌─────────────────┐
                                              │ Mailable:        │
                                              │ InviteMail       │
                                              └─────────────────┘
```

### 2.2 Decisão: Notification vs Mailable

**Decisão:** Usar **Notification** (que internamente usa Mailable).

**Justificatica:**
- Notifications são o padrão Laravel para envio de comunicações
- Permitem multi-channel futuro (database, slack, etc.) sem refactor
- O trait `Notifiable` já está no model `User`
- Testes são mais simples com `Notification::fake()`

### 2.3 Componentes a Criar

| Componente | Tipo | Local |
|---|---|---|
| `InviteCreatedEvent` | Event | `app/Events/Workspace/InviteCreated.php` |
| `SendInviteEmailListener` | Listener | `app/Listeners/Workspace/SendInviteEmail.php` |
| `NewInviteNotification` | Notification | `app/Notifications/Workspace/NewInvite.php` |
| `emails/workspace/invite.blade.php` | Mail Template | `resources/views/emails/workspace/invite.blade.php` |

---

## 3. Detalhamento dos Componentes

### 3.1 Event: `InviteCreated`

```php
// app/Events/Workspace/InviteCreated.php

namespace App\Events\Workspace;

use App\Models\Invite;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InviteCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Invite $invite,
        public readonly User $inviter,
    ) {}
}
```

**Props:**
- `invite` — o model Invite recém-criado
- `inviter` — o usuário que enviou o convite

### 3.2 Listener: `SendInviteEmail`

```php
// app/Listeners/Workspace/SendInviteEmail.php

namespace App\Listeners\Workspace;

use App\Events\Workspace\InviteCreated;
use App\Notifications\Workspace\NewInviteNotification;
use App\Models\User;

class SendInviteEmail
{
    public function handle(InviteCreated $event): void
    {
        $targetUser = User::where('email', $event->invite->email)->first();

        if ($targetUser) {
            $targetUser->notify(new NewInviteNotification(
                $event->invite,
                $event->inviter,
            ));
        }
    }
}
```

**Registro no EventServiceProvider:**

```php
// app/Providers/EventServiceProvider.php

protected $listen = [
    InviteCreated::class => [
        SendInviteEmailListener::class,
    ],
];
```

### 3.3 Notification: `NewInvite`

```php
// app/Notifications/Workspace/NewInvite.php

namespace App\Notifications\Workspace;

use App\Models\Invite;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewInviteNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Invite $invite,
        private readonly User $inviter,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $workspace = $this->invite->workspace;
        $acceptUrl = route('invites.accept', ['invite' => $this->invite->uuid]);
        $declineUrl = route('invites.decline', ['invite' => $this->invite->uuid]);

        return (new MailMessage)
            ->subject("Convite para workspace: {$workspace->name}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("{$this->inviter->name} convidou você para participar do workspace **{$workspace->name}** como **{$this->invite->role->label()}**.")
            ->action('Aceitar Convite', $acceptUrl)
            ->line('Ou, se preferir, você pode recusar este convite:')
            ->line("Este convite foi enviado por {$this->inviter->email}.")
            ->line('Se você não reconhece este convite, pode ignorar este email com segurança.');
    }
}
```

### 3.4 Mail Template (Markdown)

```blade
{{-- resources/views/emails/workspace/invite.blade.php --}}

<x-mail::message>
# Convite para Workspace

Olá, {{ $notifiable->name }}!

{{ $inviter->name }} convidou você para participar do workspace **{{ $workspace->name }}** como **{{ $role->label() }}**.

<x-mail::button :url="$acceptUrl" color="primary">
Aceitar Convite
</x-mail::button>

Ou copie e cole este link no navegador:
{{ $acceptUrl }}

Se preferir recusar:
{{ $declineUrl }}

Este convite foi enviado por {{ $inviter->email }}.

Se você não reconhece este convite, pode ignorar este email com segurança.

Atenciosamente,
Equipe Fin
</x-mail::message>
```

---

## 4. Integração com InviteService

### 4.1 Modificação no InviteService

O `InviteService::invite()` deve disparar o evento após criar o convite:

```php
// app/Services/InviteService.php — método invite()

use App\Events\Workspace\InviteCreated;

public function invite(Workspace $workspace, User $inviter, string $email, WorkspaceRole $role): ?Invite
{
    $targetUser = User::where('email', $email)->first();

    if (! $targetUser) {
        return null;
    }

    if ($workspace->members()->where('user_id', $targetUser->id)->exists()) {
        throw new HttpException(422, 'Este usuário já pertence ao workspace.');
    }

    $existingInvite = Invite::where('workspace_id', $workspace->id)
        ->where('email', $email)
        ->where('status', InviteStatus::Pending)
        ->first();

    if ($existingInvite) {
        return $existingInvite;
    }

    $invite = Invite::create([
        'uuid' => Str::orderedUuid()->toString(),
        'workspace_id' => $workspace->id,
        'email' => $email,
        'role' => $role,
        'inviter_id' => $inviter->id,
        'status' => InviteStatus::Pending,
    ]);

    // Dispatch event for email notification
    InviteCreated::dispatch($invite, $inviter);

    return $invite;
}
```

### 4.2 Modificação no InviteController

O controller precisa ajustar o feedback quando o email não existe:

```php
// app/Http/Controllers/InviteController.php — método store()

public function store(StoreInviteRequest $request, Workspace $workspace, InviteService $inviteService): RedirectResponse
{
    Gate::authorize('invite', $workspace);

    $invite = $inviteService->invite(
        $workspace,
        $request->user(),
        $request->validated()['email'],
        WorkspaceRole::from($request->validated()['role']),
    );

    if (! $invite) {
        Toast::error('Usuário não encontrado. O convite só pode ser enviado para usuários registrados.');
        return back();
    }

    Toast::success('Convite enviado com sucesso. Um email de notificação foi enviado.');
    return back();
}
```

---

## 5. Design da Tela de Settings

### 5.1 Estrutura da Página

```
┌─────────────────────────────────────────────────────────────┐
│  ← Voltar ao Dashboard          Workspace: Minha Empresa    │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  Convidar Membro                        [Convidar]  │   │
│  │  ┌──────────────────────┐  ┌──────────────────┐     │   │
│  │  │ email@example.com    │  │ Papel: Editor ▼  │     │   │
│  │  └──────────────────────┘  └──────────────────┘     │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  Membros (3)                                        │   │
│  │  ┌─────────────────────────────────────────────┐    │   │
│  │  │ 👤 João Silva  joao@email.com  Admin   ···  │    │   │
│  │  │ 👤 Maria Souza maria@email.com Editor  ···  │    │   │
│  │  │ 👤 Carlos Lima carlos@email.com Viewer  ···  │    │   │
│  │  └─────────────────────────────────────────────┘    │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  Convites Pendentes (2)                             │   │
│  │  ┌─────────────────────────────────────────────┐    │   │
│  │  │ 📧 ana@email.com  Editor  Enviado por João  │    │   │
│  │  │                              [Cancelar]     │    │   │
│  │  │ 📧 pedro@email.com Viewer Enviado por Maria │    │   │
│  │  │                              [Cancelar]     │    │   │
│  │  └─────────────────────────────────────────────┘    │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### 5.2 Componentes

| Componente | Local | Responsabilidade |
|---|---|---|
| `Settings.tsx` | `Pages/Workspace/Settings.tsx` | Página principal, orquestra seções |
| `InviteForm.tsx` | `Components/Workspace/InviteForm.tsx` | Formulário de convite (email + role) |
| `MemberList.tsx` | `Components/Workspace/MemberList.tsx` | Tabela de membros com ações |
| `PendingInvitesList.tsx` | `Components/Workspace/PendingInvitesList.tsx` | Lista de convites pendentes |
| `MemberRow.tsx` | `Components/Workspace/MemberRow.tsx` | Linha individual de membro |
| `InviteRow.tsx` | `Components/Workspace/InviteRow.tsx` | Linha individual de convite |
| `RoleSelect.tsx` | `Components/Workspace/RoleSelect.tsx` | Dropdown de seleção de papel |
| `RoleBadge.tsx` | `Components/Workspace/RoleBadge.tsx` | Badge colorido do papel |

### 5.3 Props e Tipos

```typescript
// resources/js/types/index.d.ts (adições)

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

    interface SettingsPageProps {
      members: MemberData[];
      invites: InviteData[];
      isAdmin: boolean;
    }
  }
}
```

---

## 6. Design do WorkspaceSwitcher

### 6.1 Comportamento

```
┌─────────────────────────────┐
│  🔽 Minha Empresa    ⚙️     │  ← Estado fechado
└─────────────────────────────┘

Ao clicar:

┌─────────────────────────────┐
│  🔼 Minha Empresa    ⚙️     │
├─────────────────────────────┤
│  ● Minha Empresa     Admin  │  ← Ativo (dot indicator)
│  ○ Outro Workspace   Editor │
│  ○ Pessoal           Viewer │
├─────────────────────────────┤
│  + Criar novo workspace     │
└─────────────────────────────┘
```

### 6.2 Props e Estado

```typescript
// Components/Workspace/WorkspaceSwitcher.tsx

interface Props {
  currentWorkspace: App.Data.Workspace;
  workspaces: App.Data.Workspace[];
}

// Estado interno
const [open, setOpen] = useState(false);

// Mutação
const switchForm = useForm({ workspace: '' });
const switchWorkspace = (uuid: string) => {
  switchForm.post(route('workspace.activate', { workspace: uuid }), {
    preserveScroll: true,
    onSuccess: () => router.reload(),
  });
};
```

---

## 7. Design de Testes

### 7.1 Testes de Feature (PHPUnit)

#### `tests/Feature/Workspace/InviteEmailTest.php` (NOVO)

```php
class InviteEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_invite_sends_email_notification(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $target = User::factory()->create(['email' => 'invited@example.com']);
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($admin, ['role' => WorkspaceRole::Admin->value]);

        $this->actingAs($admin)->post("/w/{$workspace->uuid}/invites", [
            'email' => 'invited@example.com',
            'role' => 'editor',
        ]);

        Notification::assertSentTo($target, NewInviteNotification::class);
    }

    public function test_invite_to_nonexistent_user_does_not_send_email(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($admin, ['role' => WorkspaceRole::Admin->value]);

        $this->actingAs($admin)->post("/w/{$workspace->uuid}/invites", [
            'email' => 'nonexistent@example.com',
            'role' => 'editor',
        ]);

        Notification::assertNothingSent();
    }

    public function test_invite_email_contains_correct_workspace_name(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $target = User::factory()->create(['email' => 'invited@example.com']);
        $workspace = Workspace::factory()->create(['name' => 'Empresa XYZ']);
        $workspace->members()->attach($admin, ['role' => WorkspaceRole::Admin->value]);

        $this->actingAs($admin)->post("/w/{$workspace->uuid}/invites", [
            'email' => 'invited@example.com',
            'role' => 'editor',
        ]);

        Notification::assertSentTo($target, NewInviteNotification::class, function ($notification) {
            $mail = $notification->toMail(User::first());
            return str_contains($mail->subject, 'Empresa XYZ');
        });
    }

    public function test_duplicate_invite_does_not_send_second_email(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $target = User::factory()->create(['email' => 'invited@example.com']);
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($admin, ['role' => WorkspaceRole::Admin->value]);

        // Primeiro convite
        $this->actingAs($admin)->post("/w/{$workspace->uuid}/invites", [
            'email' => 'invited@example.com',
            'role' => 'editor',
        ]);

        // Segundo convite (duplicado)
        $this->actingAs($admin)->post("/w/{$workspace->uuid}/invites", [
            'email' => 'invited@example.com',
            'role' => 'editor',
        ]);

        // Deve ter enviado apenas 1 notificação
        Notification::assertSentTimes(NewInviteNotification::class, 1);
    }
}
```

#### `tests/Feature/Workspace/WorkspaceSettingsTest.php` (NOVO)

```php
class WorkspaceSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_workspace_settings(): void
    {
        $admin = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($admin, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($admin)
            ->get("/w/{$workspace->uuid}/settings");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Workspace/Settings'));
    }

    public function test_non_admin_cannot_manage_members(): void
    {
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($member, ['role' => WorkspaceRole::Editor->value]);

        $response = $this->actingAs($member)
            ->get("/w/{$workspace->uuid}/settings");

        // Editor pode ver a página mas não vê ações de admin
        $response->assertOk();
        // Verificar que isAdmin = false
    }

    public function test_viewer_cannot_access_settings(): void
    {
        $viewer = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($viewer, ['role' => WorkspaceRole::Viewer->value]);

        $response = $this->actingAs($viewer)
            ->get("/w/{$workspace->uuid}/settings");

        $response->assertForbidden();
    }

    public function test_settings_page_returns_members_and_invites(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($admin, ['role' => WorkspaceRole::Admin->value]);
        $workspace->members()->attach($member, ['role' => WorkspaceRole::Editor->value]);

        $invite = Invite::factory()->create([
            'workspace_id' => $workspace->id,
            'email' => 'pending@example.com',
            'inviter_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->get("/w/{$workspace->uuid}/settings");

        $response->assertInertia(fn ($page) => $page
            ->has('members', 2)
            ->has('invites', 1)
            ->where('isAdmin', true)
        );
    }
}
```

### 7.2 Smoke Tests (Obrigatórios)

#### `tests/Feature/Workspace/WorkspaceSmokeTest.php` (NOVO)

Um teste por rota GET, garantindo HTTP 200 e renderização do componente Inertia. Estes testes pegam regressões de "tela branca" que feature tests não pegam (ex: bugs de ApiResource onde `whenLoaded` retorna `MissingValue`).

```php
class WorkspaceSmokeTest extends TestCase
{
    public function test_settings_page_returns_ok(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->get(route('workspace.settings', $workspace));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Workspace/Settings'));
    }

    public function test_members_index_returns_ok(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->get(route('workspace.members.index', $workspace));

        $response->assertOk();
    }
}
```

#### Cypress E2E Smoke Tests

Cada arquivo E2E deve incluir um teste mínimo no topo que verifica se a página renderiza sem erros de console:

```javascript
describe('Workspace Settings smoke', () => {
  it('renders without crashing', () => {
    // login, create workspace, navigate to settings
    cy.visit(`/w/${workspaceUuid}/settings`)
    cy.contains('Membros').should('be.visible')
  })
})
```

### 7.3 Testes de Estrutura de ApiResource (Obrigatórios)

Quando uma feature envolve ApiResources, os testes devem verificar a **forma** da resposta (keys + tipos), não apenas a quantidade. Isso pega bugs onde `whenLoaded` retorna `MissingValue` para relações não eager-loaded.

#### `tests/Feature/Workspace/WorkspaceSettingsTest.php` (REFORÇAR)

Adicionar assertions de estrutura:

```php
public function test_settings_page_returns_members_with_correct_resource_structure(): void
{
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $workspace = Workspace::factory()->create();
    $workspace->members()->attach($admin, ['role' => WorkspaceRole::Admin->value]);
    $workspace->members()->attach($member, ['role' => WorkspaceRole::Editor->value]);

    $response = $this->actingAs($admin)
        ->get(route('workspace.settings', $workspace));

    $response->assertInertia(fn ($page) => $page
        ->has('members', 2)
        ->where('members.0.user.uuid', $admin->uuid)
        ->where('members.0.user.name', $admin->name)
        ->where('members.0.user.email', $admin->email)
        ->where('members.0.role', 'admin')
        ->where('members.1.user.uuid', $member->uuid)
        ->where('members.1.role', 'editor')
    );
}

public function test_settings_page_returns_invites_with_correct_resource_structure(): void
{
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create();
    $workspace->members()->attach($admin, ['role' => WorkspaceRole::Admin->value]);

    $invite = Invite::factory()->create([
        'workspace_id' => $workspace->id,
        'email' => 'pending@example.com',
        'inviter_id' => $admin->id,
        'status' => InviteStatus::Pending,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('workspace.settings', $workspace));

    $response->assertInertia(fn ($page) => $page
        ->has('invites', 1)
        ->where('invites.0.uuid', $invite->uuid)
        ->where('invites.0.email', 'pending@example.com')
        ->where('invites.0.role', 'editor')
        ->where('invites.0.inviter.uuid', $admin->uuid)
        ->where('invites.0.inviter.name', $admin->name)
    );
}
```

### 7.4 Testes Existentes a Atualizar

#### `tests/Feature/Workspace/InviteTest.php`

Adicionar assertion de email nos testes existentes:

```php
public function test_admin_can_invite_existing_user(): void
{
    Notification::fake();
    // ... existing code ...

    Notification::assertSentTo($target, NewInvite::class);
}
```

---

## 8. Estrutura de Arquivos Final

### 8.1 Backend (Novos)

```
app/
├── Events/
│   └── Workspace/
│       └── InviteCreated.php          # Evento disparado ao criar convite
├── Listeners/
│   └── Workspace/
│       └── SendInviteEmail.php        # Listener que envia notification
├── Notifications/
│   └── Workspace/
│       └── NewInvite.php              # Notification com markdown mail
├── Http/
│   ├── Controllers/
│   │   ├── InviteController.php       # Modificado — feedback melhorado
│   │   ├── WorkspaceController.php    # Modificado — adiciona settings()
│   │   └── WorkspaceMemberController.php # Modificado — usa Resources
│   └── Resources/
│       └── (existentes, agora utilizados)
├── Services/
│   └── InviteService.php              # Modificado — dispatch event
└── Providers/
    └── EventServiceProvider.php       # Modificado — registra listener
```

### 8.2 Frontend (Novos)

```
resources/js/
├── Components/
│   ├── Workspace/
│   │   ├── WorkspaceSwitcher.tsx      # Dropdown de troca de workspace
│   │   ├── InviteForm.tsx             # Formulário de convite
│   │   ├── MemberList.tsx             # Lista de membros
│   │   ├── MemberRow.tsx              # Linha de membro
│   │   ├── PendingInvitesList.tsx     # Lista de convites pendentes
│   │   ├── InviteRow.tsx              # Linha de convite
│   │   ├── RoleSelect.tsx             # (existente, reutilizar)
│   │   └── RoleBadge.tsx              # (existente, reutilizar)
│   └── AppSidebar.tsx                 # Modificado — integra WorkspaceSwitcher
├── Pages/
│   └── Workspace/
│       └── Settings.tsx               # Página de gestão do workspace
└── types/
    └── index.d.ts                     # Modificado — novos tipos
```

### 8.3 Tests (Novos)

```
tests/Feature/Workspace/
├── InviteEmailTest.php                # Testes de notificação por email
├── WorkspaceSettingsTest.php          # Testes da página de settings (inclui estrutura de Resource)
├── WorkspaceSmokeTest.php             # 1 teste GET por rota — smoke tests obrigatórios
└── InviteTest.php                     # Modificado — adiciona assertions de email
```

### 8.4 Views (Novos)

```
resources/views/
└── emails/
    └── workspace/
        └── invite.blade.php           # Template markdown do email
```

---

## 9. Ordem de Implementação

```
┌─────────────────────────────────────────────────────────────────┐
│  FASE 1: Backend — Notificação de Email                        │
│  ────────────────────────────────────────────────────────────── │
│  1. Criar InviteCreated event                                   │
│  2. Criar NewInvite notification                               │
│  3. Criar SendInviteEmail listener                             │
│  4. Registrar listener no EventServiceProvider                 │
│  5. Modificar InviteService para dispatch event                │
│  6. Modificar InviteController feedback                        │
│  7. Criar template markdown do email                           │
│  8. Testes: InviteEmailTest + atualizar InviteTest             │
├─────────────────────────────────────────────────────────────────┤
│  FASE 2: Backend — Settings e Resources                         │
│  ────────────────────────────────────────────────────────────── │
│  9. HandleInertiaRequests — adicionar role ao shared           │
│  10. WorkspaceMemberController — refactor para Resources       │
│  11. Rota + método settings() no WorkspaceController           │
│  12. Testes: WorkspaceSettingsTest                             │
├─────────────────────────────────────────────────────────────────┤
│  FASE 3: Frontend — Tipos e Componentes Base                   │
│  ────────────────────────────────────────────────────────────── │
│  13. Tipos TypeScript (MemberData, InviteData, etc.)           │
│  14. Instalar shadcn: dropdown-menu                            │
│  15. Criar WorkspaceSwitcher component                         │
│  16. Integrar no AppSidebar                                    │
├─────────────────────────────────────────────────────────────────┤
│  FASE 4: Frontend — Página de Settings                         │
│  ────────────────────────────────────────────────────────────── │
│  17. Criar InviteForm component                                │
│  18. Criar MemberList + MemberRow components                   │
│  19. Criar PendingInvitesList + InviteRow components           │
│  20. Criar página Settings.tsx                                 │
├─────────────────────────────────────────────────────────────────┤
│  FASE 5: Qualidade e Verificação                               │
│  ────────────────────────────────────────────────────────────── │
│  21. composer quality (Pint + PHPMD)                           │
│  22. npm run quality (ESLint + Prettier)                       │
│  23. php artisan test --filter=Workspace                       │
│  24. npm run build (TypeScript sem erros)                      │
└─────────────────────────────────────────────────────────────────┘
```

---

## 10. Riscos e Mitigações

| Risco | Probabilidade | Impacto | Mitigação |
|---|---|---|---|
| Email cai em spam | Médio | Alto | Usar subject claro, botão de CTA, texto alternativo |
| Event/Listener não dispara | Baixo | Alto | Teste dedicado com `Notification::fake()` |
| Queue bloqueia request | Baixo | Médio | Usar `->dispatch()` síncrono (sem queue) inicialmente |
| Usuário sem conta no sistema | Alto | Baixo | Feedback claro: "Usuário não encontrado" |
| N+1 no listener | Baixo | Médio | Eager load no listener com `User::with()` |

---

## 11. Decisões Deliberadas (fora do escopo)

| Decisão | Justificativa |
|---|---|
| Edição de nome/descrição do workspace | Feature separada |
| Exclusão de workspace | Requer confirmação complexa |
| Transferência de ownership | Existe no service mas não será exposta agora |
| Dark mode | Apenas light mode (conforme design system) |
| Convite para email não registrado | Retorna erro amigável (não cria conta automática) |
| Queue assíncrona para emails | Usar sync inicialmente, migrar para queue depois |

---

## 12. Critérios de Aceitação

- [ ] Admin convida usuário → email é enviado com sucesso
- [ ] Email contém nome do workspace, nome do convidante, papel
- [ ] Email tem botão "Aceitar Convite" com link correto
- [ ] Usuário não registrado recebe feedback de erro amigável
- [ ] Convite duplicado não envia segundo email
- [ ] Admin acessa `/settings` e vê membros + convites
- [ ] Editor/Viewer não pode acessar ações de admin
- [ ] WorkspaceSwitcher mostra lista de workspaces com role
- [ ] Troca de workspace atualiza a página corretamente
- [ ] Todos os testes passando (PHPUnit + quality gates)

---

**Aguardando aprovação para iniciar implementação.**
