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
