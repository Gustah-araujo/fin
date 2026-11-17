# Bugfix: Persistir Anexos Enviados nas Mensagens Anteriores do Histórico do Fin

## Causa raiz

`FinAssistantService::hydrateAttachments()` (app/Services/FinAssistantService.php:219) reidrata anexos **apenas da última mensagem do usuário** da conversa. Quando o usuário responde um turno de confirmação sem novo anexo (fluxo típico do Fin), o turno seguinte perde o acesso ao arquivo da rodada anterior. Além disso, `buildHistory()` (linha 169) descarta a coluna `attachments` ao mapear para `Message` (o value object do SDK só carrega role+content).

## Constraint do SDK

O caminho ad-hoc (`withMessages()`) do Laravel AI SDK aceita apenas `Message` (texto). Anexos só podem ser enviados junto ao prompt atual via `prompt(text, attachments: [...])` — onde o gateway Anthropic relê o conteúdo do disco a cada request (`HasStoredContent::content()`).

## Estratégia de correção

**Agregação de anexos da janela de histórico:**

1. Extrair em `FinAssistantService` um método privado `windowMessages(Conversation $conversation, ?int $limit = null): Collection<int, ConversationMessage>` — a query de janela (últimas N mensagens `Completed`, oldest-first) que hoje vive dentro de `buildHistory()`.
2. `buildHistory()` passa a delegar para `windowMessages()` e mapear para `Message` — assinatura pública inalterada (teste existente `test_build_history_returns_only_last_n_messages_in_order` continua passando).
3. `run()` usa `windowMessages()` diretamente: identifica a última mensagem do usuário na janela (prompt + histórico anterior a ela) e coleta os metadados de anexos de **todas as mensagens do usuário presentes na janela** (não só a última).
4. Hidratar todos os arquivos via `FinAttachmentService::hydrate()`, deduplicando por `path` (defensivo), e passar a lista agregada para `prompt(attachments: $files)`.

**Consequências (mapeadas nos requisitos da issue):**
- Req 1 (persistência): anexos de rodadas anteriores são reenviados à LLM enquanto suas mensagens originais estiverem na janela.
- Req 2 (janela de tokens): o corte da janela N já recorta texto E anexos, pois a coleta acontece sobre as linhas da própria janela.
- Ponto auditável 3 (builder): `run()` deixa de hidratar só a mensagem mais recente.
- Ponto auditável 4 (multi-tenant): guard de defesa em profundidade (abaixo).

**Guard multi-tenant em `FinAttachmentService::hydrate()`:**
- Nova assinatura: `hydrate(array $metadata, ?Workspace $workspace = null): ?File`. Quando `$workspace` é fornecido, retorna `null` se `path` não começar com `fin/{workspace->uuid}/`. `run()` passa o workspace carregado do run.

## Tarefas

### Tarefa 1 — Backend (pawn): implementação + testes PHPUnit (TDD)
1. **Testes primeiro** (devem falhar antes da implementação):
   - `tests/Feature/Fin/FinAssistantServiceTest.php`:
     - `test_run_rehydrates_attachments_from_previous_user_messages_in_window` — msg1 com anexo + resposta do assistente + msg2 sem anexo → `run()` → agente captura `StoredDocument` 'extrato.pdf' da msg1.
     - `test_run_includes_attachments_from_multiple_rounds_in_window` — msg1 (pdf) → assistente → msg2 (imagem) → assistente → msg3 confirmação sem anexo → agente captura os 2 arquivos.
     - `test_run_drops_attachments_outside_history_window` — `config(['ai.fin.context_window' => 3])`; encher conversa até a msg1 sair da janela → anexos capturados vazios e prompt da última msg do usuário intacto.
   - `tests/Feature/Fin/FinAttachmentServiceTest.php`:
     - Testes do guard: path válido do workspace → File; path de outro workspace → null; sem workspace → comportamento atual.
2. **Implementação**: refatoração descrita acima em `FinAssistantService` + guard em `FinAttachmentService::hydrate()` (chamado em `run()` com o workspace).
3. Verificação: `php artisan test tests/Feature/Fin` e `composer quality`.

### Tarefa 2 — E2E (pawn): Cypress
- Adicionar em `cypress/e2e/fin-memory.cy.js` (temático) o journey multi-turno: enviar mensagem com `cypress/fixtures/sample.pdf`, simular resposta de confirmação via `cy.finResponse`, enviar segunda mensagem de confirmação sem anexo, simular segunda resposta e validar que a conversa prossegue (mensagens renderizadas, chip do anexo original visível em `fin-message-attachments`, sem reenvio).
- Verificação: suíte Cypress do domínio Fin.

## Sem mudanças de frontend
`FinMessageResource` já expõe os chips de anexo; nenhuma mutação nova de UI é necessária (requisito de Toast não se aplica — não há novas mutações).

## Gate final
`php artisan test` (suíte completa) + `composer quality` + `npm run quality`.
