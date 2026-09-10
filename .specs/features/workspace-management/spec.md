# Workspace Management — Specification

**Parent Spec:** `.specs/features/workspace-financeiro/spec.md`
**Phase:** P1 — MVP Core

## Problem Statement

Workspace admins need a centralized screen to manage members, roles, and pending invites. Currently, the `WorkspaceMemberController` returns raw arrays (violating the ApiResource convention), there's no settings page, and the sidebar doesn't show the user's role in each workspace. Additionally, the `WorkspaceSwitcher` component doesn't exist, making it hard to navigate between workspaces. The invite system works but doesn't send email notifications to invitees.

## Goals

- [ ] Admins can access a settings page that shows members, invites, and role management
- [ ] Settings page uses ApiResources (MemberResource, InviteResource) — no raw arrays
- [ ] WorkspaceSwitcher component allows quick navigation between workspaces
- [ ] Sidebar shows user's role in each workspace
- [ ] Invite notification emails are sent to invitees
- [ ] All GET routes have smoke tests (one per route)
- [ ] All ApiResource responses are tested for shape (keys + types)

## Key Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Settings access | Admin-only for mutations; editor can view but not modify | Principle of least privilege |
| ApiResource mandatory | Refactor `WorkspaceMemberController` to use Resources | Project convention (D-01) |
| Email notification | Laravel Notification via Event/Listener | Standard Laravel pattern |
| WorkspaceSwitcher | Reads from `usePage()` shared data | Inertia pure (D-08) |
| Smoke tests | One per GET route (PHPUnit + Cypress) | Catch white-screen regressions |

## Out of Scope

| Feature | Reason |
|---------|--------|
| Edit workspace name/description | Separate feature |
| Delete workspace | Requires complex confirmation flow |
| Transfer ownership | `transferAdminRole()` exists but not exposed yet |
| Dark mode | Light mode only (per design system) |

---

## User Stories

### P1: Tela de Configurações do Workspace ⭐ MVP

**User Story**: As a workspace admin, I want to access a settings page that shows all members and pending invites so that I can manage my workspace.

**Why P1**: Without a settings page, admins have no way to manage members or see pending invites.

**Acceptance Criteria**:

1. WHEN an admin navigates to `/w/{workspace}/settings` THEN system SHALL return HTTP 200 and render the `Workspace/Settings` Inertia page.

2. WHEN the settings page loads THEN system SHALL return `members` as a `MemberResource` collection containing `user.uuid`, `user.name`, `user.email`, `role`, and `joined_at` for each member.

3. WHEN the settings page loads THEN system SHALL return `invites` as an `InviteResource` collection containing `uuid`, `email`, `role`, `status`, and `inviter` (with `uuid` and `name`) for each pending invite.

4. WHEN the settings page loads THEN system SHALL return `isAdmin` boolean indicating whether the current user is an admin of the workspace.

5. WHEN a viewer (role=viewer) accesses the settings page THEN system SHALL return HTTP 403 Forbidden.

6. WHEN a non-member accesses the settings page THEN system SHALL return HTTP 403 Forbidden.

**Independent Test**: Admin creates workspace → navigates to settings → sees members list with correct structure → sees pending invites with correct structure → sees `isAdmin=true`.

---

### P1: WorkspaceSwitcher Component ⭐ MVP

**User Story**: As a user with multiple workspaces, I want to switch between workspaces from the sidebar so that I can access different workspaces quickly.

**Why P1**: Users with multiple workspaces (personal + shared) need fast navigation.

**Acceptance Criteria**:

1. WHEN the sidebar renders THEN system SHALL display a `WorkspaceSwitcher` showing the current workspace name with a dropdown indicator.

2. WHEN the user clicks the WorkspaceSwitcher THEN system SHALL open a dropdown listing all their workspaces with name and role badge.

3. WHEN the user clicks a different workspace in the dropdown THEN system SHALL call `POST /workspace/activate` and reload the page to the new workspace.

4. WHEN the sidebar renders THEN system SHALL show a gear icon next to the workspace name that navigates to `workspace.settings`.

5. WHEN the user has only one workspace THEN the WorkspaceSwitcher SHALL still render but the dropdown shows only that workspace.

**Independent Test**: User has 2 workspaces → sidebar shows switcher with current workspace → clicks switcher → sees both workspaces with roles → clicks other workspace → page reloads in new workspace context.

---

### P1: Shared Workspaces com Role ⭐ MVP

**User Story**: As a user, I want to see my role in each workspace listed in the sidebar so that I know what permissions I have.

**Why P1**: Role visibility is essential for users to understand their access level across workspaces.

**Acceptance Criteria**:

1. WHEN `HandleInertiaRequests` shares the `workspaces` array THEN each workspace SHALL include a `role` field (admin/editor/viewer) from the pivot table.

2. WHEN the shared workspaces are returned THEN the `role` field SHALL be loaded via `withPivot('role')` on the relationship.

**Independent Test**: User is admin in workspace A and editor in workspace B → sidebar dropdown shows "Workspace A (Admin)" and "Workspace B (Editor)".

---

### P1: Notificação de Convite por Email ⭐ MVP

**User Story**: As a workspace admin, I want invitees to receive an email notification when I invite them so that they know about the invitation.

**Why P1**: Without email notification, invitees have no way to know they've been invited unless they manually check the app.

**Acceptance Criteria**:

1. WHEN an admin creates an invite for a registered user THEN system SHALL dispatch an `InviteCreated` event that triggers a `NewInvite` notification email.

2. WHEN the invite notification is sent THEN the email SHALL contain: workspace name, inviter name, proposed role, and an "Accept Invite" button with the correct URL.

3. WHEN an invite is created for a non-registered email THEN system SHALL NOT send any email (user not found).

4. WHEN a duplicate invite is created (same email, same workspace, pending status) THEN system SHALL NOT send a second email.

**Independent Test**: Admin invites `invited@example.com` → email is sent with workspace name, inviter, role, and accept link → second invite to same email does NOT send another email.

---

## Edge Cases

- WHEN the last admin tries to remove themselves THEN system SHALL reject with HTTP 422 (must transfer admin role first).
- WHEN a user is removed from a workspace THEN their historical transactions remain visible (shown as "ex-member").
- WHEN the settings page has zero pending invites THEN the invites section SHALL show an empty state.
- WHEN the settings page has zero members (only admin) THEN the members section SHALL show only the admin.
- WHEN a user is both admin in one workspace and editor in another THEN each workspace shows the correct role in the switcher.

---

## Requirement Traceability

| ID       | Story                                  | Phase | Status |
| -------- | -------------------------------------- | ----- | ------ |
| WSMC-01  | P1: Tela de Configurações do Workspace | Design | Pending |
| WSMC-02  | P1: WorkspaceSwitcher Component        | Design | Pending |
| WSMC-03  | P1: Shared Workspaces com Role         | Design | Pending |
| WSMC-04  | P1: Notificação de Convite por Email   | Design | Pending |

**Coverage:** 4 requirements, 0 mapped, 4 unmapped

---

## Success Criteria

- [ ] Admin accesses `/settings` and sees members + invites with correct ApiResource structure
- [ ] Editor can view settings but `isAdmin` is false (no mutation actions)
- [ ] Viewer gets 403 when accessing settings
- [ ] WorkspaceSwitcher shows all user's workspaces with role badges
- [ ] Switching workspaces reloads the page correctly
- [ ] Invite email is sent with workspace name, inviter name, role, and accept link
- [ ] Duplicate invite does not send second email
- [ ] All GET routes have smoke tests (assertOk + assertInertia)
- [ ] All ApiResource responses are tested for shape (keys + types, not just count)
