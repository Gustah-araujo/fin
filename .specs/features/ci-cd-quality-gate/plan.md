# Plan: Quality Gate CI/CD Workflow

## Issue Reference
`[CI/CD] Quality Gate em Pull Requests para a branch master`

## Analysis

### Project Reality vs. Issue Specification

| Issue Requests | Project Reality | Decision |
|---|---|---|
| PostgreSQL service | MariaDB 11 in docker-compose | Use **MariaDB** for E2E |
| Redis service | No Redis in docker-compose | **Omit Redis** |
| `php artisan serve` + `npx cypress run` | Cypress runs via Docker compose (`network_mode: host`) | Run Cypress **directly** (`npx cypress run`) with `php artisan serve` |
| — | Project uses **MariaDB** in production | Backend tests use **MariaDB service** (matching real DB) |
| — | Mailpit needed for registration E2E | Include **Mailpit service** for E2E job |

### Architecture: 3 Parallel Jobs

```
┌─────────────────────────────────────────────────────┐
│                  Pull Request                        │
│                 (opened / synced)                    │
└──────────────┬──────────────┬───────────────────────┘
               │              │
     ┌─────────▼──┐   ┌──────▼───────┐   ┌──────────▼──────────┐
     │  Quality   │   │   Backend    │   │       E2E           │
     │   Gate     │   │    Tests     │   │    (Cypress)        │
     │            │   │              │   │                     │
      │ composer   │   │ php artisan  │   │ MariaDB + Mailpit   │
      │ quality    │   │ test         │   │ services            │
      │ npm run    │   │ (MariaDB)    │   │ php artisan serve   │
      │ quality    │   │              │   │ npx cypress run     │
     └────────────┘   └──────────────┘   └─────────────────────┘
```

### Job 1: Quality Gate
- **Purpose:** Static analysis — Pint (format) + PHPMD (complexity) + ESLint + Prettier
- **No services needed** (pure static analysis)
- **PHP 8.3**, **Node 22**
- Steps: checkout → setup PHP → setup Node → composer install → npm ci → `composer quality` → `npm run quality`

### Job 2: Backend Tests
- **Purpose:** PHPUnit feature tests against MariaDB
- **Services:** MariaDB 11 (matches production database)
- **PHP 8.3**, **Node 22** (needed for build)
- Steps: checkout → setup PHP → setup Node → composer install → npm ci → build → prepare env (migrate) → `php artisan test`

### Job 3: E2E Tests (Cypress)
- **Purpose:** Full user journey tests
- **Services:** MariaDB 11 + Mailpit
- **PHP 8.3**, **Node 22**
- Cypress `baseUrl` overridden to `http://localhost:8000` (php artisan serve default port)
- Steps: checkout → setup PHP → setup Node → composer install → npm ci → build → prepare env → `php artisan serve &` → wait for app → `npx cypress run`

### Key Technical Decisions

1. **Skip draft PRs** via `if: github.event.pull_request.draft == false` on each job
2. **MariaDB healthcheck** uses `mariadb-admin ping` (not `mysqladmin` — the image provides both)
3. **Mailpit healthcheck** uses `wget` to ping its API endpoint
4. **App readiness** for Cypress: poll `http://localhost:8000/login` with `wget` in a retry loop
5. **Cypress baseUrl override** via `CYPRESS_BASE_URL=http://localhost:8000` env var
6. **Node 22** to match the project's Dockerfile (`setup_22.x`)
7. **Deterministic installs** via `composer.lock` and `package-lock.json`

### Files to Create
- `.github/workflows/quality-gate.yml` — the main workflow

### Verification
- Validate YAML syntax
- Ensure all service env vars match docker-compose conventions
- Confirm `composer quality` and `npm run quality` scripts exist and work
- Confirm `php artisan test` runs against MariaDB service
- Confirm Cypress can run with `npx cypress run` (not just via Docker)
