# Cartões de Crédito V2 — Design

**Spec:** `.specs/features/cartoes-v2/spec.md`
**Context:** `.specs/features/cartoes-v2/context.md`
**Status:** Approved

---

## Architecture Overview

O refactor unifica dois silos (despesas de conta vs. despesas de cartão) num único fluxo baseado em `Transaction`. O diagrama alto-nível mostra como os domínios se conectam após o refactor:

```mermaid
graph TD
    subgraph Frontend ["Frontend - React + Inertia"]
        A[Transactions/Create.tsx] --> B[Transactions/Edit.tsx]
        C[Cards/Show.tsx — Hub] --> D[Transactions/Create.tsx]
        E[Transactions/Index.tsx — DataTable]
        F[Recurrences/Index.tsx]
    end

    subgraph Controllers ["Backend Controllers"]
        G[TransactionController]
        H[CreditCardController]
        I[CreditCardBillController]
        J[RecurrenceController]
    end

    subgraph Services ["Services"]
        K[TransactionService]
        L[BillService]
        M[CreditCardService]
        N[RecurrenceService]
        O[AccountService]
    end

    subgraph Data ["Data"]
        P[(transactions)]
        Q[(credit_cards)]
        R[(credit_card_bills)]
        S[(recurrences)]
        T[(accounts)]
    end

    subgraph Jobs ["Jobs - diários"]
        U[CloseBillsJob]
        V[PreCreateBillsJob]
        W[ProcessRecurrencesJob]
    end

    A --> G
    D --> G
    C --> H
    E --> G
    F --> J

    G --> K
    H --> M
    I --> L
    J --> N

    K --> L
    K --> M
    N --> L
    N --> M
    L --> M
    L --> O

    K --> P
    K --> Q
    K --> R
    L --> R
    M --> Q
    N --> P
    N --> S

    U --> L
    V --> M
    W --> N
```

---

## Domain 1: Service Layer — TransactionService Unification

O `CardExpenseService` é fundido dentro do `TransactionService`. Métodos específicos prefixados (`createCard*`, `updateCard*`, `deleteCard*`) mantêm coesão. Helpers partilhados (`resolveBill`, `syncTags`, `ensureBillNotPaid`) eliminam duplicação.

```mermaid
classDiagram
    class TransactionService {
        +create : Transaction
        +createCardExpense : Transaction
        +createCardInstallment : Transaction[1..48]
        +update : Transaction
        +updateCardSingle : Transaction
        +updateCardGroup : void
        +deleteCardSingle : void
        +deleteCardGroup : void
        +pay : void
        +unpay : void
        -resolveBill : CreditCardBill
        -syncTags : void
        -ensureBillNotPaid : void
    }

    class BillService {
        +computeBillPeriod : array
        +computeClosingDate : Carbon
        +computeDueDate : Carbon
        +findOrCreateBill : CreditCardBill
        +recalculateBillTotal : void
        +closeBill : void
        +closeBillsBefore : void
        +payBill : void
        +undoPayment : void
    }

    class CreditCardService {
        +create : CreditCard
        +update : CreditCard
        +recalculateAvailableLimit : void
        +ensurePaymentCategory : Category
        +preCreateBills : void
        +archive : void
    }

    class AccountService {
        +recalculateBalance : void
    }

    TransactionService --> BillService : findOrCreateBill, recalculateBillTotal
    TransactionService --> CreditCardService : recalculateAvailableLimit
    BillService --> CreditCardService : recalculateAvailableLimit
    BillService --> AccountService : recalculateBalance
```

### Decisões

| Decisão | Escolha | Racionale |
|---------|---------|-----------|
| Organização dos métodos | Métodos específicos prefixados (`createCardExpense`, etc.) + helpers partilhados | Cada método permanece coeso e testável; controller faz roteamento inicial |
| Helpers extraídos de CardExpenseService | `resolveBill()`, `syncTags()`, `ensureBillNotPaid()` | Elimina duplicação entre caminhos de conta e cartão |
| Guard de pagamento | `pay()`/`unpay()` rejeitam `credit_card_id` com mensagem específica | Enforcement real do invariant D-28 |

---

## Domain 2: Recurrence — Suporte a Cartão

O `RecurrenceService` recebe `BillService` e `CreditCardService` como novas dependências de construtor. O método `createWithBuffer()` passa a aceitar `credit_card_id` como alternativa a `account_id` (mutualmente exclusivos). A validação de colisão com fatura paga é adicionada.

```mermaid
classDiagram
    class RecurrenceService {
        +create : Recurrence
        +createWithFirstInstance : Recurrence
        +createWithBuffer : Recurrence
        +propagateToFuture : void
        +applyUpdateThisAndFuture : void
        +applyDeleteThisAndFuture : void
        +generateBufferInstances : void
        +updateRule : void
        +pause : void
        +restore : void
        +skipConsumedPeriods : void
        -validatePaidBillCollision : void
        -resolveCardBill : CreditCardBill
    }

    class BillService2 {
        +findOrCreateBill : CreditCardBill
        +computeBillPeriod : array
    }

    class CreditCardService2 {
        +recalculateAvailableLimit : void
    }

    class ProcessRecurrencesJob {
        +handle : void
    }

    class ApplyRecurrenceScopeChangeJob {
        +handle : void
    }

    RecurrenceService --> BillService2 : findOrCreateBill
    RecurrenceService --> CreditCardService2 : recalculateAvailableLimit
    ProcessRecurrencesJob --> RecurrenceService : generateBufferInstances
    ApplyRecurrenceScopeChangeJob --> RecurrenceService : propagate/delete
```

### Data Model Change

```mermaid
erDiagram
    recurrences {
        uuid id PK
        uuid workspace_id FK
        uuid account_id FK "nullable"
        uuid credit_card_id FK "nullable, novo"
        uuid category_id FK
        string type
        string description
        decimal value
        string frequency
        int frequency_date
        date start_date
        date until_date
        date next_date
        string status
        int buffer_ahead
        uuid created_by FK
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    credit_card_bills {
        uuid id PK
        uuid credit_card_id FK
        uuid workspace_id FK
        int period_year
        int period_month
        date closing_date
        date due_date
        string status
        decimal total_amount
        timestamp created_at
    }

    recurrences ||--o{ transactions : "generates"
    credit_card_bills ||--o{ transactions : "groups"
    recurrences }o--o| credit_cards : "optional"
    recurrences }o--o| accounts : "optional"
```

### Decisões

| Decisão | Escolha | Racionale |
|---------|---------|-----------|
| `account_id` em recurrences | Torna nullable; `credit_card_id` adicionado também nullable | Mutual exclusivity via validation; dados existentes permanecem válidos |
| Ocorrências retroativas | Materializadas mesmo em faturas fechadas (não pagas) | Conforme spec AC5; faturas fechadas ≠ faturas pagas |
| Validação de colisão | Rejeita criação se ocorrência start_date→hoje cair em fatura paga | Preserva imutabilidade da fatura paga (D-79) |

---

## Domain 3: Bill Lifecycle — Pré-criação e Fechamento Uniforme

Duas mudanças no ciclo de vida das faturas:

1. **`CreditCardService::create()`** cria 13 faturas sincronamente na criação do cartão
2. **`PreCreateBillsJob`** (novo) roda diariamente mantendo o horizonte de 13 meses
3. **`CloseBillsJob`** (modificado) fecha **todas** as faturas com `closing_date < today`, incluindo vazias
4. Fallback on-demand no render da UI do cartão (CCXP-06 AC2)

```mermaid
stateDiagram-v2
    [*] --> Open: "criação - síncrona 13 faturas"

    Open --> Closed: "closing_date antes de hoje - job ou on-demand"
    Open --> Closed: "vazia ou com despesas - fechamento uniforme"

    Closed --> Paid: "payBill com conta + confirmação"
    Closed --> Open: "impossível - sem reopen direto"

    Paid --> Closed: "undoPayment"
    Paid --> [*]: "histórico"

    note right of Open
        Pré-criadas 13 meses à frente
        pelo PreCreateBillsJob diário
    end note

    note right of Paid
        paid_at em todas as transações
        da fatura - CCV2-03
    end note
```

```mermaid
graph LR
    subgraph Criacao ["Criação do Cartão"]
        A[CreditCardService::create] --> B[preCreateBills - 13 meses síncronos]
    end

    subgraph Jobs ["Job Diário"]
        C[PreCreateBillsJob] --> D{Garante 13 meses de horizonte}
        E[CloseBillsJob] --> F{Fecha todas as Open com closing_date antes de hoje}
        G[ProcessRecurrencesJob] --> H{Gera buffer de recorrências}
    end

    subgraph Fallback ["Fallback On-Demand"]
        I[Cards/Show render] --> J{Bill com closing_date antes de hoje e Open?}
        J -->|Sim| K[closeBill antes de render]
    end

    subgraph Buffer ["Buffer de Recorrência"]
        L[RecurrenceService::createWithBuffer] --> M{findOrCreateBill como garantia}
        M -->|fatura além do horizonte| N[Cria fatura sob demanda]
    end
```

### Decisões

| Decisão | Escolha | Racionale |
|---------|---------|-----------|
| Criação síncrona de 13 faturas | Na request de `store` do cartão | 13 INSERTs vazias não causam latência considerável |
| Fechamento de faturas vazias | Fecham uniformemente (não são mais descartadas) | Supersede D-32 (lazy); ciclo uniforme com faturas pré-criadas |
| Fallback on-demand | No render de `Cards/Show` | Implementa CCXP-06 AC2 (nunca implementada); resilia contra job atrasado |

---

## Domain 4: Form Requests — Validação Unificada

O `StoreTransactionRequest` e `UpdateTransactionRequest` passam a aceitar `credit_card_id` como alternativa a `account_id`, com validação de mutual exclusivity e campos condicionais.

```mermaid
flowchart TD
    A[StoreTransactionRequest] --> B{account_id XOR credit_card_id?}
    B -->|ambos ou nenhum| C[Rejeitar - validation error]
    B -->|apenas account_id| D[Validar conta - workspace, não arquivada]
    B -->|apenas credit_card_id| E[Validar cartão - workspace, não arquivada]
    E --> F{installments > 1?}
    F -->|Sim| G[total_value obrigatório]
    F -->|Não| H[value é o valor da compra]
    G --> I[category_id obrigatório]
    H --> I
    I --> J{is_recurring AND credit_card_id?}
    J -->|Sim| K[installments locked to 1]
    J -->|Não| L[installments 1 a 48 válido]
```

### Mapa de FormRequests

```
StoreTransactionRequest  → MODIFICADO: account_id XOR credit_card_id, installments, total_value
UpdateTransactionRequest → MODIFICADO: sometimes rules + scope (single/group)
StoreCardExpenseRequest  → REMOVIDO
UpdateCardExpenseRequest → REMOVIDO
```

### Decisões

| Decisão | Escolha | Racionale |
|---------|---------|-----------|
| Mutual exclusivity | Validação XOR no FormRequest | Regra de negócio (D-24) centralizada |
| Parcelas com recorrência | Rejeitadas: "Despesas recorrentes em cartão não podem ser parceladas" | D-78: recorrência em cartão sempre 1x |
| Remoção de CardExpenseRequests | Deletados com o silo | Toda a validação migra para TransactionRequest |

---

## Domain 5: Frontend — Formulário Unificado

O `Transactions/Create.tsx` e `Edit.tsx` são reestruturados com sub-components por tipo de despesa. O seletor "forma de pagamento" controla quais campos são exibidos.

```mermaid
graph TD
    subgraph Form ["Transactions/Create.tsx"]
        A[PaymentMethodSelector] -->|Conta| B[AccountFields]
        A -->|Cartão de crédito| C[CardExpenseFields]
        B --> D[SharedFields]
        C --> D
        D --> E[RecurrenceFields]
        D --> F[SubmitButton]
    end

    subgraph Subs ["Sub-components"]
        B --> |account_id| G[Select de contas ativas]
        C --> |credit_card_id| H[Select de cartões ativos]
        C --> |installments| I[Input 1 a 48, default 1]
        C --> |total_value| J[Input quando installments > 1]
        E --> |is_recurring| K[Frequência, data fim, buffer]
        E --> |locked se cartão| L[installments = 1]
    end
```

```mermaid
sequenceDiagram
    participant U as Usuário
    participant F as Transactions/Create
    participant S as PaymentMethodSelector
    participant C as CardExpenseFields
    participant API as TransactionController
    participant TS as TransactionService

    U->>F: Abre formulário
    F->>S: Renderiza - Conta como default
    U->>S: Seleciona Cartão de crédito
    S->>C: Revela CardExpenseFields
    C->>F: Mostra card select + installments
    U->>C: Seleciona cartão, installments=12
    C->>F: Troca Valor por Valor total
    U->>F: Submete
    F->>API: POST expenses com credit_card_id, total_value, installments
    API->>TS: createCardInstallment
    TS-->>API: 12 Transactions criadas
    API-->>F: Redirect para listagem
```

### Decisões

| Decisão | Escolha | Racionale |
|---------|---------|-----------|
| Sub-components por tipo | `AccountFields`, `CardExpenseFields`, `RecurrenceFields` | Separa responsabilidades; form principal orquesta |
| PaymentMethodSelector | Toggle Conta ↔ Cartão no topo do form | UX clara; campos aparecem/desaparecem conforme seleção |
| Redirect do cartão | Query string `?payment_method=card&card_id={uuid}` | Cartão pré-preenchido no form unificado |

---

## Domain 6: Frontend — Card Hub UI

O `Cards/Show.tsx` é redesenhado como hub único do crédito, com 4 cards de limite, seletor de faturas, despesas scopadas e pagamento inline.

```mermaid
graph TD
    subgraph Hub ["Cards/Show.tsx - Hub"]
        A[LimitCards] --> A1[Limite total]
        A --> A2[Consumido]
        A --> A3[Disponível]
        A --> A4[Total da fatura em vista]

        B[InvoiceSelector] -->|estilo month-picker| B1[Lista faturas - passado, atual, futuro]
        B -->|default é ciclo atual| B2[Fatura selecionada]

        C[InvoiceDetails] --> C1[Período MM/YYYY]
        C --> C2[Closing date]
        C --> C3[Due date]
        C --> C4[Status]

        D[InvoiceExpenses] --> D1[Lista de despesas da fatura]
        D1 --> D2[Descrição, valor, data, category, tags, installment badge]

        E[InlineBillAction] -->|Closed| E1[Marcar fatura como paga]
        E -->|Open| E2[Hint - aguardando fechamento]
        E -->|Paid| E3[Info pagamento + Desfazer]

        F[NewExpenseButton] --> F1[Redirect para Transactions/Create com card_id]
    end
```

```mermaid
stateDiagram-v2
    direction LR
    [*] --> SelectingBill

    SelectingBill --> ViewingExpenses: seleciona fatura
    ViewingExpenses --> PayingBill: clica Marcar como paga -- se Closed
    SelectingBill --> CreatingExpense: clica Nova despesa neste cartão

    PayingBill --> ConfirmingAccount: pede conta + confirma valor
    ConfirmingAccount --> BillPaid: confirma
    BillPaid --> [*]: redirect para show atualizado

    CreatingExpense --> ExpenseForm: redirect com card_id
    ExpenseForm --> ViewingExpenses: redirect de volta após criar
```

### Decisões

| Decisão | Escolha | Racionale |
|---------|---------|-----------|
| Cards de limite | Globais (não por fatura) | Conforme decisão do usuário; `available_limit` é propriedade do cartão |
| Seletor de faturas | Estilo month-picker de `Transactions/Index` | Consistência visual; referência explícita do usuário |
| Pagamento inline | Botão refere-se à fatura sendo vista | Decisão do usuário: "marcar fatura como paga" = fatura selecionada |

---

## Domain 7: Frontend — DataTable com Filtros de Cartão

A `Transactions/Index.tsx` ganha coluna "Cartão" e filtros por cartão/fatura. O `Recurrences/Index.tsx` ganha coluna "Cartão" quando aplicável.

```mermaid
graph LR
    subgraph DT ["Transactions/Index.tsx"]
        A[DataTable] --> B[Colunas existentes]
        A --> C[Coluna Cartão - novo]
        D[Filters] --> E[Filtros existentes]
        D --> F[Filtro por Cartão - novo]
        D --> G[Filtro por Fatura - novo]
    end

    subgraph BillOver ["Filtro Bill sobrepõe Month"]
        H[Bill filter ativo] --> I[Ignora month scoping]
        I --> J[Mostra todas as despesas da fatura]
    end

    subgraph Controller ["TransactionController - datatable"]
        K[Query] --> L[with creditCard e bill]
        K --> M[Filter por credit_card_id]
        K --> N[Filter por credit_card_bill_id]
    end
```

```mermaid
graph TD
    subgraph DT2 ["Transactions/Index.tsx"]
        Cols["Colunas: Descrição, Valor, Data, Conta, Cartão, Categoria, Tags, Status, Ações"]
        Filtros["Filtros: Descrição, Valor, Data, Categoria, Conta, Cartão, Fatura, Status"]
    end

    subgraph StatusCol ["Status column for card expenses"]
        S1["Na fatura - Aberta"]
        S2["Na fatura - Fechada"]
        S3["Paga"]
    end

    subgraph Rec ["Recurrences/Index.tsx"]
        RCols["Colunas: Descrição, Tipo, Valor, Próxima, Status, Conta ou Cartão, Categoria, Ações"]
    end
```

### Decisões

| Decisão | Escolha | Racionale |
|---------|---------|-----------|
| Coluna "Cartão" | Mostra nome do cartão; vazio para despesas em conta | Visibilidade do vínculo sem navegação extra |
| Filtro fatura sobrepõe mês | Quando ativo, mostra todas as despesas da fatura independente do mês selecionado | Um ciclo de fatura cruza meses (closing_date) |
| Status de despesa de cartão | Derivado da fatura: "Na fatura (Aberta/Fechada)" ou "Paga" | Botão Pagar oculto; status reflete realidade da fatura |

---

## Domain 8: Decommission — Remoção do Silo CardExpenses

Todo o fluxo dedicado de `CardExpenses` é removido atomicamente. Nenhum período de transição — o projeto ainda não está em produção.

```mermaid
graph TD
    subgraph Removido ["REMOVIDO"]
        A[CardExpenseController] -->|cards/uuid/expenses/*| Z[404 - rotas removidas]
        B[CardExpenseService] -->|fundido em| F[TransactionService]
        C[StoreCardExpenseRequest] -->|substituído por| G[StoreTransactionRequest]
        D[UpdateCardExpenseRequest] -->|substituído por| H[UpdateTransactionRequest]
        E[CardExpenses/Create.tsx] -->|substituído por| I[Transactions/Create.tsx + card_id query]
        E2[CardExpenses/Edit.tsx] -->|substituído por| I2[Transactions/Edit.tsx]
        J[CardExpenseServiceTest tests] -->|reescritos para| K[TransactionService - fluxo unificado]
    end

    subgraph Mantido ["MANTIDO"]
        L[CreditCardController] --> L1[index, show, create, store, edit, update, destroy]
        M[CreditCardBillController] --> M1[show, pay, unpay]
        N[Bills/Show.tsx] --> N1[detalhe/histórico de fatura]
    end
```

### Decisões

| Decisão | Escolha | Racionale |
|---------|---------|-----------|
| Atomicidade | Tudo removido num único conjunto de commits | Projeto não está em produção; coexistência traria complexidade desnecessária |
| Legado `Bills/Show` | Mantido | Serve como detalhe/histórico de faturas fechadas e pagas |
| Testes de CardExpenses | Reescritos para o fluxo unificado | Cobrem os mesmos cenários via `Transactions/Create` com cartão selecionado |

---

## Migration Summary

| Migration | Type | Impact |
|-----------|------|--------|
| Add `credit_card_id` to `recurrences` | ALTER (nullable FK) | Baixo — novas recorrências de cartão; existentes em conta inalteradas |
| Make `account_id` nullable in `recurrences` | ALTER | Baixo — existente NOT NULL rows permanecem válidos |
| Nenhum change em `credit_card_bills` | — | — |
| Nenhum change em `credit_cards` | — | — |
| Nenhum change em `transactions` | — | Colunas `credit_card_id` e `credit_card_bill_id` já existem |

---

## Error Handling Strategy

| Error Scenario | Handling | User Impact |
|---|---|---|
| Pay card expense via `TransactionService::pay()` | Reject with 422 + "Despesas de cartão são pagadas através da fatura do cartão" | Botão Pagar oculto na UI; API protegida |
| Both `account_id` + `credit_card_id` submitted | FormRequest validation error | Mensagem: "Uma transação deve ter conta OU cartão, nunca ambos" |
| Card recurrence with installments > 1 | FormRequest validation error | Mensagem: "Despesas recorrentes em cartão não podem ser parceladas" |
| Recurrence buffer hits paid bill | Reject creation | Mensagem: "A data de início colide com faturas já pagas do cartão" |
| Bill with total=0 paid | Reject | Mensagem: "Esta fatura não possui despesas" |
| Edit/delete expense on paid bill | Blocked (existing) | Mensagem de imutabilidade existente |
| Payment transaction deleted from list | Blocked (existing) | Mensagem: "Esta transação foi gerada pelo pagamento de uma fatura..." |

---

## Quality Gates (per task)

- `composer quality` — Pint format check + PHPMD complexity
- `npm run quality` — ESLint complexity + Prettier check
- PHPUnit: feature tests (one per acceptance criteria) + smoke tests (one per GET route)
- Cypress E2E: critical journeys (create card → create expense → pay bill → undo)
