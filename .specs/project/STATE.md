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
| D-24 | Credit card expenses reuse Transaction with credit_card_id nullable (mutual exclusive with account_id) | 2026-07-14 | CCXP-01 spec; single transactions table |
| D-25 | Installments: per-installment Transaction rows with installment_number, installments_total, installment_group_id | 2026-07-14 | CCXP-01 spec; each installment belongs to its own bill |
| D-26 | CreditCardBill explicit model (card, year, month, status Open/Closed/Paid, total, paid_at, paid_to_account_id) | 2026-07-14 | CCXP-01 spec; bill lifecycle entity |
| D-27 | BillStatus enum in App\Enums\ (Open, Closed, Paid)       | 2026-07-14 | CCXP-01 spec              |
| D-28 | Card expense paid_at always null; payment is via BILL not individual expense | 2026-07-14 | CCXP-01 spec; card-specific semantics |
| D-29 | available_limit = credit_limit - sum(card expenses on non-paid bills) | 2026-07-14 | CCXP-01 spec; recalc on every card operation |
| D-30 | Bill payment creates a debit Transaction (type=Expense, paid_at=now) and recalculates account balance + card available_limit | 2026-07-14 | CCXP-01 spec; bridges credit→debit |
| D-31 | "Pagamento de Cartão" category auto-created per workspace on first card creation; system-managed (not user-deletable, name editable) | 2026-07-14 | CCXP-01 spec; bill payment category |
| D-32 | Bill closing: scheduled daily job + on-demand fallback; next open bill created lazily | 2026-07-14 | CCXP-01 spec; no proactive empty bills |
| D-33 | Installment value = round(total/count, 2); last installment absorbs remainder so sum = total exactly | 2026-07-14 | CCXP-01 spec              |
| D-34 | Income reuses `Transaction` with `type=Income`, `account_id` NOT NULL, `credit_card_id` must be null (mutual exclusive) | 2026-07-20 | INCM-01 spec; mirror of D-24 |
| D-35 | CRUD unificado: one income form with "É recorrente?" toggle revealing recurrence panel; no separate avulsa vs recorrente CRUDs | 2026-07-20 | INCM-01 spec; UX preference |
| D-36 | Create flow with toggle ON AND `start_date <= today`: creates Recurrence + first Transaction in single DB transaction; advances `next_date` to next cycle | 2026-07-20 | INCM-01 spec; first instance materialized |
| D-37 | Create flow with toggle ON AND `start_date > today`: creates only Recurrence (next_date = start_date); Transaction generated later by job | 2026-07-20 | INCM-01 spec; no phantom future transactions |
| D-38 | New `Recurrence` model (genérico, não `RecurringIncome`) + `RecurrenceFrequency` enum (Weekly, Monthly) in App\Enums; extensible to future recurring expenses | 2026-07-20 | INCM-01 spec; neutro p/ future reuse |
| D-39 | New `recurrence_id` uuid nullable FK on `transactions` (mirrors `installment_group_id` pattern from CCXP-01) | 2026-07-20 | INCM-01 spec; link recurrence ↔ generated instances |
| D-40 | Weekday encoding 0=Dom…6=Sáb (Carbon `dayOfWeek` compatible); `frequency_day` int: 0–6 if Weekly, 1–31 if Monthly; Monthly day-overflow falls back to last day of month (`addMonthNoOverflow`) | 2026-07-20 | INCM-01 spec; aligns with CCXP-01 closing_day convention |
| D-41 | End condition: `until_date` nullable date — null = infinite, date = last allowed occurrence (inclusive) | 2026-07-20 | INCM-01 spec; user preference |
| D-42 | `next_date` tracking column on Recurrence; job filters `WHERE next_date <= today AND (until_date IS NULL OR next_date <= until_date)` | 2026-07-20 | INCM-01 spec; idempotent + UI-renderable |
| D-43 | `ProcessRecurrencesJob` daily at midnight in `routes/console.php`, parallel to `CloseBillsJob`; optimistic lock `UPDATE ... WHERE next_date = <expected>` for idempotency; per-recurrence try/catch for resilience | 2026-07-20 | INCM-01 spec; mirrors D-32 lifecycle pattern |
| D-44 | Edit recurrence-generated instance: scope prompt "Apenas esta" / "Esta e futuras" (segunda atualiza a Recurrence pai + instâncias futuras já geradas, NÃO retroage passadas); frequency/frequency_day/start_date are NOT editable via instance scope (managed at /recurrences/{id}/edit only) | 2026-07-20 | INCM-01 spec; mirrors CCXP-01 edit scope pattern |
| D-45 | Delete recurrence-generated instance: scope prompt "Apenas esta" / "Esta e parar futuras" (segunda soft-deleta instância atual + futuras já geradas + soft-deleta a Recurrence pai; passadas permanecem visíveis) | 2026-07-20 | INCM-01 spec; UX preference |
| D-46 | Dedicated `/w/{workspace}/recurrences` page: list active + exhausted + paused (toggle); actions Editar, Pausar/Reativar, Gerar agora, Ver instâncias | 2026-07-20 | INCM-01 spec; règles orphans visible |
| D-47 | Job backfill policy: if `next_date` is multiple cycles in past, generate ONE transaction for most recent due date then advance to next future cycle; historic missed cycles are NOT backfilled (v1 simplification) | 2026-07-20 | INCM-01 spec; v1 simpler than full backfill |
| D-48 | Confirmação de recebimento reuses existing `TransactionService::pay()` / `unpay()` — does NOT affect parent Recurrence (confirming is orthogonal to generation) | 2026-07-20 | INCM-01 spec; orthogonality |
| D-49 | Recurrence pause uses explicit `status` column (`active`/`paused`), NOT soft-delete; soft-delete is reserved for real deletion flows (e.g., "Esta e parar futuras") | 2026-07-20 | Design review; avoids overloading soft-delete semantics |
| D-50 | Cypress E2E tests are a hard gate for INCM-01; must cover income CRUD, receipt confirmation, recurrence generation, scope edit/delete, and recurrence management | 2026-07-20 | Code review correction; aligns with TDD-first and project testing standards |

## Blockers

None currently.

## Lessons Learned

None yet — project hasn't started implementation.

## Deferred Ideas

None yet.

## Preferences

None yet.

## Session Log

| Date | Feature | Phase | Summary |
|------|---------|-------|---------|
| 2026-07-14 | CCXP-01 | Specify | Spec written: 7 user stories, 7 requirements (CCXP-01 to CCXP-07), 10 architectural decisions (D-24 to D-33) |
| 2026-07-14 | CCXP-01 | Design | Design written: CreditCardBill model, BillService, CardExpenseService, BillStatus enum, 3 migrations, TDD strategy with 64 PHPUnit tests + 10 Cypress E2E, 35 new files + 12 modified |
| 2026-07-14 | CCXP-01 | Tasks | 14 tasks (T1-T14) with TDD-first ordering, 8 phases, 6 parallel tasks across 3 phases, full granularity/diagram/co-location validation passed |
| 2026-07-14 | CCXP-01 | Execute | All 14 tasks implemented: 3 migrations, BillStatus enum, CreditCardBill model, BillService, CardExpenseService, CloseBillsJob, 3 controllers, 3 FormRequests, CreditCardBillPolicy, CreditCardBillResource, 4 React pages, radio-group component. 64 new PHPUnit tests (203 total, 709 assertions). Frontend build passes. ROADMAP CCXP-01 = 🟢 |
| 2026-07-20 | INCM-01 | Specify | Spec rewritten as unified CRUD: one income form with "É recorrente?" toggle; 6 user stories (INCM-01 to INCM-06), 15 new architectural decisions (D-34 to D-48), key decisions: CRUD unificado, Recurrence model, recurrence_id FK, next_date tracking, ProcessRecurrencesJob with optimistic lock, edit/delete scope prompt "Esta e futuras"/"Esta e parar futuras", dedicated /recurrences page, no backfill v1 |
| 2026-07-20 | INCM-01 | Design | Design written: Recurrence model/migration, RecurrenceService, ProcessRecurrencesJob, IncomeController, RecurrenceController, 5 React pages, unified form with recurrence toggle, scope edit/delete, dedicated /recurrences management, 18+ PHPUnit test files, 25+ files to create/modify |
| 2026-07-20 | INCM-01 | Design Review | The Fool review (pre-mortem + red team): scope bulk updates moved to background queue, workspace_id scoping in services, rate limiting, and pause/refactor from soft-delete to explicit `status` column (D-49) |
| 2026-07-20 | INCM-01 | Tasks | Tasks written: 21 atomic tasks across 6 phases, TDD-first, dependencies mapped, 12+ PHPUnit test files, 7 frontend pages, Cypress E2E hard gate, spec updated to reflect D-49 status semantics |
| 2026-07-20 | INCM-01 | Execute T7-T9 | RecurrenceService (38 tests), TransactionService income refactor, ProcessRecurrencesJob + ApplyRecurrenceScopeChangeJob implemented; 307 PHPUnit tests passing; design.md updated for async scope jobs |
