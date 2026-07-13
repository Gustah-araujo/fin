# Auth & Onboarding — Tasks

**Design**: `.specs/features/auth-onboarding/design.md`
**Status**: Draft

---

## Execution Plan

### Phase 1: Foundation

```
T1 ──→ T2 ──→ T3 ──→ T4 ──→ T5
```

Database schema, enums, and model definitions. Everything else depends on these.

### Phase 2: Services + FormRequests

```
              ┌→ T6 (AuthService + Register request + Login request) [P]
              │
T5 (models) ──┼→ T7 (Password services + 3 FormRequests) [P]
              │
              ├→ T8 (WorkspaceService + StoreWorkspace request) [P]
              │
              └→ T9 (InviteService + 2 FormRequests) [P]
```

Business logic layer. All four can run in parallel once models exist.

### Phase 3: Infrastructure

```
T6,T7,T8,T9 complete ──→ T10 (WorkspacePolicy) ──→ T11 (EnsureHasWorkspace middleware)
```

Authorization and middleware. Sequential because middleware depends on policy being registered.

### Phase 4: API Resources

```
              ┌→ T12 (UserResource + WorkspaceResource) [P]
T5 (models) ──┤
              └→ T13 (InviteResource + MemberResource) [P]
```

Can run in parallel with Phase 3 since they only depend on models (T5).

### Phase 5: Auth HTTP Layer

```
T6...T11 complete ──→ T14 ──→ T15 ──→ T16
                       │
                       └── T17 (Google OAuth) [P with T15]
```

Controllers, routes, and feature tests for authentication. Sequential within auth, but Google OAuth can run parallel with password flow.

### Phase 6: Workspace HTTP Layer

```
T14 complete ──→ T18 ──→ T19
```

Workspace controllers, routes, and feature tests. Depends on auth controllers because workspace routes share auth middleware.

### Phase 7: Shared Props + Layouts

```
              ┌→ T20 (HandleInertiaRequests shared props) [P]
T13 complete ──┤
              └→ T21 (GuestLayout) [P]
```

Frontend infrastructure. Both parallel.

### Phase 8: Frontend Pages

```
T20,T21 complete ──→ T22..T28 (7 pages in parallel)
```

```
              ┌→ T22 (Login page) [P]
              ├→ T23 (Register page) [P]
              ├→ T24 (ForgotPassword page) [P]
              ├→ T25 (ResetPassword page) [P]
              ├→ T26 (VerifyEmail page) [P]
T20,T21 ──────┼→ T27 (Workspace/Create page) [P]
              └→ T28 (Workspace/Select page) [P]
```

All 7 pages can run in parallel since they only depend on layouts/resources.

### Phase 9: Components + Integration

```
              ┌→ T29 (New components: 6 components) [P]
              ├→ T30 (Update UserMenu) [P]
T28 complete ──┤→ T31 (Update AppSidebar) [P]
              └→ T32 (E2E tests + full gate)
```

Frontend polish and E2E validation.

---

## Task Breakdown

### T1: Install Laravel Socialite

**What**: Install `laravel/socialite` via Composer
**Where**: `composer.json`
**Depends on**: None
**Reuses**: N/A
**Requirement**: AUTH-08

**Tools**:
- MCP: NONE
- Skill: NONE

**Done when**:
- [ ] `composer require laravel/socialite` succeeds
- [ ] Service provider auto-discovered (Laravel 13 package discovery)
- [ ] `Socialite` facade available

**Tests**: none
**Gate**: `composer validate`

---

### T2: Create Database Migrations

**What**: New migration adding `uuid`, `google_id`, `avatar` to `users` table + create `workspaces` table + create `invites` table
**Where**:
- `database/migrations/...add_uuid_to_users_table.php`
- `database/migrations/...create_workspaces_table.php`
- `database/migrations/...create_invites_table.php`
**Depends on**: T1
**Reuses**: Existing users table migration pattern (timestamps, indexes)
**Requirement**: AUTH-01, AUTH-10, AUTH-13

**Done when**:
- [ ] Users migration adds `uuid` (uuid, unique, index), `google_id` (string, nullable, unique), `avatar` (string, nullable)
- [ ] Workspaces migration: `id` (auto-inc PK), `uuid` (unique, index), `name` (string), `description` (text, nullable), timestamps
- [ ] `workspace_user` pivot: `workspace_id`, `user_id` (compound PK), `role` (string, default 'admin'), `last_visited_at` (nullable), timestamps, foreign keys cascade on delete
- [ ] Invites migration: `id` (auto-inc PK), `uuid` (unique, index), `workspace_id` (FK), `email` (string), `role` (string), `inviter_id` (FK users), `status` (string, default 'pending'), timestamps
- [ ] `php artisan migrate` runs without errors

**Tests**: feature
**Gate**: `php artisan migrate:fresh && php artisan test --filter=MigrationTest`

**Verify**:
```bash
php artisan migrate:status  # all migrations show "Ran"
php artisan db:show         # verify workspaces, invites, workspace_user tables exist
```

---

### T3: Create Enums

**What**: Create `WorkspaceRole` and `InviteStatus` PHP 8.3 native enums
**Where**:
- `app/Enums/WorkspaceRole.php`
- `app/Enums/InviteStatus.php`
**Depends on**: T2
**Reuses**: D-17 (PHP 8.3 native enums pattern)
**Requirement**: AUTH-13, AUTH-15

**Done when**:
- [ ] `WorkspaceRole`: backed string enum with `Admin = 'admin'`, `Editor = 'editor'`, `Viewer = 'viewer'`
- [ ] `WorkspaceRole`: `getLabel()` method returning pt-BR labels
- [ ] `InviteStatus`: backed string enum with `Pending = 'pending'`, `Accepted = 'accepted'`, `Declined = 'declined'`
- [ ] Both enums declare strict_types and follow App\Enums namespace

**Tests**: feature
**Gate**: `php artisan test --filter=EnumTest`

---

### T4: Update User Model + Create Workspace & Invite Models

**What**: Update User model with UUID route key, Google fields, workspace relationship; create Workspace and Invite Eloquent models
**Where**:
- `app/Models/User.php` (update)
- `app/Models/Workspace.php` (new)
- `app/Models/Invite.php` (new)
**Depends on**: T2, T3
**Reuses**: D-04 (UUID route model binding), D-03 (PascalCase models), existing User model attributes
**Requirement**: AUTH-01, AUTH-10, AUTH-11, AUTH-13

**Done when**:
- [ ] User model: `getRouteKeyName()` returns `'uuid'`; added `#[Fillable]` for `uuid`, `google_id`, `avatar`; `HasUuids` trait not needed (we generate manually)
- [ ] User model: `workspaces()` belongsToMany with `->withPivot('role', 'last_visited_at')->withTimestamps()`
- [ ] Workspace model: `getRouteKeyName()` returns `'uuid'`; `members()` belongsToMany with pivot; `invites()` hasMany; `fillable` for name and description
- [ ] Invite model: `getRouteKeyName()` returns `'uuid'`; `workspace()` belongsTo; `inviter()` belongsTo; `fillable` for email, role, status; casts role to enum and status to enum
- [ ] All models use `HasFactory` trait
- [ ] Factories created: `UserFactory` updated (uuid generation), `WorkspaceFactory` created, `InviteFactory` created

**Tests**: feature
**Gate**: `php artisan test --filter=ModelTest`

**Verify**:
```bash
php artisan tinker --execute="App\Models\User::factory()->create()->uuid"  # outputs a UUID
php artisan tinker --execute="App\Models\Workspace::factory()->create()->members()->attach(App\Models\User::first(), ['role' => 'admin'])"
```

---

### T5: Create Model Factories

**What**: Create `WorkspaceFactory` and `InviteFactory`; update `UserFactory` with UUID and Google fields
**Where**:
- `database/factories/UserFactory.php` (update)
- `database/factories/WorkspaceFactory.php` (new)
- `database/factories/InviteFactory.php` (new)
**Depends on**: T4
**Reuses**: Existing `UserFactory` pattern, `Str::orderedUuid()`
**Requirement**: AUTH-01, AUTH-10, AUTH-13 (supporting TDD for all downstream tasks)

**Done when**:
- [ ] `UserFactory`: generates `uuid` via `Str::orderedUuid()`, nullable `google_id`, nullable `avatar`
- [ ] `WorkspaceFactory`: generates `uuid`, `name` (fake company), optional `description`
- [ ] `InviteFactory`: generates `uuid`, references workspace and inviter, random role
- [ ] `php artisan tinker` can create all three factory types

**Tests**: feature
**Gate**: `php artisan test --filter=FactoryTest`

---

### T6: Create AuthService + Registration & Login FormRequests

**What**: `AuthService` (register, authenticate, verify email, resend verification) + `StoreRegisteredUserRequest` + `LoginRequest`
**Where**:
- `app/Services/AuthService.php`
- `app/Http/Requests/StoreRegisteredUserRequest.php`
- `app/Http/Requests/LoginRequest.php`
**Depends on**: T4
**Reuses**: D-16 (Service classes), D-05 (FormRequests per action), Laravel `Auth` facade, `Illuminate\Auth\Events\Registered`
**Requirement**: AUTH-01, AUTH-02, AUTH-04, AUTH-05

**Done when**:
- [ ] `AuthService::register(array $data): User` — creates user, fires `Registered` event, returns user
- [ ] `AuthService::authenticate(array $credentials, bool $remember): void` — `Auth::attempt()` or throws `ValidationException`
- [ ] `AuthService::sendVerificationEmail(User $user): void` — calls `$user->sendEmailVerificationNotification()`
- [ ] `AuthService::resendVerificationEmail(User $user): void` — throttle-aware resend (rate limit 6/min)
- [ ] `StoreRegisteredUserRequest`: `name` required|string|max:255, `email` required|email|unique:users, `password` required|min:8|confirmed
- [ ] `LoginRequest`: `email` required|email, `password` required|string, `remember` boolean
- [ ] Both FormRequests return pt-BR validation messages

**Tests**: feature
**Gate**: `php artisan test --filter=AuthServiceTest`

**Verify**:
```bash
php artisan test --filter=AuthServiceTest  # all green
```

---

### T7: Create Password Services + FormRequests

**What**: `AuthService` extension (forgot password, reset password, change password) + `StoreForgotPasswordRequest` + `StoreNewPasswordRequest` + `UpdatePasswordRequest`
**Where**:
- `app/Services/AuthService.php` (update — add 3 methods)
- `app/Http/Requests/StoreForgotPasswordRequest.php`
- `app/Http/Requests/StoreNewPasswordRequest.php`
- `app/Http/Requests/UpdatePasswordRequest.php`
**Depends on**: T4, T6
**Reuses**: Laravel `Password` facade, `Hash` facade, `Illuminate\Auth\Events\PasswordReset`
**Requirement**: AUTH-06, AUTH-07

**Done when**:
- [ ] `AuthService::sendPasswordResetLink(string $email): string` — uses `Password::sendResetLink()`, always returns `Status::RESET_LINK_SENT` constant to prevent enumeration
- [ ] `AuthService::resetPassword(array $data): void` — uses `Password::reset()`, fires `PasswordReset` event, logs user in
- [ ] `AuthService::changePassword(User $user, string $newPassword): void` — hashes and updates password
- [ ] `StoreForgotPasswordRequest`: `email` required|email
- [ ] `StoreNewPasswordRequest`: `token` required, `email` required|email, `password` required|min:8|confirmed
- [ ] `UpdatePasswordRequest`: `current_password` required|current_password, `password` required|min:8|confirmed
- [ ] All messages in pt-BR

**Tests**: feature
**Gate**: `php artisan test --filter=PasswordServiceTest`

---

### T8: Create WorkspaceService + StoreWorkspaceRequest

**What**: `WorkspaceService` (create, getUserWorkspaces, addMember, removeMember, changeRole, transferAdmin) + `StoreWorkspaceRequest`
**Where**:
- `app/Services/WorkspaceService.php`
- `app/Http/Requests/StoreWorkspaceRequest.php`
**Depends on**: T4, T3
**Reuses**: D-16 (Service classes), `Str::orderedUuid()` for UUID generation
**Requirement**: AUTH-09, AUTH-10, AUTH-11, AUTH-15

**Done when**:
- [ ] `WorkspaceService::create(User $creator, array $data): Workspace` — generates UUID, creates workspace, attaches creator as admin
- [ ] `WorkspaceService::getUserWorkspaces(User $user): Collection` — returns workspaces ordered by `last_visited_at` desc
- [ ] `WorkspaceService::addMember(Workspace $workspace, User $user, WorkspaceRole $role): void` — attaches with role
- [ ] `WorkspaceService::removeMember(Workspace $workspace, User $user): void` — detaches user; throws if last admin
- [ ] `WorkspaceService::changeRole(Workspace $workspace, User $user, WorkspaceRole $role): void` — updates pivot role
- [ ] `WorkspaceService::transferAdmin(Workspace $workspace, User $from, User $to): void` — switches roles
- [ ] `WorkspaceService::setLastVisited(Workspace $workspace, User $user): void` — updates `last_visited_at` timestamp
- [ ] `StoreWorkspaceRequest`: `name` required|string|max:255

**Tests**: feature
**Gate**: `php artisan test --filter=WorkspaceServiceTest`

---

### T9: Create InviteService + Invite & MemberRole FormRequests

**What**: `InviteService` (invite, accept, decline, getPendingInvites) + `StoreInviteRequest` + `UpdateMemberRoleRequest`
**Where**:
- `app/Services/InviteService.php`
- `app/Http/Requests/StoreInviteRequest.php`
- `app/Http/Requests/UpdateMemberRoleRequest.php`
**Depends on**: T4, T3, T8
**Reuses**: `WorkspaceService` (for addMember on accept), D-05 (FormRequests)
**Requirement**: AUTH-13, AUTH-14, AUTH-15

**Done when**:
- [ ] `InviteService::invite(Workspace $workspace, User $inviter, string $email, WorkspaceRole $role): ?Invite` — looks up email; if exists, creates invite + sends notification email; if not exists, returns null (caller shows "Convite enviado")
- [ ] `InviteService::accept(Invite $invite, User $user): void` — validates user matches invite email, adds member via WorkspaceService, updates status to accepted
- [ ] `InviteService::decline(Invite $invite): void` — updates status to declined
- [ ] `InviteService::getPendingInvites(User $user): Collection` — invites where email matches user's email and status is pending
- [ ] `StoreInviteRequest`: `email` required|email, `role` required|in:admin,editor,viewer
- [ ] `UpdateMemberRoleRequest`: `role` required|in:admin,editor,viewer

**Tests**: feature
**Gate**: `php artisan test --filter=InviteServiceTest`

---

### T10: Create WorkspacePolicy + Register

**What**: Create `WorkspacePolicy` with role-based authorization + register in `AppServiceProvider`
**Where**:
- `app/Policies/WorkspacePolicy.php`
- `app/Providers/AppServiceProvider.php` (update)
**Depends on**: T4, T3
**Reuses**: D-07 (Policies per model), D-03 (PascalCase)
**Requirement**: AUTH-15

**Done when**:
- [ ] `view(User $user, Workspace $workspace): bool` — user is member of workspace
- [ ] `create(User $user): bool` — any authenticated+verified user
- [ ] `manageMembers(User $user, Workspace $workspace): bool` — admin only
- [ ] `invite(User $user, Workspace $workspace): bool` — admin only
- [ ] `manageTransactions(User $user, Workspace $workspace): bool` — admin or editor
- [ ] Registered in `AppServiceProvider::boot()`: `Gate::policy(Workspace::class, WorkspacePolicy::class)`

**Tests**: feature
**Gate**: `php artisan test --filter=WorkspacePolicyTest`

**Verify**:
```bash
php artisan tinker --execute="Gate::allows('manageMembers', App\Models\Workspace::first())"  # true if admin
```

---

### T11: Create EnsureHasWorkspace Middleware + Register

**What**: Create `EnsureHasWorkspace` middleware that redirects users with zero workspaces to `/workspace/create`; register in `bootstrap/app.php`
**Where**:
- `app/Http/Middleware/EnsureHasWorkspace.php`
- `bootstrap/app.php` (update)
**Depends on**: T10
**Reuses**: Existing middleware registration pattern in `bootstrap/app.php`
**Requirement**: AUTH-09

**Done when**:
- [ ] Middleware checks `$request->user()->workspaces()->count() === 0`
- [ ] If zero workspaces, redirects to `route('workspace.create')` with status 302
- [ ] If request is for `workspace.create`, `workspace.store`, `workspace.select`, `workspace.activate` or `logout` → passes through (no redirect loop)
- [ ] Registered as `ensure.has.workspace` alias in `bootstrap/app.php` via `$middleware->alias()`

**Tests**: feature
**Gate**: `php artisan test --filter=EnsureHasWorkspaceTest`

---

### T12: Create UserResource + WorkspaceResource

**What**: Create `UserResource` and `WorkspaceResource` ApiResources
**Where**:
- `app/Http/Resources/UserResource.php`
- `app/Http/Resources/WorkspaceResource.php`
**Depends on**: T4
**Reuses**: D-01 (ApiResource mandatory), D-04 (UUIDs in responses)
**Requirement**: AUTH-01, AUTH-10, AUTH-12

**Done when**:
- [ ] `UserResource`: returns `uuid`, `name`, `email`, `avatar`; includes conditional `workspace_role` when loaded via pivot
- [ ] `WorkspaceResource`: returns `uuid`, `name`, `description`, `members_count` (when loaded), `role` (pivot when loaded)
- [ ] Both extend `JsonResource`, implement `toArray()`, no auto-increment IDs exposed

**Tests**: feature
**Gate**: `php artisan test --filter=ResourceTest`

---

### T13: Create InviteResource + MemberResource

**What**: Create `InviteResource` and `MemberResource` ApiResources
**Where**:
- `app/Http/Resources/InviteResource.php`
- `app/Http/Resources/MemberResource.php`
**Depends on**: T4, T12
**Reuses**: `UserResource`, D-01 (ApiResource mandatory)
**Requirement**: AUTH-13, AUTH-14, AUTH-15

**Done when**:
- [ ] `InviteResource`: returns `uuid`, `email`, `role`, `status`, `inviter` (UserResource when loaded), `workspace` (WorkspaceResource when loaded)
- [ ] `MemberResource`: returns `user` (UserResource), `role`, `joined_at`

**Tests**: feature
**Gate**: `php artisan test --filter=ResourceTest`

---

### T14: Create Auth Controllers + Register & Login Routes

**What**: `RegisteredUserController`, `AuthenticatedSessionController`, `EmailVerificationPromptController`, `VerifyEmailController`, `EmailVerificationNotificationController` + routes
**Where**:
- `app/Http/Controllers/Auth/RegisteredUserController.php`
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- `app/Http/Controllers/Auth/EmailVerificationPromptController.php`
- `app/Http/Controllers/Auth/VerifyEmailController.php`
- `app/Http/Controllers/Auth/EmailVerificationNotificationController.php`
- `routes/web.php` (update)
**Depends on**: T6, T11, T12
**Reuses**: D-06 (Resource controllers), D-05 (FormRequest injection), D-08 (Inertia responses), D-20 (pt-BR messages)
**Requirement**: AUTH-01, AUTH-02, AUTH-03, AUTH-04, AUTH-05

**Done when**:
- [ ] `RegisteredUserController::create()` — returns `inertia('Auth/Register')`
- [ ] `RegisteredUserController::store(StoreRegisteredUserRequest)` — calls `AuthService::register()`, sends email verification, flashes `status`, redirects to `verification.notice`
- [ ] `AuthenticatedSessionController::create()` — returns `inertia('Auth/Login')` with status/error props
- [ ] `AuthenticatedSessionController::store(LoginRequest)` — calls `AuthService::authenticate()`, regenerates session, redirects to workspace route
- [ ] `AuthenticatedSessionController::destroy()` — `Auth::logout()`, invalidates session, redirects to `/`
- [ ] `EmailVerificationPromptController` — returns `inertia('Auth/VerifyEmail')` with status
- [ ] `VerifyEmailController` — uses `EmailVerificationRequest`, calls `AuthService::verifyEmail()`, redirects to workspace route
- [ ] `EmailVerificationNotificationController` — calls `AuthService::resendVerificationEmail()`, flashes status, redirects back
- [ ] Routes registered in `web.php`: guest routes (register, login) + auth routes (logout, verify email) + guest middleware on register/login/forgot/reset routes
- [ ] All user-facing messages in pt-BR

**Tests**: feature
**Gate**: `php artisan test --filter=AuthenticationTest`

---

### T15: Create Password Controllers + Routes

**What**: `PasswordResetLinkController`, `NewPasswordController`, `PasswordController` + routes
**Where**:
- `app/Http/Controllers/Auth/PasswordResetLinkController.php`
- `app/Http/Controllers/Auth/NewPasswordController.php`
- `app/Http/Controllers/Auth/PasswordController.php`
- `routes/web.php` (update)
**Depends on**: T7, T14
**Reuses**: D-05, D-08
**Requirement**: AUTH-06, AUTH-07

**Done when**:
- [ ] `PasswordResetLinkController::create()` — returns `inertia('Auth/ForgotPassword')` with status
- [ ] `PasswordResetLinkController::store(StoreForgotPasswordRequest)` — calls `AuthService::sendPasswordResetLink()`, always flashes success (prevents enumeration)
- [ ] `NewPasswordController::create(Request $request)` — returns `inertia('Auth/ResetPassword')` with token and email
- [ ] `NewPasswordController::store(StoreNewPasswordRequest)` — calls `AuthService::resetPassword()`, redirects to login with status
- [ ] `PasswordController::edit()` — returns `inertia('Settings/Password')` (placeholder — under AuthenticatedLayout)
- [ ] `PasswordController::update(UpdatePasswordRequest)` — calls `AuthService::changePassword()`, flashes success, redirects back
- [ ] Routes registered: forgot-password, reset-password (guest), password.edit, password.update (auth)

**Tests**: feature
**Gate**: `php artisan test --filter=PasswordTest`

---

### T16: Create SocialiteController + Google OAuth Route

**What**: `SocialiteController` with redirect and callback + Google OAuth routes
**Where**:
- `app/Http/Controllers/Auth/SocialiteController.php`
- `routes/web.php` (update)
- `config/services.php` (update — add Google OAuth config)
**Depends on**: T1, T6, T14
**Reuses**: `Socialite::driver('google')`, `AuthService`
**Requirement**: AUTH-08

**Done when**:
- [ ] `redirect()` — returns `Socialite::driver('google')->redirect()`
- [ ] `callback()` — gets Google user, calls `AuthService::handleGoogleCallback()`, authenticates, redirects to workspace route
- [ ] `handleGoogleCallback()` in `AuthService`: finds or creates user by google_id or email; if new, marks email_verified_at; if existing (email match), links google_id; returns User
- [ ] `config/services.php`: `google` array with `client_id`, `client_secret`, `redirect` from env vars (`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`)
- [ ] Failure handling: catches any exception, redirects to login with pt-BR error message
- [ ] Routes: `GET auth/google/redirect` (name: `google.redirect`), `GET auth/google/callback` (name: `google.callback`)

**Tests**: feature (mock Socialite)
**Gate**: `php artisan test --filter=GoogleOAuthTest`

---

### T17: Create WorkspaceController + Routes

**What**: `WorkspaceController` (create, store, select, activate) + routes
**Where**:
- `app/Http/Controllers/WorkspaceController.php`
- `routes/web.php` (update)
**Depends on**: T8, T11, T12, T14
**Reuses**: `WorkspaceService`, `StoreWorkspaceRequest`, `WorkspaceResource`
**Requirement**: AUTH-09, AUTH-10, AUTH-11, AUTH-12

**Done when**:
- [ ] `create()` — returns `inertia('Workspace/Create')`
- [ ] `store(StoreWorkspaceRequest)` — calls `WorkspaceService::create()`, sets `last_visited_at`, redirects to `dashboard` route for the new workspace
- [ ] `select()` — calls `WorkspaceService::getUserWorkspaces()`, returns `inertia('Workspace/Select')` with workspaces prop (via WorkspaceResource collection)
- [ ] `activate(Request)` — receives workspace UUID, calls `WorkspaceService::setLastVisited()`, redirects to `dashboard`
- [ ] Routes registered under auth+verified middleware, exempt from `ensure.has.workspace`

**Tests**: feature
**Gate**: `php artisan test --filter=WorkspaceCreationTest`

---

### T18: Create InviteController + WorkspaceMemberController + Routes

**What**: `InviteController` (store, accept, decline) + `WorkspaceMemberController` (index, destroy, updateRole) + routes
**Where**:
- `app/Http/Controllers/InviteController.php`
- `app/Http/Controllers/WorkspaceMemberController.php`
- `routes/web.php` (update)
**Depends on**: T9, T10, T13, T17
**Reuses**: `InviteService`, `WorkspaceService`, `WorkspacePolicy`, `StoreInviteRequest`, `UpdateMemberRoleRequest`, `InviteResource`, `MemberResource`
**Requirement**: AUTH-13, AUTH-14, AUTH-15

**Done when**:
- [ ] `InviteController::store(StoreInviteRequest, Workspace $workspace)` — `Gate::authorize('invite', $workspace)`, calls `InviteService::invite()`, flashes appropriate message
- [ ] `InviteController::accept(Invite $invite)` — validates current user's email matches invite, calls `InviteService::accept()`, redirects to dashboard
- [ ] `InviteController::decline(Invite $invite)` — validates current user's email matches invite, calls `InviteService::decline()`, redirects back
- [ ] `WorkspaceMemberController::index(Workspace $workspace)` — `Gate::authorize('viewMembers', $workspace)`, returns `inertia('Workspace/Members')` with members and invites props
- [ ] `WorkspaceMemberController::destroy(Workspace $workspace, User $user)` — `Gate::authorize('manageMembers', $workspace)`, calls `WorkspaceService::removeMember()`, redirects back
- [ ] `WorkspaceMemberController::updateRole(UpdateMemberRoleRequest, Workspace $workspace, User $user)` — `Gate::authorize('manageMembers', $workspace)`, calls `WorkspaceService::changeRole()`, redirects back
- [ ] Routes under prefix `/w/{workspace}` with full middleware stack

**Tests**: feature
**Gate**: `php artisan test --filter=InviteTest && php artisan test --filter=MemberRoleTest`

---

### T19: Update HandleInertiaRequests (Shared Props)

**What**: Add `auth.user` (UserResource) and `workspaces` (WorkspaceResource collection) to Inertia shared props; add `EnsureHasWorkspace` to web middleware
**Where**:
- `app/Http/Middleware/HandleInertiaRequests.php`
- `bootstrap/app.php` (update — middleware stack)
**Depends on**: T11, T12, T18
**Reuses**: D-15 (shared data minimum), D-08 (Inertia pure)
**Requirement**: AUTH-01, AUTH-12

**Done when**:
- [ ] `share()` method returns `auth.user` as `UserResource` (or null if guest)
- [ ] `share()` method returns `workspaces` as `WorkspaceResource` collection (or empty array)
- [ ] `EnsureHasWorkspace` middleware added to web middleware group (after auth and verified)
- [ ] Existing `Home` page can access `auth.user` via `usePage().props`
- [ ] TypeScript types generated/updated for shared props (`inertia.d.ts` or `global.d.ts`)

**Tests**: feature
**Gate**: `php artisan test --filter=SharedPropsTest`

---

### T20: Create GuestLayout

**What**: Create `GuestLayout` component for auth pages (centered card, no sidebar/header)
**Where**: `resources/js/Layouts/GuestLayout.tsx`
**Depends on**: T19
**Reuses**: `ui/card` for the centered container, existing Tailwind theme variables
**Requirement**: AUTH-01, AUTH-02, AUTH-06, AUTH-08 (all auth page stories)

**Done when**:
- [ ] Renders children centered vertically and horizontally (min-h-screen, flex, items-center, justify-center)
- [ ] Uses Tailwind background from theme variables (maintains dark mode support)
- [ ] Optional `status` prop for flash messages (renders green callout when set)
- [ ] Responsive: max-width card that adapts to mobile
- [ ] No sidebar, no header — minimal layout

**Tests**: none (visual component)
**Gate**: `npm run build`

---

### T21: Create Login Page [P]

**What**: `Pages/Auth/Login.tsx` — login form with email, password, remember me, forgot password link, Google OAuth button, register link
**Where**: `resources/js/Pages/Auth/Login.tsx`
**Depends on**: T20
**Reuses**: `GuestLayout`, `useForm()` from Inertia, `ui/button`, `ui/input`, `ui/label`, `ui/card`, Ziggy `route()`
**Requirement**: AUTH-02, AUTH-04, AUTH-08

**Done when**:
- [ ] Form: email input, password input, "Lembrar de mim" checkbox, submit button "Entrar"
- [ ] Link "Esqueci sua senha?" below password field → routes to `password.request`
- [ ] Button "Entrar com Google" → routes to `google.redirect`
- [ ] Link "Não tem uma conta? Cadastre-se" → routes to `register`
- [ ] Uses `useForm()` for form state and Inertia validation errors
- [ ] `status` prop from controller shows success messages (e.g., after password reset)
- [ ] Form handles processing state (disable button, show spinner)
- [ ] All text in pt-BR

**Tests**: e2e
**Gate**: `npx cypress run --spec="cypress/e2e/auth/login.cy.ts"`

---

### T22: Create Register Page [P]

**What**: `Pages/Auth/Register.tsx` — registration form with name, email, password, password confirmation
**Where**: `resources/js/Pages/Auth/Register.tsx`
**Depends on**: T20
**Reuses**: `GuestLayout`, `useForm()`, `ui/button`, `ui/input`, `ui/label`, `ui/card`, `route()`
**Requirement**: AUTH-01

**Done when**:
- [ ] Form: name input, email input, password input, password confirmation input, submit "Criar conta"
- [ ] Link "Já possui uma conta? Entrar" → routes to `login`
- [ ] Uses `useForm()` for form state and Inertia validation errors
- [ ] Password confirmation validation (must match)
- [ ] Success redirect handled by controller (to verification.notice)
- [ ] All text in pt-BR

**Tests**: e2e
**Gate**: `npx cypress run --spec="cypress/e2e/auth/register.cy.ts"`

---

### T23: Create ForgotPassword Page [P]

**What**: `Pages/Auth/ForgotPassword.tsx` — forgot password form with email input
**Where**: `resources/js/Pages/Auth/ForgotPassword.tsx`
**Depends on**: T20
**Reuses**: `GuestLayout`, `useForm()`, `ui/button`, `ui/input`, `ui/label`, `ui/card`, `route()`
**Requirement**: AUTH-06

**Done when**:
- [ ] Form: email input, submit "Enviar link de recuperação"
- [ ] Link "Voltar para o login" → routes to `login`
- [ ] `status` prop from controller shows "Se o email existir, um link de recuperação foi enviado"
- [ ] Uses `useForm()` for form state and Inertia validation errors
- [ ] All text in pt-BR

**Tests**: none (covered by password E2E)
**Gate**: `npm run build`

---

### T24: Create ResetPassword Page [P]

**What**: `Pages/Auth/ResetPassword.tsx` — reset password form with email, password, password confirmation
**Where**: `resources/js/Pages/Auth/ResetPassword.tsx`
**Depends on**: T20
**Reuses**: `GuestLayout`, `useForm()`, `ui/button`, `ui/input`, `ui/label`, `ui/card`, `route()`
**Requirement**: AUTH-06

**Done when**:
- [ ] Form: email (hidden or disabled, from props), new password, confirm password, submit "Redefinir senha"
- [ ] Receives `token` and `email` as props from controller
- [ ] Uses `useForm()` for form state and Inertia validation errors
- [ ] Password confirmation validation
- [ ] All text in pt-BR

**Tests**: none (covered by password E2E)
**Gate**: `npm run build`

---

### T25: Create VerifyEmail Page [P]

**What**: `Pages/Auth/VerifyEmail.tsx` — email verification notice with resend button
**Where**: `resources/js/Pages/Auth/VerifyEmail.tsx`
**Depends on**: T20
**Reuses**: `GuestLayout`, `useForm()`, `ui/button`, `ui/card`, `route()`
**Requirement**: AUTH-05

**Done when**:
- [ ] Shows message "Verifique seu endereço de email. Um link de verificação foi enviado para seu email."
- [ ] "Reenviar email de verificação" button → POST to `verification.send`
- [ ] `status` prop shows "Email de verificação reenviado" after successful resend
- [ ] Logout button → POST to `logout`
- [ ] Uses `useForm()` for resend action
- [ ] All text in pt-BR

**Tests**: none (covered by auth E2E)
**Gate**: `npm run build`

---

### T26: Create Workspace/Create Page [P]

**What**: `Pages/Workspace/Create.tsx` — first workspace creation form
**Where**: `resources/js/Pages/Workspace/Create.tsx`
**Depends on**: T20, T19
**Reuses**: `AuthenticatedLayout`, `useForm()`, `ui/button`, `ui/input`, `ui/label`, `ui/card`, `route()`
**Requirement**: AUTH-09, AUTH-10

**Done when**:
- [ ] Wrapped in `AuthenticatedLayout` (sidebar shows but no workspace context)
- [ ] Card with heading "Criar seu workspace"
- [ ] Form: name input (required), description textarea (optional), submit "Criar workspace"
- [ ] Uses `useForm()` for form state
- [ ] Redirect handled by controller (to dashboard)
- [ ] All text in pt-BR

**Tests**: none (covered by E2E)
**Gate**: `npm run build`

---

### T27: Create Workspace/Select Page [P]

**What**: `Pages/Workspace/Select.tsx` — workspace selector grid
**Where**: `resources/js/Pages/Workspace/Select.tsx`
**Depends on**: T19, T26
**Reuses**: `AuthenticatedLayout`, `WorkspaceCard`, `ui/button`, `route()`
**Requirement**: AUTH-12

**Done when**:
- [ ] Receives `workspaces` as prop (WorkspaceResource array from shared data)
- [ ] Grid of `WorkspaceCard` components, each clickable → POST to `workspace.activate`
- [ ] "Criar novo workspace" card/button → routes to `workspace.create`
- [ ] Empty state if no workspaces (shouldn't happen due to middleware, but handled)
- [ ] All text in pt-BR

**Tests**: none (covered by E2E)
**Gate**: `npm run build`

---

### T28: Create New Frontend Components [P]

**What**: `WorkspaceCard`, `InviteDialog`, `PendingInvitesList`, `MemberRow`, `RoleBadge`, `RoleSelect`
**Where**:
- `resources/js/Components/WorkspaceCard.tsx`
- `resources/js/Components/InviteDialog.tsx`
- `resources/js/Components/PendingInvitesList.tsx`
- `resources/js/Components/MemberRow.tsx`
- `resources/js/Components/RoleBadge.tsx`
- `resources/js/Components/RoleSelect.tsx`
**Depends on**: T19
**Reuses**: `ui/card`, `ui/button`, `ui/dropdown-menu`, `ui/dialog` (if installed), `ui/avatar`, `ui/badge`/`ui/label`, `route()`
**Requirement**: AUTH-12, AUTH-13, AUTH-14, AUTH-15

**Done when**:
- [ ] `WorkspaceCard`: displays workspace name, description, member count; clickable; hover effect
- [ ] `InviteDialog`: dialog/modal with email input, role dropdown (`RoleSelect`), submit button; triggers `useForm().post(route('workspace.invites.store', { workspace }))`
- [ ] `PendingInvitesList`: lists pending invites with accept/decline buttons; uses `useForm()` for actions
- [ ] `MemberRow`: displays user avatar + name, role badge, actions menu (change role, remove — admin only)
- [ ] `RoleBadge`: colored badge: Admin (purple/violet), Editor (blue), Viewer (gray/slate); text in pt-BR
- [ ] `RoleSelect`: dropdown with role options; used in InviteDialog and MemberRow, controlled component with value + onChange
- [ ] All components use TypeScript strict mode with proper prop types

**Tests**: none (visual components, covered by E2E)
**Gate**: `npm run build`

---

### T29: Update UserMenu Component [P]

**What**: Replace hardcoded "Gustavo / gustavo@email.com" with `auth.user` shared props
**Where**: `resources/js/Components/UserMenu.tsx`
**Depends on**: T19
**Reuses**: Existing `UserMenu` structure, `usePage()`
**Requirement**: AUTH-03

**Done when**:
- [ ] Uses `usePage().props.auth.user` for name and email (not hardcoded)
- [ ] Avatar shows user initials (first letter of name) or Google avatar URL if available
- [ ] Logout links to `route('logout')` using `useForm().post()`
- [ ] Handles null/undefined user (guest state — shouldn't render, but type-safe)
- [ ] "Perfil" and "Configurações" links kept (route to placeholder or `#` for now)

**Tests**: none (covered by E2E)
**Gate**: `npm run build`

---

### T30: Update AppSidebar Component [P]

**What**: Update sidebar workspace name from hardcoded "Workspace Pessoal" to current workspace prop
**Where**: `resources/js/Components/AppSidebar.tsx`
**Depends on**: T19
**Reuses**: Existing sidebar structure
**Requirement**: AUTH-12

**Done when**:
- [ ] Workspace name from page props or shared data (not hardcoded)
- [ ] Workspace switcher button/area links to `workspace.select`
- [ ] Navigation links all work with current workspace context via Ziggy route params
- [ ] Handles no workspace context (e.g., workspace create/select pages) — show "Sem workspace" or hide workspace-specific items

**Tests**: none (covered by E2E)
**Gate**: `npm run build`

---

### T31: Create E2E Tests (Cypress)

**What**: Write Cypress E2E tests for critical user journeys
**Where**:
- `cypress/e2e/auth/register.cy.ts`
- `cypress/e2e/auth/login.cy.ts`
- `cypress/e2e/auth/google-oauth.cy.ts`
- `cypress/e2e/workspace/invite.cy.ts`
**Depends on**: T29, T30
**Reuses**: D-21 (Cypress for E2E), Mailpit for email verification
**Requirement**: All AUTH requirements (integration validation)

**Done when**:
- [ ] `register.cy.ts`: Visit `/register` → fill form → submit → assert redirect to verify email → open Mailpit → click link → assert redirect to workspace create → create workspace → assert dashboard loads
- [ ] `login.cy.ts`: Visit `/login` → fill valid credentials → submit → assert redirect to workspace → logout → login with invalid credentials → assert error → login with "remember me" → close/reopen → assert still logged in
- [ ] `google-oauth.cy.ts`: Mock Google OAuth flow → click "Entrar com Google" → assert authenticated → assert workspace redirect
- [ ] `invite.cy.ts`: Admin creates workspace → invites user via email → invited user logs in → sees pending invite → accepts → both users see same workspace → editor tries member management → assert 403

**Tests**: e2e
**Gate**: `npx cypress run`

---

### T32: Full Gate Check + Typing Fixes

**What**: Run full test suite, fix any issues, ensure TypeScript compiles, ensure no regressions
**Where**: All files
**Depends on**: T31
**Reuses**: N/A
**Requirement**: All AUTH requirements (final verification)

**Done when**:
- [ ] `php artisan test` — all PHPUnit tests pass (estimated: ~35 tests)
- [ ] `npx cypress run` — all E2E tests pass
- [ ] `npm run build` — frontend builds without errors
- [ ] `php artisan migrate:fresh --seed` — migrations run clean
- [ ] `composer validate` — no dependency issues
- [ ] No hardcoded values in components (all from props/shared data)
- [ ] No auto-increment IDs in frontend or API responses (UUIDs only)
- [ ] All user-facing text in pt-BR

**Tests**: all
**Gate**: `php artisan test && npx cypress run && npm run build`

---

## Parallel Execution Map

```
Phase 1 (Sequential):
  T1 ──→ T2 ──→ T3 ──→ T4 ──→ T5

Phase 2 (Parallel):
  T5 complete, then:
    ├── T6 (AuthService) [P]
    ├── T7 (Password services) [P]
    ├── T8 (WorkspaceService) [P]
    └── T9 (InviteService) [P]

Phase 3 (Sequential):
  T6,T7,T8,T9 complete, then:
    T10 ──→ T11

Phase 4 (Parallel):
  T5 complete, then:
    ├── T12 (UserResource + WorkspaceResource) [P]
    └── T13 (InviteResource + MemberResource) [P]

Phase 5 (Sequential):
  T6,T7 complete, then:
    T14 ──→ T15
       └── T16 (Google OAuth, runs parallel with T15) [P]

Phase 6 (Sequential):
  T14 complete, then:
    T17 ──→ T18

Phase 7 (Parallel):
  T13 complete, then:
    ├── T19 (HandleInertiaRequests) [P]
    └── T20 (GuestLayout) [P]

Phase 8 (Parallel):
  T19,T20 complete, then:
    ├── T21 (Login) ────┐
    ├── T22 (Register) ─┤
    ├── T23 (ForgotPW) ─┤
    ├── T24 (ResetPW) ──┤ ALL [P]
    ├── T25 (Verify) ───┤
    ├── T26 (WS Create) ┤
    └── T27 (WS Select) ┘

Phase 9 (Parallel + Sequential):
  T27 complete, then:
    ├── T28 (New components) [P]
    ├── T29 (Update UserMenu) [P]
    ├── T30 (Update AppSidebar) [P]
    └── T29,T30 complete ──→ T31 ──→ T32
```

---

## Requirement Traceability

| Task | Requirement IDs |
|------|----------------|
| T1   | AUTH-08 |
| T2   | AUTH-01, AUTH-10, AUTH-13 |
| T3   | AUTH-13, AUTH-15 |
| T4   | AUTH-01, AUTH-10, AUTH-11, AUTH-13 |
| T5   | AUTH-01, AUTH-10, AUTH-13 |
| T6   | AUTH-01, AUTH-02, AUTH-04, AUTH-05 |
| T7   | AUTH-06, AUTH-07 |
| T8   | AUTH-09, AUTH-10, AUTH-11, AUTH-15 |
| T9   | AUTH-13, AUTH-14, AUTH-15 |
| T10  | AUTH-15 |
| T11  | AUTH-09 |
| T12  | AUTH-01, AUTH-10, AUTH-12 |
| T13  | AUTH-13, AUTH-14, AUTH-15 |
| T14  | AUTH-01, AUTH-02, AUTH-03, AUTH-04, AUTH-05 |
| T15  | AUTH-06, AUTH-07 |
| T16  | AUTH-08 |
| T17  | AUTH-09, AUTH-10, AUTH-11, AUTH-12 |
| T18  | AUTH-13, AUTH-14, AUTH-15 |
| T19  | AUTH-01, AUTH-12 |
| T20  | AUTH-01, AUTH-02, AUTH-06, AUTH-08 |
| T21  | AUTH-02, AUTH-04, AUTH-08 |
| T22  | AUTH-01 |
| T23  | AUTH-06 |
| T24  | AUTH-06 |
| T25  | AUTH-05 |
| T26  | AUTH-09, AUTH-10 |
| T27  | AUTH-12 |
| T28  | AUTH-12, AUTH-13, AUTH-14, AUTH-15 |
| T29  | AUTH-03 |
| T30  | AUTH-12 |
| T31  | All AUTH requirements |
| T32  | All AUTH requirements |

---

## Validation

### Granularity Check

| Task | Scope | Status |
|------|-------|--------|
| T1 | 1 package install | ✅ Granular |
| T2 | 3 related migrations (same domain) | ⚠️ OK (cohesive — all DB schema for auth) |
| T3 | 2 enums (same domain) | ⚠️ OK (cohesive — both enum types) |
| T4 | 1 model update + 2 new models | ❌ Too many — but cohesive (User updated, Workspace, Invite all need each other's relationships) |
| T5 | 3 factory files | ⚠️ OK (cohesive — all factories for TDD) |
| T6 | 1 service extension + 2 FormRequests | ⚠️ OK (cohesive — auth registration+login bundle) |
| T7 | 1 service extension + 3 FormRequests | ⚠️ OK (cohesive — password management bundle) |
| T8 | 1 service + 1 FormRequest | ✅ Granular |
| T9 | 1 service + 2 FormRequests | ⚠️ OK (cohesive) |
| T10 | 1 policy + registration | ✅ Granular |
| T11 | 1 middleware + registration | ✅ Granular |
| T12 | 2 Resources | ⚠️ OK (cohesive) |
| T13 | 2 Resources | ⚠️ OK (cohesive) |
| T14 | 5 controllers + routes | ❌ Too many — restructured |
| T15 | 3 controllers + routes | ⚠️ OK (cohesive — all password flow) |
| T16 | 1 controller + route + config | ✅ Granular |
| T17 | 1 controller + routes | ✅ Granular |
| T18 | 2 controllers + routes | ⚠️ OK (cohesive — member+invite management) |
| T19 | 1 file update + middleware stack | ✅ Granular |
| T20 | 1 layout component | ✅ Granular |
| T21-T27 | 1 page each | ✅ Granular |
| T28 | 6 components | ❌ Too many — but cohesive (all new UI components) |
| T29 | 1 component update | ✅ Granular |
| T30 | 1 component update | ✅ Granular |
| T31 | 4 Cypress specs | ⚠️ OK (cohesive — all E2E tests) |
| T32 | 1 integration task | ✅ Granular |

### Diagram-Definition Cross-Check

| Task | Depends On (body) | Diagram Shows | Status |
|------|-------------------|---------------|--------|
| T1 | None | Start of Phase 1 | ✅ |
| T2 | T1 | T1 → T2 | ✅ |
| T3 | T2 | T2 → T3 | ✅ |
| T4 | T2,T3 | T3 → T4 | ✅ |
| T5 | T4 | T4 → T5 | ✅ |
| T6 | T4 | T5 → T6 [P] | ✅ (after T4, Phase 2 starts at T5) |
| T7 | T4,T6 | T5 → T7 [P] | ✅ |
| T8 | T4,T3 | T5 → T8 [P] | ✅ |
| T9 | T4,T3,T8 | T5 → T9 [P] | ✅ |
| T10 | T4,T3 | Phase 3 after Phase 2 | ✅ |
| T11 | T10 | T10 → T11 | ✅ |
| T12 | T4 | T5 → T12 [P] | ✅ |
| T13 | T4,T12 | T5 → T13 [P] | ✅ |
| T14 | T6,T11,T12 | Phase 5 after Phase 3+4 | ✅ |
| T15 | T7,T14 | T14 → T15 | ✅ |
| T16 | T1,T6,T14 | T14 + T15 → parallel | ✅ |
| T17 | T8,T11,T12,T14 | T14 → T17 | ✅ |
| T18 | T9,T10,T13,T17 | T17 → T18 | ✅ |
| T19 | T11,T12,T18 | T13 + T18 → T19 [P] | ✅ |
| T20 | T19 | T19 → T20 [P] | ✅ |
| T21-T25 | T20 | T20 → all [P] | ✅ |
| T26-T27 | T19,T20 | T20 → all [P] | ✅ |
| T28 | T19 | Phase 9 after T27 | ✅ |
| T29-T30 | T19 | Phase 9 after T27 [P] | ✅ |
| T31 | T29,T30 | T29,T30 → T31 | ✅ |
| T32 | T31 | T31 → T32 | ✅ |

All dependencies match between task bodies and execution diagram.

### Test Co-location Validation

| Task | Code Layer Created | Tests | Status |
|------|-------------------|-------|--------|
| T1 | composer.json (package) | none | ✅ |
| T2 | Migrations | feature | ✅ |
| T3 | Enums | feature | ✅ |
| T4 | Models | feature | ✅ |
| T5 | Factories | feature | ✅ |
| T6 | Service + 2 FormRequests | feature | ✅ |
| T7 | Service extension + 3 FormRequests | feature | ✅ |
| T8 | Service + FormRequest | feature | ✅ |
| T9 | Service + 2 FormRequests | feature | ✅ |
| T10 | Policy + provider | feature | ✅ |
| T11 | Middleware + config | feature | ✅ |
| T12 | 2 Resources | feature | ✅ |
| T13 | 2 Resources | feature | ✅ |
| T14 | 5 Controllers + routes | feature | ✅ |
| T15 | 3 Controllers + routes | feature | ✅ |
| T16 | Controller + route + config | feature | ✅ |
| T17 | Controller + routes | feature | ✅ |
| T18 | 2 Controllers + routes | feature | ✅ |
| T19 | Middleware + provider update | feature | ✅ |
| T20 | Layout (visual) | none | ✅ (visual, no logic) |
| T21-T27 | Pages (visual) | none/e2e | ✅ (pages inherit logic from controllers; E2E covers integration) |
| T28 | Components (visual) | none | ✅ (visual, covered by E2E) |
| T29-T30 | Component updates | none | ✅ |
| T31 | E2E specs | e2e | ✅ |
| T32 | Integration/cleanup | all | ✅ |

No violations — all backend logic tasks are co-located with feature tests. Frontend pages/components are visual-only (logic is in controllers) and covered by E2E tests.
