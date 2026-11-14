# Fin Memory Integration — Plan

Integração do Sistema de Memórias no Assistente Fin (issue: [Feature/Refactor] Integração do Sistema de Memórias do Usuário no Assistente Fin).

## Estado Atual (auditoria)

O PR #52 (issue #51) já implementou a base da integração:

| Item do issue | Status atual |
|---|---|
| Mapeamento & Refatoração de Serviço (módulo compartilhado) | ✅ `AgentMemoryService` compartilhado; `BaseAgent` injeta as 4 tools de memória em todos os agentes (`app/Ai/Agents/BaseAgent.php`) |
| Injeção de Contexto no Fin (leitura) | ✅ `FinancialAssistantAgent::instructions()` inclui bloco `## Memória` com `{$this->memoryMap()}` |
| Registro/Atualização de Memórias pelo Fin (tools) | ✅ `CreateMemoryTool`, `ReadMemoryTool`, `UpdateMemoryTool`, `DeleteMemoryTool` disponíveis ao Fin (12 tools total) |
| Isolamento Multi-tenant | ✅ Tools recebem `workspaceUuid` e resolvem via `Workspace::where('uuid')`; service escopo via `$workspace->agentMemories()` |
| Limite de tokens do bloco de memórias | ❌ `formatForPrompt()` serializa TODAS as memórias ativas, sem limite — `app/Services/AgentMemoryService.php:40` |
| Guardrails de escrita no prompt do Fin | ⚠️ Parcial — escopo financeiro existe nas instruções gerais, mas o bloco `## Memória` não orienta sobre QUÊ/COMO registrar (metas, preferências, evitar duplicatas) |
| Testes de integração Fin↔Insights | ❌ Não existem testes no nível do Fin provando injeção/isolamento/escrita cruzada |
| E2E Cypress de memória entre conversas | ❌ Não existe |

## Decisões

- **Escopo de memória = workspace** (mantém-se). As memórias são workspace-scoped (`agent_memories.workspace_id`, sem `user_id`) — requisito 1 do issue exige que ambos os agentes compartilhem a MESMA base; adicionar `user_id` fragmentaria o conhecimento compartilhado. O isolamento auditável ("não vazar para outro workspace_id") é satisfeito pelo escopo atual.
- **Limite de memórias no prompt via config compartilhada**: `config/ai.php` → `'memories' => ['prompt_limit' => env('AI_MEMORY_PROMPT_LIMIT', 20), 'content_max_chars' => env('AI_MEMORY_CONTENT_MAX_CHARS', 400)]`. Aplicado em `AgentMemoryService::formatForPrompt()` (usado por ambos os agentes). `list()` é `latest()`, então o `take()` mantém as 20 mais recentes.
- **Guardrails do Fin = prompt-level** (mesma travas do Insights, que também é prompt-level). Orientar uso de `create_memory`/`update_memory`, evitar duplicatas, e proibir registro de assuntos fora do escopo financeiro/plataforma.
- **Sem mudança de schema, sem novos endpoints, sem mudança no frontend** — a integração é backend-only + testes.

## Tasks (TDD-first, ordem sequencial)

### T1 — Limite de tokens no mapa de memórias (service)
Arquivos: `config/ai.php`, `app/Services/AgentMemoryService.php`, `tests/Feature/Ai/AgentMemoryServiceTest.php`
- Testes novos (red primeiro):
  - `formatForPrompt` respeita o limite de 20 memórias (criar 25; assert 20 entradas; as mais recentes são mantidas).
  - `formatForPrompt` trunca conteúdo longo (`content_max_chars`).
  - Config default presente (`ai.memories.prompt_limit` = 20).
- Implementação: `->take((int) config('ai.memories.prompt_limit', 20))` + `Str::limit` no title/content.
- Gate: `php artisan test --filter=AgentMemoryServiceTest` + `composer quality`.

### T2 — Guardrails de memória no prompt do Fin
Arquivos: `app/Ai/Agents/FinancialAssistantAgent.php`, `tests/Feature/Fin/FinancialAssistantAgentTest.php`
- Testes novos (red primeiro):
  - Instruções contêm `## Memória` e o título/conteúdo de uma memória do workspace (prova de injeção no nível do agente Fin).
  - Instruções contêm orientação de escrita: `create_memory`, `update_memory`, "duplicata".
  - Instruções contêm guardrail de escopo para memórias (só registrar conteúdo com relevância financeira/plataforma).
- Implementação: expandir o bloco `## Memória` do heredoc (mantendo `{$this->memoryMap()}` — `BaseAgentTest` depende dele).
- Gate: `php artisan test --filter="FinancialAssistantAgentTest|BaseAgentTest"` + `composer quality`.

### T3 — Testes de integração cruzada Insights↔Fin (arquivo novo)
Arquivo: `tests/Feature/Ai/AgentMemoryCrossAgentTest.php`
- `test_memory_created_by_insights_agent_is_injected_into_fin_context`: memória criada via `AgentMemoryService` com `source = ScheduledFinancialAnalysisAgent::class` → instruções do `FinancialAssistantAgent` contêm título/conteúdo.
- `test_memory_created_by_fin_tool_is_available_to_insights_agent`: `CreateMemoryTool` handle (padrão do `MemoryToolTest`) → instruções do `ScheduledFinancialAnalysisAgent` contêm a memória.
- `test_fin_context_does_not_leak_memories_from_other_workspaces`: memória no workspace B → instruções do Fin do workspace A NÃO contêm; do workspace B contêm.
- `test_fin_instructions_respect_memory_prompt_limit`: 25 memórias → instruções do Fin contêm a mais recente e não contêm a mais antiga.
- Estes testes devem passar com T1+T2 aplicados (tarefa de cobertura).

### T4 — Cypress E2E: memória entre conversas
Arquivo: `cypress/e2e/fin-memory.cy.js`
- Smoke: página Fin renderiza (padrão do `fin.cy.js`).
- Cenário: conversa 1 — usuário declara preferência ("Prefiro registrar delivery como Lazer"); aguardar run completar. `POST testing/financial-analysis` com insight que registra a preferência como memória real. Iniciar "Nova conversa", perguntar sobre categorização de delivery, resposta scriptada referenciando "Lazer"; assert renderizado. Cleanup via `cy.finCleanup`.
- Seguir helpers/padrões existentes de `cypress/e2e/fin.cy.js` (`cy.login`, `cy.finResponse`, `cy.finCleanup`, seletores).
- A validação real da injeção server-side é coberta pelos testes PHPUnit (T2/T3); o E2E valida a jornada de UX ponta a ponta.

### T5 — Verificação final
- `php artisan test` (suite completa)
- `composer quality` + `npm run quality`
- `npm run build`

## Definition of Done (do issue) — mapeamento
1. Padrões técnicos ✅ (sem frontend novo; backend segue Service/config/TDD)
2. Isolamento de dados ✅ T3 (teste multi-tenant)
3. Toasts — N/A (nenhuma mutação/alerta de UI neste escopo)
4. `composer quality` ✅ T5
5. Testes backend + E2E ✅ T1–T4
