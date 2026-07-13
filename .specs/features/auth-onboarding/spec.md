# Auth & Onboarding — Specification

## Problem Statement

Sem autenticação nenhum outro módulo do sistema funciona. O usuário precisa criar uma conta, fazer login, verificar seu email e — se for seu primeiro acesso — criar ou ser adicionado a um workspace colaborativo. Todo o fluxo deve suportar email/senha e Google OAuth com a menor fricção possível.

## Goals

- [ ] Registro e login com email/senha + Google OAuth via Laravel Socialite
- [ ] Verificação de email obrigatória antes do acesso aos workspaces
- [ ] Gerenciamento de conta: recuperação de senha, troca de senha, logout
- [ ] Sessão persistente via token `remember_me` tradicional do Laravel
- [ ] Onboarding: redirecionamento automático para criação de workspace quando o usuário tem zero workspaces
- [ ] Workspace colaborativo: criação, convites por email e papéis (admin/editor/viewer)

## Out of Scope

| Feature                         | Reason                          |
| ------------------------------- | ------------------------------- |
| Perfil do usuário               | Nome e avatar ficam para spec separado de User Profile |
| Exclusão de conta               | Spec separado                   |
| Gerenciamento de sessões ativas | Spec separado                   |
| 2FA / MFA                       | Complexidade desnecessária para v1 |

## Email Infrastructure

O projeto usa **Mailpit** como servidor SMTP de desenvolvimento (container `fin-mailpit`):

- SMTP: `mailpit:1025` — catch-all, aceita qualquer remetente/destinatário
- Web UI: `http://localhost:8026` — interface para visualizar emails enviados
- Emails enviados pelo sistema são **reais** (Laravel Mail + Mailpit), não simulados
- Em produção, bastará trocar as credenciais SMTP no `.env`

---

## User Stories

### P1: Autenticação (Registro, Login, Logout, Lembrar-me) ⭐ MVP

**User Story**: As a user, I want to register, log in, and stay logged in across browser sessions so that I can access my workspaces at any time.

**Why P1**: É a porta de entrada do sistema. Sem isso, nada funciona.

**Acceptance Criteria**:

1. WHEN a new user registers with email + password THEN system SHALL create the user account, trigger email verification, and redirect to a "verify your email" screen.
2. WHEN a user attempts to register with an already-registered email THEN system SHALL reject with a validation error "Este email já está em uso".
3. WHEN a user logs in with valid email + password AND their email is verified THEN system SHALL authenticate them and redirect to the workspace route (selector or last active).
4. WHEN a user logs in with valid email + password BUT their email is NOT verified THEN system SHALL redirect to the "verify your email" screen without authenticating.
5. WHEN a user logs in with invalid credentials THEN system SHALL reject with a generic error "Credenciais inválidas" (no field-specific hints).
6. WHEN a user checks "Lembrar de mim" during login THEN system SHALL issue a Laravel `remember_me` token so the session persists after browser close.
7. WHEN a user clicks logout THEN system SHALL terminate the session, clear the remember-me token, and redirect to the login page.

**Independent Test**: Register → see verify email screen → verify email → login → logout → login with "remember me" → close and reopen browser → still logged in.

---

### P1: Verificação de Email ⭐ MVP

**User Story**: As a user, I want to verify my email after registration so that system knows I own the email address.

**Why P1**: Garante que só emails reais acessam o sistema. Bloqueia acesso a workspaces até verificação.

**Acceptance Criteria**:

1. WHEN a user registers THEN system SHALL generate a signed verification URL and send a verification email to the user's email address.
2. WHEN a user clicks the verification link THEN system SHALL mark the email as verified and redirect to the workspace route.
3. WHEN a user requests a new verification email THEN system SHALL resend the email and display "Email de verificação reenviado".
4. WHEN an unverified user attempts to access any authenticated route THEN system SHALL redirect to the verification notice screen.
5. WHEN development mode (Mailpit) THEN verification emails SHALL be viewable at `http://localhost:8026`.

**Independent Test**: Register → see verify screen → check Mailpit at localhost:8026 → see verification email → click link → verified → redirected to workspace route.

---

### P1: Recuperação e Troca de Senha ⭐ MVP

**User Story**: As a user, I want to reset my password when I forget it and change it when I'm logged in so that I maintain account security.

**Why P1**: Segurança básica de conta. Usuário precisa conseguir recuperar acesso.

**Acceptance Criteria**:

1. WHEN a user clicks "Esqueci minha senha" and submits their email THEN system SHALL send a password reset email with a signed link (if the email exists; no user enumeration; same generic message either way).
2. WHEN a user clicks the reset link and submits a new password (min 8 chars, confirmed) THEN system SHALL update the password and redirect to login with success message "Senha redefinida com sucesso".
3. WHEN the reset link is expired or already used THEN system SHALL reject and show "Link inválido ou expirado".
4. WHEN a logged-in user changes password (current password + new password + confirmation) THEN system SHALL update the password and show "Senha alterada com sucesso".
5. WHEN a logged-in user submits the wrong current password THEN system SHALL reject with "Senha atual incorreta".
6. WHEN development mode (Mailpit) THEN password reset emails SHALL be viewable at `http://localhost:8026`.

**Independent Test**: Forgot password → receive email → click link → set new password → login with new password → change password from settings → logout → login with newest password.

---

### P1: Google OAuth ⭐ MVP

**User Story**: As a user, I want to log in with my Google account so that I don't need to remember another password.

**Why P1**: Reduz fricção de entrada. Para muitos usuários, é o método preferido de login.

**Acceptance Criteria**:

1. WHEN a user clicks "Entrar com Google" THEN system SHALL redirect to Google OAuth consent screen via Laravel Socialite.
2. WHEN Google returns authorization for a NEW email THEN system SHALL create the user account (email verified = true) and proceed to workspace route.
3. WHEN Google returns authorization for an EXISTING email (previously registered with email/password) THEN system SHALL link the Google account and authenticate the user.
4. WHEN Google OAuth fails (user denies consent, network error) THEN system SHALL redirect back to login with error "Falha na autenticação com Google".
5. WHEN a Google-authenticated user has no password set THEN the "change password" flow SHALL be unavailable until they set an initial password.

**Independent Test**: Click "Entrar com Google" → authorize → logged in → logout → login with same Google account → works.

---

### P1: Onboarding — Primeiro Workspace ⭐ MVP

**User Story**: As a new user, I want to create my first workspace immediately after login so that I can start managing finances.

**Why P1**: Sem workspace, o usuário não pode fazer nada no sistema.

**Acceptance Criteria**:

1. WHEN an authenticated user has zero workspaces THEN a global middleware SHALL redirect all requests to the workspace creation screen.
2. WHEN a user creates a workspace (name, optional description) THEN system SHALL create the workspace and assign the creating user as admin automatically.
3. WHEN a workspace is created THEN system SHALL redirect to the workspace dashboard (`/w/{workspace}`).
4. WHEN a user has one or more workspaces THEN system SHALL show a workspace selector or automatically redirect to the last active workspace.
5. WHEN a user's last active workspace is unavailable (deleted, removed) THEN system SHALL redirect to the workspace selector.

**Independent Test**: Register → verify → redirected to workspace creation → create workspace → see dashboard → logout → login → auto-redirected to workspace dashboard.

---

### P1: Convites e Papéis no Workspace ⭐ MVP

**User Story**: As a workspace admin, I want to invite other users by email with specific roles so that we can manage finances collaboratively.

**Why P1**: O diferencial colaborativo do produto depende de convites e papéis.

**Acceptance Criteria**:

1. WHEN an admin invites a user by email (with role: admin/editor/viewer) THEN system SHALL look up the email; IF the email exists THEN system SHALL create a pending invite and send an invite notification email to that user; IF the email does NOT exist THEN system SHALL respond "Convite enviado" but no invite is persisted and no email is sent.
2. WHEN an invited user views their pending invites THEN system SHALL display workspace name, inviter name, and proposed role.
3. WHEN an invited user accepts an invite THEN system SHALL add them to the workspace with the proposed role.
4. WHEN an invited user declines an invite THEN system SHALL remove the invite without adding the user.
5. WHEN a user is added to a workspace THEN system SHALL enforce role permissions:
   - **Admin**: full access (manage members, invite, change roles, remove members, all CRUD)
   - **Editor**: manage transactions and categories (CRUD on accounts, expenses, income, credit cards, categories, tags) but NOT manage members or workspace settings
   - **Viewer**: read-only access to all workspace data, no mutations
6. WHEN the last admin attempts to leave the workspace THEN system SHALL prompt to transfer admin role to another member before allowing exit.
7. WHEN a user attempts an action beyond their role THEN system SHALL reject with HTTP 403.
8. WHEN development mode (Mailpit) THEN invite notification emails SHALL be viewable at `http://localhost:8026`.

**Independent Test**: Admin invites existing user as editor → invited user sees invite → accepts → editor can create expenses → editor cannot access member management → viewer added → viewer sees data but cannot create/edit.

---

## Edge Cases

- WHEN a user registers with email but tries to login with Google using the same email THEN system SHALL link accounts seamlessly (no duplicate account).
- WHEN a user with Google OAuth tries to use "forgot password" THEN system SHALL respond "If an account exists, a reset link has been sent" (no user enumeration) but since no password exists, no email is sent.
- WHEN a user is removed from all workspaces THEN the zero-workspace middleware SHALL trigger and redirect to workspace creation.
- WHEN the verification email fails to send (SMTP error) THEN system SHALL log the error and show "Erro ao enviar email de verificação. Tente novamente."
- WHEN a user refreshes the browser during OAuth callback THEN system SHALL handle gracefully (state parameter validation).
- WHEN admin invites an email that already belongs to a workspace member THEN system SHALL reject with "Este usuário já pertence ao workspace".
- WHEN a user has >20 workspaces THEN workspace selector SHALL paginate or use a searchable list.

---

## Requirement Traceability

| ID      | Story                           | Phase  | Status  |
| ------- | ------------------------------- | ------ | ------- |
| AUTH-01 | P1: Register with email/password | Specify | Pending |
| AUTH-02 | P1: Login with email/password   | Specify | Pending |
| AUTH-03 | P1: Logout                      | Specify | Pending |
| AUTH-04 | P1: Remember me                 | Specify | Pending |
| AUTH-05 | P1: Email verification          | Specify | Pending |
| AUTH-06 | P1: Forgot/reset password       | Specify | Pending |
| AUTH-07 | P1: Change password (logged in) | Specify | Pending |
| AUTH-08 | P1: Google OAuth login          | Specify | Pending |
| AUTH-09 | P1: Zero-workspace redirect     | Specify | Pending |
| AUTH-10 | P1: Create first workspace      | Specify | Pending |
| AUTH-11 | P1: Admin auto-assignment       | Specify | Pending |
| AUTH-12 | P1: Workspace selector/redirect | Specify | Pending |
| AUTH-13 | P1: Invite by email             | Specify | Pending |
| AUTH-14 | P1: Accept/decline invite       | Specify | Pending |
| AUTH-15 | P1: Roles (admin/editor/viewer) | Specify | Pending |

**Coverage:** 15 total, 0 mapped, 15 unmapped

---

## Success Criteria

- [ ] User can register, verify email, and create a workspace in < 2 minutes
- [ ] User can invite a partner and have them accept the invite in < 30 seconds
- [ ] Logout and login with "remember me" persists session correctly across browser restarts
- [ ] Google OAuth works for both new and existing (email/password) users
- [ ] Role enforcement prevents unauthorized actions (403 on disallowed mutations)
