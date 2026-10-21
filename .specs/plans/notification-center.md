# Plan: Notification Center (Central de Notificações)

## Source
Issue: [Feature] Central de Notificações no Header com Polling e Slide-over Drawer

## Overview
Implement a notification center accessible from the AppHeader with a Bell icon + badge, a slide-over drawer, 30s polling, and Laravel DatabaseNotifications integration. Scopes all queries by authenticated user + active workspace.

## Current State Analysis

### What exists
- **User model** uses `Notifiable` trait (`Illuminate\Notifications\Notifiable`) — ready for DatabaseNotifications
- **No `notifications` table** — Laravel's default migration not yet published/run
- **Sheet component** exists at `resources/js/components/ui/sheet.tsx` (Radix-based)
- **Sonner Toaster** already configured in `AuthenticatedLayout.tsx`
- **Toast flash system** via `App\Support\Toast` (session flash → Sonner)
- **UserResource** pattern established for ApiResources
- **Existing Notification class**: `App\Notifications\Workspace\NewInvite` (mail-only, no `database` channel)
- **Workspace-scoped routes** use `prefix('w/{workspace}')` pattern
- **No API routes file** (`routes/api.php` doesn't exist) — all routes are in `routes/web.php`
- **User factory** uses UUIDs, workspace membership via `workspace_user` pivot

### What needs to be created
1. Migration for `notifications` table (Laravel's default schema)
2. Notification API controller + routes
3. NotificationResource (ApiResource)
4. NotificationService (business logic)
5. Frontend: `NotificationBell` component (header icon + badge)
6. Frontend: `NotificationDrawer` component (slide-over sheet)
7. Frontend: `useNotificationPolling` hook (30s interval)
8. Backend: Feature tests (list, mark-read, mark-all-read, isolation)
9. Frontend: PHPUnit smoke test for notification routes
10. E2E: Cypress tests for full notification flow

---

## Implementation Steps (Atomic, Ordered)

### Step 1: Migration — Create `notifications` table
**Files:**
- `database/migrations/2026_10_20_000000_create_notifications_table.php`

Laravel's standard `notifications` table schema:
- `id` (UUID, primary)
- `type` (string)
- `notifiable_type` (string)
- `notifiable_id` (foreign key → users.id)
- `data` (JSON) — stores `title`, `message`, `action_url`, `action_text`, `workspace_uuid`
- `read_at` (timestamp, nullable)
- `created_at`, `updated_at`

The `data` JSON includes `workspace_uuid` to scope notifications per workspace.

**Gate:** Migration runs successfully (`php artisan migrate`).

---

### Step 2: API Routes — Notification endpoints
**Files:**
- `routes/web.php` (add new route group)

Add workspace-scoped routes under `prefix('w/{workspace}')`:
```php
Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
Route::patch('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
Route::post('notifications/mark-all-as-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-as-read');
```

Note: Using `web.php` routes (not `api.php`) because this project doesn't use `api.php` — all routes are web middleware with Inertia. The controller returns `JsonResponse` with ApiResource.

---

### Step 3: NotificationResource (ApiResource)
**Files:**
- `app/Http/Resources/NotificationResource.php`

Fields returned:
```php
[
    'id' => $this->id, // UUID
    'title' => $this->data['title'] ?? null,
    'message' => $this->data['message'] ?? null,
    'action_url' => $this->data['action_url'] ?? null,
    'action_text' => $this->data['action_text'] ?? null,
    'is_read' => $this->read_at !== null,
    'created_at' => $this->created_at->toISOString(),
    'read_at' => $this->read_at?->toISOString(),
]
```

---

### Step 4: NotificationService
**Files:**
- `app/Services/NotificationService.php`

Methods:
- `getUnreadCount(User $user, Workspace $workspace): int` — count unread notifications for user in workspace
- `getNotifications(User $user, Workspace $workspace, int $limit = 20): Collection` — latest notifications for user in workspace
- `markAsRead(User $user, Notification $notification): void` — set `read_at = now()` (with ownership check)
- `markAllAsRead(User $user, Workspace $workspace): void` — bulk update all unread

All methods filter by `notifiable_type = User::class`, `notifiable_id = $user->id`, and `data->workspace_uuid = $workspace->uuid`.

---

### Step 5: NotificationController
**Files:**
- `app/Http\Controllers\NotificationController.php`

Methods:
- `index(Workspace $workspace): JsonResponse` — returns `{ unread_count, notifications }` using `NotificationResource::collection()`
- `markAsRead(Workspace $workspace, DatabaseNotification $notification): JsonResponse` — calls service, returns updated resource
- `markAllAsRead(Workspace $workspace): JsonResponse` — calls service, returns `{ unread_count: 0 }`

All actions use `Gate::authorize('viewAny', [$workspace])` pattern or implicit ownership check via service.

---

### Step 6: Frontend Hook — `useNotificationPolling`
**Files:**
- `resources/js/hooks/use-notification-polling.ts`

- Uses `router.reload()` or direct `fetch` via `axios` to hit `notifications.index` route
- Polls every 30 seconds
- Returns `{ unreadCount, notifications, isLoading, refresh }`
- Cleans up interval on unmount
- Accepts workspace UUID parameter

Implementation uses Inertia's `router.get()` or direct fetch to avoid full page reload:
```typescript
const fetchNotifications = useCallback(async () => {
    const response = await fetch(route('notifications.index', { workspace: workspaceUuid }));
    const data = await response.json();
    setUnreadCount(data.unread_count);
    setNotifications(data.notifications);
}, [workspaceUuid]);
```

---

### Step 7: Frontend Component — `NotificationBell`
**Files:**
- `resources/js/Components/NotificationBell.tsx`

- Renders Bell icon (lucide-react) with Badge overlay
- Uses `useNotificationPolling` hook for data
- Opens `NotificationDrawer` via Sheet component
- Badge shows `unreadCount` (hidden when 0)
- Positioned in AppHeader before `UserMenu`

---

### Step 8: Frontend Component — `NotificationDrawer`
**Files:**
- `resources/js/Components/NotificationDrawer.tsx`

Slide-over sheet containing:
- Header: "Notificações" + close button + "X não lidas" + "Marcar todas como lidas" button
- Notification list with items showing:
  - Title (bold)
  - Message
  - Relative time ("há 5 minutos") via `date-fns` `formatDistanceToNow` with `pt-BR` locale
  - Action button (if `action_url` + `action_text` present) → navigates via Inertia `router.visit()`
  - Individual "Marcar como lida" button per item
- Loading skeleton state (3 skeleton items)
- Empty state when no notifications

---

### Step 9: Integrate into AppHeader
**Files:**
- `resources/js/Components/AppHeader.tsx`

Add `<NotificationBell />` between the breadcrumb section and `<UserMenu />` in the `ml-auto` div.

---

### Step 10: PHPUnit Feature Tests
**Files:**
- `tests/Feature/Notifications/NotificationFeatureTest.php`
- `tests/Feature/Notifications/NotificationSmokeTest.php`

Feature tests:
1. `test_user_can_list_notifications_in_their_workspace` — create notifications, verify response structure
2. `test_notification_listing_is_scoped_to_workspace` — user A's workspace notifications don't appear in user B's workspace
3. `test_user_can_mark_single_notification_as_read` — PATCH endpoint, verify `read_at` set
4. `test_user_can_mark_all_notifications_as_read` — POST endpoint, verify all `read_at` set, badge zeroed
5. `test_user_cannot_read_another_users_notification` — isolation test, 404/403

Smoke test:
1. `test_notifications_index_returns_ok` — `assertOk()` on index route
2. `test_mark_as_read_returns_ok` — `assertOk()` on mark-read route
3. `test_mark_all_as_read_returns_ok` — `assertOk()` on mark-all route

---

### Step 11: Cypress E2E Tests
**Files:**
- `cypress/e2e/notifications/notification-center.cy.js`

Scenarios:
1. **Smoke:** Dashboard renders with bell icon visible
2. **Badge display:** Create 3 unread notifications (via factory in seeder or API), verify badge shows "3"
3. **Open drawer:** Click bell, verify drawer opens with notification list
4. **Mark as read:** Click "Marcar como lida" on first item, verify badge decrements, toast appears
5. **Mark all as read:** Click "Marcar todas como lidas", verify badge disappears, toast appears
6. **Action button:** Click action button on notification with `action_url`, verify navigation occurs
7. **Empty state:** After marking all read, verify empty state message

---

### Step 12: Quality Gate & Type Definitions
**Files:**
- `resources/js/types/index.d.ts` — add notification type definitions

Add notification types to the shared PageProps or as standalone types:
```typescript
interface NotificationData {
    id: string;
    title: string | null;
    message: string | null;
    action_url: string | null;
    action_text: string | null;
    is_read: boolean;
    created_at: string;
    read_at: string | null;
}
```

Run:
- `composer quality` (Pint + PHPMD)
- `npm run quality` (ESLint + Prettier)

---

## Key Design Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Route file | `routes/web.php` | Project doesn't use `api.php`; all routes are web middleware |
| Auth | `auth` middleware | Notifications are per-authenticated-user, not public |
| Scoping | `workspace_uuid` in `data` JSON | Avoids extra column; keeps notifications portable |
| Polling mechanism | `setInterval` + `fetch` | Simple, no extra dependencies; cleanup on unmount |
| Drawer component | Sheet (Radix) | Already installed in `Components/ui/sheet.tsx` |
| Toast feedback | Sonner (already configured) | Project standard for mutations |
| Relative time | `date-fns` `formatDistanceToNow` | Lightweight, supports `pt-BR` locale |

---

## File Checklist (What Gets Created/Modified)

### Created
1. `database/migrations/2026_10_20_000000_create_notifications_table.php`
2. `app/Http/Resources/NotificationResource.php`
3. `app/Services/NotificationService.php`
4. `app/Http/Controllers/NotificationController.php`
5. `resources/js/hooks/use-notification-polling.ts`
6. `resources/js/Components/NotificationBell.tsx`
7. `resources/js/Components/NotificationDrawer.tsx`
8. `tests/Feature/Notifications/NotificationFeatureTest.php`
9. `tests/Feature/Notifications/NotificationSmokeTest.php`
10. `cypress/e2e/notifications/notification-center.cy.js`

### Modified
1. `routes/web.php` — add notification routes
2. `resources/js/Components/AppHeader.tsx` — add NotificationBell
3. `resources/js/types/index.d.ts` — add notification types

---

## Verification Checklist

- [ ] `php artisan migrate` succeeds
- [ ] `php artisan test` passes all notification tests
- [ ] `composer quality` passes
- [ ] `npm run quality` passes
- [ ] `npm run build` succeeds (no TypeScript errors)
- [ ] Cypress notification tests pass
- [ ] Manual verification: bell icon visible, badge updates, drawer opens/closes, mark as read works, toast appears
