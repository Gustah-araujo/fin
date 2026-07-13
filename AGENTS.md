# Fin — Agent Instructions

## First: Load Coding Patterns

**Always load the skill `fin-coding-patterns` before writing any code** for this project. It contains the full convention reference including ApiResource rules, TypeScript strict mode, FormRequests, Policies, folder structure, and anti-patterns.

For visual work (layouts, components, styling), **also load `fin-design-system`** — it covers color tokens, typography, layout architecture, and component patterns.

## Environment

- All commands must be run inside the containers on `docker-compose.yml`

## Project Context

All specification and planning docs live in `.specs/`. Load these as needed:

- `.specs/project/PROJECT.md` — vision, tech stack, scope, constraints
- `.specs/project/ROADMAP.md` — milestones and feature status
- `.specs/project/STATE.md` — 23 active decisions, blockers, preferences
- `.specs/features/workspace-financeiro/spec.md` — 13 traceable requirements with acceptance criteria

## Stack (Greenfield)

| Layer | Tech |
|-------|------|
| Backend | Laravel 13.x, PHP 8.3+, MariaDB |
| Frontend | React 19, InertiaJS 2.x, TypeScript (strict), shadcn/ui |
| AI | Laravel AI SDK + DeepSeek (`DEEPSEEK_API_KEY`) |
| Auth | Laravel Socialite (Google OAuth) + email/password |

## TDD-First

**All design and implementation is TDD-first.** Tests are written BEFORE the implementation code:
- **Feature tests (PHPUnit)**: one test per acceptance criteria from the spec
- **E2E tests (Cypress)**: critical user journeys (register → login → create workspace)
- Design documents define test structure alongside component structure — what gets tested, not just what gets built
- Every task in tasks.md includes its corresponding test requirements and gate commands

## Key Conventions (non-obvious)

- **UUIDs** for all route model binding and frontend IDs — no auto-increment in URLs
- **ApiResource mandatory** — every controller response passes through a Resource; nested models must be Resources too
- **FormRequests** per action (`StoreXRequest`, `UpdateXRequest`) — not inline validation
- **Service classes** for business logic — not in controllers or models
- **Inertia pure** — shared data (user + workspace) in `HandleInertiaRequests`, rest via controller props, mutations via `useForm()`
- **Domain-based folders** for Pages/Components; shared hooks in `hooks/`, shared utils in `lib/`
- **shadcn/ui primitives** in `Components/ui/` — never modify manually, install via `npx shadcn-ui@latest add`
- **Feature tests only** (PHPUnit) — no unit tests; Cypress for E2E
- **No linting**, no CI/CD yet
- **UI in pt-BR, codebase in English**

## Routes

All workspace-scoped routes use the prefix `/w/{workspace}` with UUID route model binding:

```php
Route::middleware(['auth', 'verified'])->prefix('w/{workspace}')->group(function () {
    Route::resource('accounts', AccountController::class);
});
```

A global middleware redirects users with zero workspaces to the creation screen.

## After Scaffolding

Once `laravel new` is run, typical dev commands will be:

```bash
php artisan serve
npm run dev
php artisan make:model Account -mrcR --policy
php artisan test --filter=AccountTest
```

Update this section with the actual npm/packager commands from package.json.
