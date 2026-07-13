# State — Persistent Memory

## Active Decisions

| ID  | Decision                                                  | Date       | Context                  |
| --- | --------------------------------------------------------- | ---------- | ------------------------ |
| D-01 | ApiResource layer for all backend→frontend responses      | 2026-07-13 | Nested models = Resources too |
| D-02 | TypeScript strict: true on frontend                       | 2026-07-13 | All React code in TS     |
| D-03 | Laravel default naming: snake_case tables, PascalCase models | 2026-07-13 | Standard convention      |
| D-04 | UUIDs for frontend entity identification                 | 2026-07-13 | IDs in URLs/API          |
| D-05 | FormRequests dedicated per action (StoreXRequest, UpdateXRequest) | 2026-07-13 | Validation layer         |
| D-06 | Resource controllers (index, store, show, update, destroy) | 2026-07-13 | Standard CRUD            |
| D-07 | Authorization via Policies, manual registration for non-model | 2026-07-13 | Policy per model         |
| D-08 | Inertia pure: shared data + props + useForm()            | 2026-07-13 | No React Query           |
| D-09 | shadcn/ui for components, `resources/js/Components/ui/`  | 2026-07-13 | Follow shadcn conventions |
| D-10 | PHPUnit for feature tests                                | 2026-07-13 | No unit tests (for now)  |
| D-11 | No linting tools                                         | 2026-07-13 | Simplicity preference    |
| D-12 | No CI/CD for now                                         | 2026-07-13 |                         |
| D-13 | `/w/{workspace}/` route prefix, Route model binding (UUID) | 2026-07-13 | web.php only             |
| D-14 | useForm().errors default (Inertia built-in)              | 2026-07-13 | No custom wrapper        |
| D-15 | Shared data: auth.user, workspace, workspaces (minimum)  | 2026-07-13 | Rest on-demand           |
| D-16 | Service classes for business logic (App\Services\)       | 2026-07-13 | Not in models/controllers |
| D-17 | PHP 8.3 native enums                                     | 2026-07-13 | App\Enums\               |
| D-18 | PascalCase React components, hooks/ and lib/ at top level | 2026-07-13 |                           |
| D-19 | Domain-based folder structure for Pages/Components       | 2026-07-13 | Shared components at top level |
| D-20 | UI: pt-BR, Codebase: English                             | 2026-07-13 |                           |
| D-21 | Cypress for end-to-end frontend tests                    | 2026-07-13 |                           |
| D-22 | DeepSeek via Laravel AI SDK (provider-agnostic)          | 2026-07-13 | DEEPSEEK_API_KEY env     |
| D-23 | MariaDB as database                                      | 2026-07-13 |                           |

## Blockers

None currently.

## Lessons Learned

None yet — project hasn't started implementation.

## Deferred Ideas

None yet.

## Preferences

None yet.
