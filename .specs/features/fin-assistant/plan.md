# Fin — Assistente Financeiro Geral por IA no Workspace (Plano)

> Feature: `CHAT-01` (Chat IA Conversacional) expandido + guardrails + mutation tools.
> Fonte: issue "Assistente Financeiro Geral por IA ('Fin') no Workspace".

## 1. Objetivo

Entregar um assistente de chat conversacional ("Fin") dentro do workspace que:

1. Responda perguntas sobre a saúde financeira do workspace usando *tool calls* de leitura (receitas, despesas, recorrências, contas, categorias, tags, cartões).
2. Execute cadastros assistidos (receita/despesa) em **2 etapas**: extração + checagem de duplicidade → resumo → confirmação explícita do usuário → persistência.
3. Rode de forma **assíncrona** (Queue Worker + polling, sem WebSockets/SSE), com UI que bloqueia o input durante o processamento.
4. Aplique **guardrails de escopo** (recusa de assuntos fora de finanças/plataforma) e **isolamento rígido por workspace**.

## 2. Estado atual (o que já existe)

| Item | Status |
| --- | --- |
| Laravel AI SDK (`laravel/ai` ^1.2) com agents + tools | ✅ já em uso |
| `config/ai.php` provider `opencode` (driver `anthropic`) | ✅ existente (modelo atual: `claude-haiku-5-5`) |
| `BaseAgent` + `QueryTransactionsTool` / `QueryCategoriesTool` / `QueryRecurrencesTool` / `QueryTagsTool` | ✅ existentes (injetam `workspace_id` no construtor) |
| Memory tools (`CreateMemoryTool`, etc.) + `AgentMemoryService` | ✅ existentes |
| Tabelas `agent_conversations` / `agent_conversation_messages` (migration `2026_10_15_141332`) | ✅ já migradas (coluna `title` presente) |
| `RemembersConversations` + `maxConversationMessages()` (janela de contexto) | ✅ SDK (default 100, sobrescrevível) |
| `Conversation`/`ConversationMessage` models + `ConversationStore` | ✅ SDK |
| Jobs `ShouldQueue` (padrão `ProcessRecurrencesJob`, `CloseBillsJob`, etc.) | ✅ padrão estabelecido |
| Polling no frontend (`use-notification-polling.ts`, `setInterval`) | ✅ padrão estabelecido |
| `QUEUE_CONNECTION=database` | ✅ configurado |

**Não existe ainda**: modelo/serviço/controller/UI de chat, job de resposta do Fin, titulação, mutation tools, detecção fuzzy de duplicidade em código (regra D-69 só existe em design), e as *read tools* de Contas/Cartões.

## 3. Decisões de arquitetura (novas)

| ID | Decisão |
| --- | --- |
| **D-73** | **Conversas escopadas por workspace**: `Workspace` é o `participant` (morph) das conversas, usando as tabelas `agent_conversations`/`agent_conversation_messages` do SDK. Listagem/CRUD sempre filtradas por `participant_type=Workspace` + `participant_id=$workspace->id`. Atribuição de `created_by` para auditoria. *(Alternativa sinalizada: isolamento por usuário dentro do workspace — fora do escopo v1.)* |
| **D-74** | **Assíncrono via `DispatchFinAssistantJob` (ShouldQueue) + polling** — sem WebSockets e sem SSE. **Supersede** o AC-3 do spec (`SSE`) por decisão do issue. Resposta gerada em background; frontend consulta status periodicamente (~2s). |
| **D-75** | **Rastreamento de status via model `FinAssistantRun`** (nova tabela) com enum `FinRunStatus` (`pending`/`processing`/`completed`/`failed`). O `ConversationMessage` do assistant só é criado ao concluir. Motivo: o enum do SDK (`MessageStatus`) só tem `completed`/`failed`/`paused` — não há estado "pendente". Endpoint de polling retorna o status do run. |
| **D-76** | **Janela de contexto** via override de `maxConversationMessages()` no agente (default `20`, configurável), limitando as últimas N mensagens enviadas à LLM. |
| **D-77** | **Titulação automática** na 1ª mensagem via `TitleConversationJob` usando modelo leve `deepseek-v4.1-flash`; grava em `agent_conversations.title`. |
| **D-78** | **Read tools injetam `workspace_id` no servidor** (via construtor, igual aos `Query*` existentes) — nunca a partir do prompt/IA. Adicionar `QueryAccountsTool` e `QueryCreditCardsTool`. |
| **D-79** | **Mutation em 2 etapas**: `ValidateTransactionTool` extrai dados + roda checagem de duplicidade + persiste `FinPendingMutation` (escopada ao workspace, com expiração); agente **sempre** apresenta resumo + alertas e aguarda confirmação explícita; `ConfirmTransactionTool` (aceita só `pending_id`, re-escopa ao workspace, persiste via `TransactionService`). **Nenhuma mutação sem confirmação.** |
| **D-80** | **`DuplicateDetectionService` compartilhado** com a regra fuzzy (valor ±0.01, data ±1 dia, `similar_text` ≥ 80% na descrição) — reusável pela importação no futuro. |
| **D-81** | **Guardrails em duas camadas**: (1) instruções de sistema rejeitando assuntos fora de finanças/plataforma (best-effort); (2) enforcement estrutural no nível de tool/persistência (escopo workspace obrigatório, campos whitelisted, sem SQL arbitrário). |
| **D-82** | **Modelos**: `config/ai.php` passa a expor modelo principal do assistente + modelo leve de titulação. ⚠️ *Discrepância a confirmar*: config atual usa `claude-haiku-5-5`; o issue e o ambiente indicam `deepseek-v4-pro` (principal) e `deepseek-v4.1-flash` (titulação). Recomenda-se `FIN_MODEL=deepseek-v4-pro` e `FIN_TITLE_MODEL=deepseek-v4.1-flash`. |

## 4. Arquitetura — fluxo assíncrono

```
Usuário envia mensagem
  │  POST /w/{workspace}/fin/conversations/{conversation}/messages
  ▼
FinConversationController / FinMessageController
  ├─ cria/atualiza Conversation (participant = Workspace)
  ├─ persiste ConversationMessage (role=user, status=completed)
  ├─ cria FinAssistantRun (status=pending)
  ├─ se 1ª mensagem → dispatch TitleConversationJob (deepseek-v4.1-flash)
  └─ dispatch DispatchFinAssistantJob(runId)
        │
        ▼ (Queue Worker)
  DispatchFinAssistantJob
    ├─ run.status = processing
    ├─ FinancialAssistantAgent (continue(conversationId, workspace))
    │     ├─ system prompt (guardrails)
    │     ├─ histórico = últimas N mensagens (D-76)
    │     ├─ read tools  (QueryTransactions/Categories/Recurrences/Tags/Accounts/CreditCards)  [workspace_id server-side]
    │     └─ mutation tools (ValidateTransactionTool → FinPendingMutation; ConfirmTransactionTool → TransactionService)
    ├─ persiste ConversationMessage (role=assistant, status=completed)
    └─ run.status = completed  |  em erro → run.status = failed + error_message

Frontend (polling ~2s)
  └─ GET /w/{workspace}/fin/runs/{run} → {status}
       └─ completed → refetch messages e renderiza; failed → toast de erro
```

## 5. Modelo de dados (mudanças)

- **Nova migração** `create_fin_assistant_runs_table`:
  - `fin_assistant_runs`: `id (uuid)`, `workspace_id`, `conversation_id`, `user_id`, `status` (FinRunStatus), `error_message` (nullable text), `result_message_id` (nullable), `timestamps`.
- **Nova migração** `create_fin_pending_mutations_table`:
  - `fin_pending_mutations`: `id (uuid)`, `workspace_id`, `conversation_id`, `user_id`, `type` (TransactionType income/expense), `payload` (json — dados extraídos), `status` (pending/confirmed/expired/cancelled), `expires_at`, `timestamps`.
- **Enums novos**: `FinRunStatus`, `FinPendingMutationStatus` (em `app/Enums/`).
- **Models novos**: `FinAssistantRun`, `FinPendingMutation`.
- Tabelas de conversa já existem (sem nova migração).

## 6. Componentes

### Backend (`app/`)

| Camada | Arquivos |
| --- | --- |
| Agents | `Ai/Agents/FinancialAssistantAgent.php` (extends `BaseAgent`, implements `Conversational`), `Ai/Agents/FinTitleAgent.php` |
| Tools (read) | `Ai/Tools/QueryAccountsTool.php`, `Ai/Tools/QueryCreditCardsTool.php` |
| Tools (mutation) | `Ai/Tools/ValidateTransactionTool.php`, `Ai/Tools/ConfirmTransactionTool.php` |
| Services | `Services/FinAssistantService.php`, `Services/FinTitleService.php` (ou dentro do anterior), `Services/DuplicateDetectionService.php` |
| Jobs | `Jobs/DispatchFinAssistantJob.php`, `Jobs/TitleConversationJob.php` |
| Controllers | `Http/Controllers/FinController.php` (index Inertia), `FinConversationController.php` (index/store/show/update/destroy), `FinMessageController.php` (store), `FinRunController.php` (show/status) |
| Requests | `Http/Requests/StoreFinMessageRequest.php`, `StoreFinConversationRequest.php`, `UpdateFinConversationRequest.php` |
| Policies | `Policies/FinConversationPolicy.php` (membership do workspace) |
| Resources | `Http/Resources/FinConversationResource.php`, `FinMessageResource.php`, `FinRunResource.php` |
| Models/Enums | `Models/FinAssistantRun.php`, `Models/FinPendingMutation.php`, `Enums/FinRunStatus.php`, `Enums/FinPendingMutationStatus.php` |
| Config | `config/ai.php` (modelos principal + titulação) |
| Routes | `routes/web.php` (grupo `/w/{workspace}`) |

### Frontend (`resources/js/`)

| Camada | Arquivos |
| --- | --- |
| Page | `Pages/Fin/Index.tsx` (layout split 33% / 67%) |
| Components | `Components/Fin/ConversationSidebar.tsx`, `ChatArea.tsx`, `MessageList.tsx`, `MessageBubble.tsx`, `ChatInput.tsx`, `NewConversationButton.tsx`, `RenameConversationDialog.tsx`, `DeleteConversationDialog.tsx` |
| Hook | `hooks/use-fin-polling.ts` (polling ~2s do run status) |
| Types | `types/fin.ts` (`FinConversation`, `FinMessage`, `FinRun`, `FinPageProps`) |
| shadcn/ui | adicionar `textarea` e `scroll-area` (e `alert-dialog` para delete) via CLI |
| Sidebar | `Components/AppSidebar.tsx` — item "Fin" com ícone `Bot` |

## 7. Estratégia de testes (TDD-first)

### PHPUnit (feature tests, `tests/Feature/Fin/` + `tests/Feature/Ai/`)

1. **Isolamento de workspace**: tool read tenta ler dados de outro workspace → vazio/recusa; run/conversation de workspace A inacessível em workspace B.
2. **Duplicidade**: `ValidateTransactionTool` retorna alerta para lançamento idêntico (±0.01, ±1 dia, ≥80% similar).
3. **Titulação**: 1ª mensagem dispara `TitleConversationJob` (assert dispatch + `assertDatabaseHas('agent_conversations', ['title' => ...])`).
4. **Janela de contexto**: payload enviado à LLM contém só as últimas N mensagens (teste via fake/assert do agente).
5. **Fluxo 2 etapas**: validação NÃO persiste; confirmação persiste; confirmação de `pending_id` de outro workspace → recusa.
6. **Guardrail estrutural**: mutation tool não persiste sem confirmação; campos não-whitelisted rejeitados.
7. **CRUD conversas**: listar/renomear/excluir escopados ao workspace.
8. **Smoke test**: `FinSmokeTest` — `assertOk` + `assertInertia('Fin/Index')`.

### Cypress (`cypress/e2e/fin.cy.js`)

1. Smoke: página renderiza sem crash.
2. Navegação: alternar entre conversas na sidebar 33% + iniciar novo chat.
3. Bloqueio de input: enviar mensagem → input fica `disabled` até o polling concluir.
4. Fluxo completo: cadastro de despesa via chatbox com confirmação.
5. Toasts Sonner: erro no polling e ao excluir conversa.

## 8. Tarefas (ordem, atômicas, TDD-first)

**Fase A — Fundações**
- **T1**: config `ai.php` (modelos) + enums + migrações (`fin_assistant_runs`, `fin_pending_mutations`) + `DuplicateDetectionService` (+ testes). *Gate: `composer quality` + `php artisan test`.*

**Fase B — Read tools + agente**
- **T2**: `QueryAccountsTool` + `QueryCreditCardsTool` (+ testes de isolamento).
- **T3**: `FinancialAssistantAgent` (guardrails no system prompt, read tools, override `maxConversationMessages`) (+ testes fake).

**Fase C — Mutation tools**
- **T4**: `ValidateTransactionTool` + `FinPendingMutation` (+ testes de duplicidade/escopo).
- **T5**: `ConfirmTransactionTool` (persiste via `TransactionService`, re-escopo) (+ testes).

**Fase D — Orquestração backend**
- **T6**: `FinAssistantService` + `DispatchFinAssistantJob` + `TitleConversationJob` (+ testes de dispatch/titulação/contexto).
- **T7**: Controllers + FormRequests + Policies + Resources + rotas + smoke test.

**Fase E — Frontend**
- **T8**: types + `Fin/Index.tsx` + componentes (sidebar 33%, chat 67%, input) + item na `AppSidebar` + shadcn `textarea`/`scroll-area`.
- **T9**: `use-fin-polling` + integração do status + input `disabled` + toasts Sonner.

**Fase F — E2E + qualidade**
- **T10**: Cypress `fin.cy.js` + gates finais (`composer quality`, `npm run quality`, `npm run build`).

## 9. Pontos abertos a confirmar (antes de implementar)

1. **Modelos/nomes**: confirmar `FIN_MODEL` e `FIN_TITLE_MODEL` reais no endpoint `opencode` (recomendado: `deepseek-v4-pro` / `deepseek-v4.1-flash`). A config atual aponta `claude-haiku-5-5`.
2. **Escopo da conversa**: workspace-wide (recomendado) vs. por-usuário-dentro-do-workspace.
3. **Valor de N** da janela de contexto (recomendado: 20).
4. **SSE vs polling**: este plano assume polling (conforme issue); confirma a remoção definitiva do streaming (AC-3 do spec original).
5. **TTL das `FinPendingMutation`**: tempo de expiração (recomendado: 30 min).
