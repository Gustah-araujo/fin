# Fin — Melhorias no Agente Fin (Plano)

> Feature: `CHAT-01` (Chat IA Conversacional) — melhorias de UX e prompt.
> Fonte: issue "Melhorias agente Fin".

## 1. Objetivo

Aplicar quatro melhorias ao assistente "Fin":

1. **Personalidade + contexto padrão no system prompt** — sempre responder de maneira amigável como assistente financeiro da plataforma, e injetar um pacote de informações padrão (usuário logado, data atual, workspace atual).
2. **Respostas em Markdown renderizadas** — instruir a LLM a responder sempre em Markdown e converter a resposta para HTML no frontend, renderizando uma versão bonita no chatbox.
3. **Correção do título da conversa na sidebar** — ao iniciar uma nova conversa, o título não atualiza até um refresh; deve atualizar automaticamente.
4. **Ajustar testes** — feature (PHPUnit) e E2E (Cypress) para cobrir as mudanças.

## 2. Estado atual

- `FinancialAssistantAgent` (`app/Ai/Agents/FinancialAssistantAgent.php`) — system prompt em heredoc, com guardrails, mas **sem** seção de tom amigável, **sem** instrução de Markdown e **sem** bloco de contexto (usuário/data/workspace). Recebe `int $userId`, não o `User`.
- `FinAssistantService::run()` cria o agente com `$run->user_id` (linha 88).
- `MessageBubble.tsx` renderiza `message.content` como **texto puro** (`whitespace-pre-wrap`), sem conversão Markdown/HTML.
- `Pages/Fin/Index.tsx` — `handlePollingCompleted` (linhas 221-227) só recarrega `messages`; nunca re-sincroniza o `title` na sidebar. `handleCreate` insere a conversa com `"Nova conversa"`; `handleRename` é o único lugar que atualiza título.
- Título é gerado assíncrono via `TitleConversationJob` (modelo leve), disparado na 1ª mensagem.
- `package.json` **não** tem biblioteca de Markdown; `composer.json` **não** usa `league/commonmark` (só transitivo).

## 3. Decisões (novas)

| ID | Decisão |
| --- | --- |
| **D-83** | **Renderização Markdown no client** com `react-markdown` + `remark-gfm` (GFM para tabelas/strikethrough). O `content` persiste como Markdown puro no banco; a conversão para HTML acontece no React. `react-markdown` é XSS-safe por padrão (não renderiza HTML cru) — sem `dangerouslySetInnerHTML`. Mensagens do **usuário** continuam texto puro; só mensagens do **assistente** são renderizadas em Markdown. |
| **D-84** | **System prompt** ganha 3 seções: `## Tom` (amigável/acolhedor, assistente financeiro da plataforma), `## Formato de resposta` (responder **sempre** em Markdown) e `## Contexto atual` (usuário logado = `name`, data atual = `now()->format('d/m/Y')`, workspace atual = `name`). O `FinancialAssistantAgent` passa a receber o **modelo `User`** no construtor (no lugar de `int $userId`); `FinAssistantService::run()` resolve `User::findOrFail($run->user_id)`. |
| **D-85** | **Sincronização de título na sidebar** no frontend: (1) `loadConversation` passa a atualizar o `title` da conversa ativa no estado `conversations`; (2) ao concluir o run (`handlePollingCompleted`/`handlePollingFailed`), re-fetch da lista (`fin.conversations.index`) para refletir títulos gerados. *Hardening* contra a corrida assíncrona do `TitleConversationJob` (produção): re-poll limitado (até ~3 tentativas, ~1.5s) enquanto o título da ativa ainda for `"Nova conversa"`. |

## 4. Mudanças detalhadas

### 4.1 Backend

**`app/Ai/Agents/FinancialAssistantAgent.php`**
- Construtor: `private readonly User $user` (substitui `int $userId`); `agentTools()` usa `$this->user->id`.
- Novo método `contextBlock(): string`:
  ```php
  ## Contexto atual
  - Usuário logado: {$this->user->name}
  - Data atual: {now()->format('d/m/Y')}
  - Workspace atual: {$this->workspace->name}
  ```
- `instructions()`: adicionar seções `## Tom`, `## Formato de resposta` e `{$this->contextBlock()}`.

**`app/Services/FinAssistantService.php`**
- `run()`: `$user = User::findOrFail($run->user_id);` e `new FinancialAssistantAgent($workspace, $conversation->id, $user);`.

### 4.2 Frontend

**`package.json`** — adicionar `react-markdown` (^10) e `remark-gfm` (^4).

**`resources/js/Components/Fin/MarkdownContent.tsx` (novo)**
- `<ReactMarkdown remarkPlugins={[remarkGfm]} components={...}>` com mapa de componentes estilizados conforme o design system (headings, `p`, `strong`, `em`, `ul/ol/li`, `code` inline, `pre`, `blockquote`, `table/thead/tbody/tr/th/td`, `a`, `hr`). Usar tokens semânticos (`text-foreground`, `bg-sidebar`/`bg-muted-foreground/10` para code, `border` para blockquote/tabela, `text-primary` para links), **sem** cores hardcoded.

**`resources/js/Components/Fin/MessageBubble.tsx`**
- Assistente → `<MarkdownContent content={message.content} />` (remove `whitespace-pre-wrap` para o assistente); usuário → mantém texto puro `whitespace-pre-wrap`. Ajustar largura/overflow para acomodar tabelas/código (`overflow-x-auto` em `pre`, `max-w-[85%]` se necessário).

**`resources/js/Pages/Fin/Index.tsx`**
- `loadConversation`: além de `setMessages`, sincronizar `setConversations(prev => prev.map(c => c.id === id ? { ...c, title: data.title } : c))`.
- Novo `loadConversations()` (GET `fin.conversations.index`) e chamada em `handlePollingCompleted`/`handlePollingFailed` (após `loadConversation`).
- *Hardening (D-85)*: se o título da ativa ainda for `"Nova conversa"` após o re-fetch, re-poll limitado.

**`routes/web.php` (endpoint de teste, non-prod)**
- `testing/fin-response`: aceitar `conversation_id` + `title` opcionais para atualizar o título da conversa ao completar o run pendente (permite testar a sincronização de título no Cypress).

## 5. Estratégia de testes (TDD-first)

### PHPUnit (`tests/Feature/Fin/`)
- `FinancialAssistantAgentTest`:
  - Atualizar os 3 construtores para `new FinancialAssistantAgent($this->workspace, 'conv-test-id', $this->user)`.
  - `test_agent_instructions_contain_scope_guardrails`: acrescentar asserts de tom amigável, Markdown e `Contexto atual`.
  - Novo `test_agent_instructions_contain_context_info`: `Carbon::setTestNow('2026-11-11 10:00:00')` e assert `$this->user->name`, `$this->workspace->name` e `11/11/2026` presentes nas instruções.
- `FinAssistantServiceTest`: verificar que os testes existentes continuam verdes (o `run()` agora resolve o `User`; sem mudança de assinatura pública do serviço).

### Cypress (`cypress/e2e/fin.cy.js`)
- **Markdown**: resposta seedada com Markdown (ex.: `**saldo**` + lista) renderiza elementos formatados (ex.: `<strong>` presente).
- **Título da sidebar**: criar conversa → enviar mensagem → `finResponse({ response, title: 'Meu saldo' })` → sidebar exibe `Meu saldo` **sem** refresh da página.

## 6. Tarefas (ordem, atômicas, TDD-first)

**T1 — Backend: prompt (tom + Markdown + contexto)**
- Testes primeiro (vermelho) → implementação do agente + `FinAssistantService::run()` → verde.
- Gate: `composer quality` + `php artisan test --filter FinancialAssistantAgentTest`.

**T2 — Frontend: renderização Markdown**
- Adicionar deps + `MarkdownContent.tsx` + `MessageBubble.tsx`.
- Gate: `npm run quality` + `npm run build`.

**T3 — Frontend: correção do título da sidebar**
- `Index.tsx` (sincronização de título + re-fetch da lista + hardening).
- Gate: `npm run quality` + `npm run build`.

**T4 — E2E + endpoint de teste**
- `testing/fin-response` (title/conversation_id) + `fin.cy.js` (Markdown + título).
- Gate: Cypress `fin.cy.js` verde.

**T5 — Qualidade final + memória**
- `composer quality`, `npm run quality`, `npm run build`, `php artisan test`.
- Atualizar `STATE.md` (decisões D-83/D-84/D-85 + log de sessão) e status do `CHAT-01` no `ROADMAP.md` (se aplicável).
