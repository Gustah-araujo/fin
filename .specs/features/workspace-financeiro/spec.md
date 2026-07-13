# Workspace Financeiro — Specification

## Problem Statement

Pessoas e casais perdem tempo gerenciando finanças em planilhas ou apps limitados que não entendem seu contexto. Não há visibilidade clara de gastos futuros parcelados, nem inteligência para extrair padrões ou importar extratos automaticamente. Workspaces colaborativos são raros — quando existem, não separam papéis adequadamente.

## Goals

- [x] Workspace multi-usuário com papéis e convites
- [x] Gestão completa de finanças: contas, débito, crédito, receitas, futuras
- [x] IA para insights diários e chat conversacional
- [x] Importação inteligente de extratos com confirmação

## Out of Scope

| Feature             | Reason                          |
| ------------------- | ------------------------------- |
| Multi-moeda         | v1 apenas BRL                   |
| Notificações push   | Insights visíveis só no app     |
| Mobile nativo       | Web responsivo suficiente       |
| Open Banking         | Complexidade regulatória        |
| Budget/orçamento    | Escopo separado                 |
| Exportação          | v1 foca em entrada e análise    |

---

## User Stories

### P1: Autenticação e Onboarding ⭐ MVP

**User Story**: As a user, I want to register, log in, and create or join a workspace so that I can start managing finances.

**Why P1**: Sem autenticação e workspace, nada mais funciona. É a porta de entrada.

**Acceptance Criteria**:

1. WHEN a new user registers with email+password THEN system SHALL create the user, send verification email, and redirect to workspace creation screen.
2. WHEN a user logs in with Google THEN system SHALL authenticate via OAuth and proceed with the same flow.
3. WHEN an authenticated user has zero workspaces THEN system SHALL redirect them to the workspace creation screen (middleware global).
4. WHEN a user creates a workspace THEN they SHALL become the admin of that workspace automatically.
5. WHEN an admin invites a user by email THEN system SHALL send an invite; WHEN the invited user accepts THEN they SHALL be added as the chosen role (admin/editor/viewer).
6. WHEN a user logs in with one or more workspaces THEN system SHALL show workspace selector or redirect to the last active workspace.

**Independent Test**: Register → create workspace → log out → log in with Google → see workspace. Invite another user → they accept → both see same data.

---

### P1: Contas Bancárias ⭐ MVP

**User Story**: As a workspace member, I want to manage bank accounts with balance and type so that I can track where my money is.

**Why P1**: Contas são a base de todas as transações financeiras.

**Acceptance Criteria**:

1. WHEN a user creates an account with name, type (checking/savings/investment), and initial balance THEN system SHALL store it linked to the workspace.
2. WHEN a user edits account details THEN system SHALL update and recalculate balance based on transactions.
3. WHEN a user views account list THEN system SHALL show current balance, calculated as: initial_balance + sum(income) - sum(paid_debit_expenses) - sum(paid_credit_bills).
4. WHEN an account has transactions THEN system SHALL prevent deletion; only soft-delete/archive is allowed.

**Independent Test**: Create 2 accounts with different balances → verify they appear on dashboard → edit one → see updated balance.

---

### P1: Categorias e Tags ⭐ MVP

**User Story**: As a workspace member, I want to define categories and tags so that I can organize my income and expenses meaningfully.

**Why P1**: Sem categorias, insights da IA e filtros ficam impossíveis.

**Acceptance Criteria**:

1. WHEN a user creates a category (name, type: income|expense|both, color, icon) THEN it SHALL be available workspace-wide.
2. WHEN a user creates a tag (name, color) THEN it SHALL be available for applying to any transaction.
3. WHEN a category is deleted THEN transactions with that category SHALL be reassigned to a default "Uncategorized" category.
4. WHEN a transaction is assigned a category + multiple tags THEN system SHALL allow filtering by any combination.

**Independent Test**: Create categories "Alimentação", "Transporte" → create tags "Combustível", "Manutenção" → create expense → assign both → filter works.

---

### P1: Despesas em Débito ⭐ MVP

**User Story**: As a workspace member, I want to record debit expenses linked to a bank account so that my balance reflects real spending.

**Why P1**: É o tipo mais básico e frequente de transação.

**Acceptance Criteria**:

1. WHEN a user creates a debit expense (description, value, date, account, category, tags) THEN system SHALL store it as unpaid by default.
2. WHEN a user marks a debit expense as paid THEN system SHALL deduct from the linked account balance and record payment date.
3. WHEN a user edits a paid expense THEN system SHALL recalculate account balance.
4. WHEN a user deletes an expense THEN system SHALL restore the balance if it was paid.
5. WHEN viewing unpaid expenses THEN system SHALL highlight them visually distinct from paid ones.

**Independent Test**: Create expense → account balance unchanged → mark paid → balance drops. Create another → delete it → no impact.

---

### P1: Cartões de Crédito ⭐ MVP

**User Story**: As a workspace member, I want to manage credit cards with their billing cycles so that I can track credit spending separately from debit.

**Why P1**: Cartão de crédito é o segundo meio de pagamento mais comum e tem mecânica completamente diferente (só debita no pagamento da fatura).

**Acceptance Criteria**:

1. WHEN a user creates a credit card (name, closing day, due day) THEN it SHALL belong to the workspace.
2. WHEN a user views a credit card THEN system SHALL show current bill total (sum of all expenses since last closing date) and next closing/due dates.
3. WHEN a credit card bill is paid THEN system SHALL prompt user to select a bank account and create a corresponding debit expense for the total amount.
4. WHEN a credit card is deleted THEN system SHALL prevent deletion if it has any expenses; only archive is allowed.

**Independent Test**: Create card → add expense → see bill total → pay bill → linked account balance drops by bill amount.

---

### P1: Despesas de Cartão de Crédito ⭐ MVP

**User Story**: As a workspace member, I want to record credit card purchases (individual and installment-based) so that I can see exactly what's on my bill.

**Why P1**: Sem despesas de crédito, o cartão não tem função.

**Acceptance Criteria**:

1. WHEN a user creates a credit card expense (description, total value, date, card, category, tags) THEN system SHALL store as single-purchase by default.
2. WHEN a user creates an installment expense (total value, installments count, first installment date) THEN system SHALL generate N individual installment records, each with due date = first_date + month_offset.
3. WHEN viewing a credit card bill THEN system SHALL show each purchase and each installment individually, with installment indicator (e.g., "3/12").
4. WHEN an installment is edited/deleted THEN system SHALL prompt: "edit only this installment" or "edit all future installments".
5. WHEN the card's closing date passes THEN system SHALL automatically associate all expenses within that cycle to the current bill.

**Independent Test**: Create purchase → add 12-installment purchase → view bill → see 1 single + 1 installment entry → verify due dates spread across months.

---

### P1: Receitas ⭐ MVP

**User Story**: As a workspace member, I want to record income so that my account balances reflect all money coming in.

**Why P1**: Sem receitas, o saldo só diminui e o retrato financeiro fica incompleto.

**Acceptance Criteria**:

1. WHEN a user creates income (description, value, date, account, category, tags) THEN system SHALL add to the account balance.
2. WHEN a user marks income as recurring (frequency: monthly, interval, end date or infinite) THEN system SHALL auto-generate future income entries up to 12 months ahead on a scheduled basis.
3. WHEN a user edits/deletes a recurring income entry THEN system SHALL prompt: "this entry only" or "this and all future entries".
4. WHEN viewing dashboard THEN system SHALL show total income for the current month vs previous month.

**Independent Test**: Create income → balance increases. Create recurring monthly salary → see future entries generated. Delete one → prompted for scope.

---

### P2: Despesas Futuras (Dívidas Pessoais)

**User Story**: As a workspace member, I want to register personal debts in installments (e.g., borrowed R$1000 from someone, paying R$250/month) so that I can plan upcoming obligations.

**Why P2**: Essencial para o planejamento, mas tecnicamente é uma variação de despesas que já existe. Pode vir logo após o core estar sólido.

**Acceptance Criteria**:

1. WHEN a user creates a future expense (description, total value, installment count, installment value, first due date, creditor name) THEN system SHALL generate N future entries with status "pending".
2. WHEN viewing future expenses THEN system SHALL show a list grouped by upcoming month with sum totals per month.
3. WHEN a future expense reaches its due date THEN system SHALL NOT auto-debit; user must manually confirm payment like any debit expense.
4. WHEN a user confirms payment of a future expense installment THEN system SHALL prompt to select the debit account and mark it as paid.

**Independent Test**: Create 4-installment debt → see list grouped by month → "next month" shows R$250 total → mark first as paid → account debited.

---

### P2: Dashboard Financeiro

**User Story**: As a workspace member, I want a dashboard with key metrics so that I can see my financial health at a glance.

**Why P2**: O dashboard depende de todos os P1s estarem funcionando para ter dados reais. Insights da IA também aparecem aqui.

**Acceptance Criteria**:

1. WHEN a user opens the dashboard THEN system SHALL show:
   - Total balance across all accounts
   - Current month income vs expenses summary
   - Unpaid debit expenses count and total
   - Credit card bills summary (per card, with due dates)
   - Recent transactions (last 10)
2. WHEN a user clicks any summary widget THEN system SHALL navigate to the corresponding detailed list.
3. WHEN AI insights are generated THEN system SHALL display them above their corresponding widget (e.g., spending pattern insight above expenses widget).

**Independent Test**: Create mixed transactions → dashboard reflects all aggregates correctly → click widgets → navigates to detail pages.

---

### P2: Insights IA Proativos

**User Story**: As a workspace member, I want AI-generated insights on my dashboard so that I can spot patterns and make better decisions without effort.

**Why P2**: É o diferencial do produto, mas depende dos dados existirem (P1s).

**Acceptance Criteria**:

1. WHEN the daily insight job runs (midnight) THEN system SHALL generate insights for each workspace using DeepSeek via Laravel AI SDK.
2. WHEN insights are generated THEN system SHALL produce cards like:
   - "You spent 40% more on restaurants this month"
   - "At this rate, your balance will be negative in 3 months"
   - "Your subscription spending increased R$148/month since January"
   - "This grocery purchase was 62% higher than your average"
   - "You have 14 active installments. R$1,870 is already committed for the next 3 months"
   - "In November, 3 installments end. Your monthly expenses will drop ~R$420"
3. WHEN a user views the dashboard THEN system SHALL display active insights above their corresponding widgets.
4. WHEN insight data changes THEN system SHALL regenerate insights (replace or update existing).

**Independent Test**: Populate transactions → run insight job manually → dashboard shows relevant insights → verify insight accuracy manually.

---

### P2: Chat IA Conversacional

**User Story**: As a workspace member, I want to chat with an AI that understands my financial data so that I can ask questions and get advice.

**Why P2**: Complementa os insights proativos com interação sob demanda.

**Acceptance Criteria**:

1. WHEN a user opens the chat THEN system SHALL show conversation history (per user, per workspace) and allow starting a new conversation.
2. WHEN a user sends a message THEN system SHALL use the Laravel AI SDK agent with tools to query workspace financial data (accounts, expenses, income, categories, tags, credit cards).
3. WHEN the AI responds THEN system SHALL stream the response to the UI in real-time using SSE (Server-Sent Events).
4. WHEN a user asks "onde posso cortar gastos?" THEN the AI SHALL have access to expense data via tools and provide concrete, data-backed suggestions.
5. WHEN a conversation is saved THEN system SHALL persist it using the RemembersConversations trait from Laravel AI SDK.

**Independent Test**: Open chat → ask "quanto gastei esse mês?" → AI responds with actual numbers from DB → ask follow-up → maintains context → close and reopen → see conversation history.

---

### P2: Importação de Extratos via IA

**User Story**: As a workspace member, I want to upload bank statements (CSV/PDF/Excel) and have AI parse them, so that I don't manually type every transaction.

**Why P2**: Economia de tempo massiva, mas depende da IA estar funcionando (P2).

**Acceptance Criteria**:

1. WHEN a user uploads a file (CSV/PDF/Excel) and selects a target account THEN system SHALL send it to DeepSeek (via AI SDK) for parsing with structured output.
2. WHEN parsing completes THEN system SHALL show a confirmation screen with:
   - All parsed transactions in a table (date, description, value, type: debit/credit)
   - Checkbox per row to include/exclude
   - Editable fields (date, description, value, category, tags)
   - Possible duplicates highlighted and pre-unchecked with warning
   - Summary: "X transactions, Y total value, Z possible duplicates"
3. WHEN the user confirms THEN system SHALL persist all checked transactions to the selected account.
4. WHEN duplicate detection runs THEN system SHALL compare parsed transactions against existing ones by date+value+description similarity; IA can also flag potential duplicates based on context.
5. WHEN the user cancels the import THEN system SHALL discard all parsed data without persisting anything.
6. WHEN file parsing fails THEN system SHALL show an error with the reason (unsupported format, unreadable file, AI error).

**Independent Test**: Upload a CSV with 20 transactions (2 duplicates with existing data) → see confirmation screen with 18 checked + 2 flagged → edit one description → confirm → 18 new transactions in DB.

---

### P3: Planejamento de Gastos Futuros

**User Story**: As a workspace member, I want to see all upcoming obligations for the next month so that I can plan around my salary.

**Why P3**: É uma view derivada de dados já existentes (despesas futuras + parcelas de cartão). Valor alto, mas implementação simples.

**Acceptance Criteria**:

1. WHEN a user views the "upcoming expenses" page THEN system SHALL show:
   - All future expense installments due in the selected period (default: next 30 days)
   - All credit card installments due in the same period
   - Grouped by month with sum totals
2. WHEN the user selects a different period THEN system SHALL recalculate.

**Independent Test**: Have 3 future expense installments + 5 credit card installments for next month → page shows 8 items → total correctly summed.

---

## Edge Cases

- WHEN a user is removed from a workspace THEN their transactions remain, shown as "ex-member" in historical records.
- WHEN the last admin leaves a workspace THEN system SHALL prompt to transfer admin role before allowing exit.
- WHEN a credit card expense is created AFTER the closing date THEN it SHALL appear in the NEXT bill, not the current one.
- WHEN an installment spans across years THEN system SHALL correctly calculate due dates (e.g., December → January).
- WHEN a user has zero workspaces and tries to access any route THEN middleware SHALL redirect to workspace creation.
- WHEN AI insight generation fails (API error, timeout) THEN system SHALL log the error and show "insights unavailable" on dashboard without breaking the page.
- WHEN a file upload exceeds max size THEN system SHALL reject with clear message.
- WHEN an account reaches zero/negative balance THEN it SHALL still be shown (not hidden).

---

## Requirement Traceability

| ID      | Story                        | Phase  | Status  |
| ------- | ---------------------------- | ------ | ------- |
| AUTH-01 | P1: Auth & Onboarding        | Design | Pending |
| ACCT-01 | P1: Contas Bancárias         | Design | Pending |
| CAT-01  | P1: Categorias e Tags        | Design | Pending |
| DEBT-01 | P1: Despesas em Débito       | Design | Pending |
| CARD-01 | P1: Cartões de Crédito       | Design | Pending |
| CCXP-01 | P1: Despesas de Cartão       | Design | Pending |
| INCM-01 | P1: Receitas                 | Design | Pending |
| FUTX-01 | P2: Despesas Futuras         | -      | Pending |
| DASH-01 | P2: Dashboard                | -      | Pending |
| INSG-01 | P2: Insights IA              | -      | Pending |
| CHAT-01 | P2: Chat IA                  | -      | Pending |
| IMPT-01 | P2: Importação Extratos      | -      | Pending |
| PLAN-01 | P3: Planejamento Futuro      | -      | Pending |

**Coverage:** 13 total, 0 mapped, 13 unmapped

---

## Success Criteria

- [ ] User can create workspace and invite partner in < 2 minutes
- [ ] User can record a debit expense in < 15 seconds
- [ ] Credit card bill payment correctly deducts from selected account
- [ ] AI chat answers "quanto gastei esse mês?" with accurate numbers
- [ ] Import 50-transaction CSV with >90% parse accuracy on first try
- [ ] Dashboard loads in < 3 seconds with up to 5000 transactions
