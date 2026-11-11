# Fin — Upload de Arquivos no Chat do Assistente (Plano)

> Feature: `CHAT-02` (Upload de arquivos no chat do Fin).
> Fonte: issue "Adicionar suporte a upload de arquivos no chatbot do assistente Fin".

## 1. Objetivo

Permitir que o usuário anexe arquivos à próxima mensagem enviada ao agente Fin. Ao soltar (drag-and-drop) um arquivo sobre o input da conversa, ele é **guardado (staged)** para ser enviado junto com a próxima mensagem. Apenas **PDFs, planilhas, txt e imagens** são permitidos.

Requisitos do issue:

1. Camada de drag-and-drop sobre o input da conversa.
2. Ao soltar um arquivo, ele fica "guardado" para a próxima mensagem (não é enviado imediatamente).
3. Restringir a **PDFs, planilhas, txt e imagens**.

## 2. Estado atual (o que já existe)

| Item | Status |
| --- | --- |
| Composer `resources/js/Components/Fin/ChatInput.tsx` — contrato `onSend(content: string)` (texto apenas) | ✅ existente |
| Envio via `fetch` JSON em `Pages/Fin/Index.tsx::handleSend` (`POST .../messages`, body `{content}`) | ✅ existente |
| `StoreFinMessageRequest` valida apenas `content` (required, string, max 4000) | ✅ existente |
| `FinMessageController::store` → `FinAssistantService::sendMessage(workspace, user, conversation, content)` | ✅ existente |
| `FinAssistantService::persistMessage` grava `attachments => []` hardcoded | ✅ existente |
| `FinAssistantService::run` chama `prompt($promptContent)` **sem** attachments | ✅ existente |
| Coluna `agent_conversation_messages.attachments` (text/JSON, cast `array` no model SDK) | ✅ já migrada |
| Laravel AI SDK suporta attachments: `prompt($text, attachments: [...])` + `File::fromArray()`/`toArray()` | ✅ existente |
| `AiService::parseFile` (Import) demonstra `Image::fromPath` / `UploadedFile` raw como attachment | ✅ existente (padrão) |
| `UploadFileRequest` — validação `mimes` + `max:10240` (padrão a reusar) | ✅ existente |
| Disco default `local` (`storage/app/private`) | ✅ configurado |

**Não existe ainda**: staging de arquivos no frontend, validação de `attachments` no request de mensagem, persistência de arquivos no disco, threading de attachments até o `FinancialAssistantAgent`, e renderização de anexos em mensagens já enviadas.

## 3. Decisões de arquitetura (novas)

| ID | Decisão |
| --- | --- |
| **D-86** | **Upload via multipart no endpoint existente de mensagem** (`POST /w/{workspace}/fin/conversations/{conversation}/messages`), **não** um endpoint separado de upload. O frontend faz o *staging* local (arquivos em memória React) e envia tudo junto na próxima mensagem via `FormData` (`content` + `attachments[]`). |
| **D-87** | **Tipos permitidos**: `pdf, csv, xlsx, xls, txt, png, jpg, jpeg, webp` (PDF, planilhas, txt, imagens — conforme o issue). Limites: **10MB por arquivo** (espelha `UploadFileRequest`) e **5 arquivos por mensagem**. |
| **D-88** | **Persistência em disco + metadados serializados**: arquivos gravados no disco `local` sob diretório escopado por workspace (`fin/{workspace_uuid}/…`); metadados serializados na coluna `attachments` (JSON) usando o formato `File::toArray()` do SDK (`stored-document` / `stored-image` com `path` + `disk` + `name`). Reconstrução no prompt via `File::fromArray()`. **Sem nova migration.** |
| **D-89** | **Classificação no prompt**: imagens → `Image` (StoredImage); pdf/planilhas/txt → `Document` (StoredDocument), espelhando `AiService::parseFile`. Passados via `FinancialAssistantAgent::prompt($content, attachments: $files)`. `content` passa a ser **nullable**; com `content` vazio, o serviço usa prompt de fallback (`Analise o(s) arquivo(s) em anexo.`). |
| **D-90** | **`FinAttachmentService`** dedicado (store + serialize + hydrate) — lógica de negócio fora de controller/model, seguindo o padrão de Service do projeto. |

## 4. Arquitetura — fluxo

```
Usuário arrasta arquivo(s) sobre o input (ou usa o botão de anexar)
  ├─ valida tipo/tamanho no frontend (rejeita com toast)
  ├─ arquivo fica "staged" (chip removível acima do textarea)
  ▼
Usuário envia a próxima mensagem
  │  POST .../messages  (multipart: content + attachments[])
  ▼
StoreFinMessageRequest (valida content OR attachments)
  ▼
FinMessageController::store → FinAssistantService::sendMessage(..., content, attachments[])
  ├─ FinAttachmentService::store() grava cada arquivo no disco local
  ├─ persiste ConversationMessage (role=user) com metadados serializados em `attachments`
  ├─ cria FinAssistantRun (pending)
  └─ dispatch DispatchFinAssistantJob
        │
        ▼ (Queue Worker)
  FinAssistantService::run()
    ├─ localiza a última mensagem user
    ├─ content = conteúdo (ou fallback se vazio)
    ├─ attachments = File::fromArray() para cada metadado da última mensagem
    ├─ FinancialAssistantAgent::prompt(content, attachments: [...])
    └─ persiste resposta + atualiza run
```

## 5. Mudanças (sem migration)

- **Coluna `attachments`**: já existe em `agent_conversation_messages` (text/JSON, cast `array`). Reutilizada para guardar o array de metadados (`{type, name, path, disk}`).
- **Disco**: usar `local` (default). Diretório sugerido: `fin/{workspace_uuid}/{ordered_uuid}.{ext}`.

## 6. Componentes

### Backend (`app/`)

| Camada | Arquivo | Mudança |
| --- | --- | --- |
| Service (novo) | `Services/FinAttachmentService.php` | `store(Workspace, UploadedFile): File` (StoredImage/StoredDocument); `serialize(File): array`; `hydrate(array): ?File` |
| Request | `Http/Requests/StoreFinMessageRequest.php` | `content` nullable; `attachments` array (`mimes`, `max`); regra content-ou-attachments |
| Service | `Services/FinAssistantService.php` | `sendMessage` recebe arquivos; `persistMessage` grava metadados; `run` reconstrói e passa attachments + fallback prompt |
| Controller | `Http/Controllers/FinMessageController.php` | repassar `attachments` validados ao service |
| Resource | `Http/Resources/FinMessageResource.php` | expor `attachments` como `[{name, kind}]` (`image`/`document`) |

### Frontend (`resources/js/`)

| Camada | Arquivo | Mudança |
| --- | --- | --- |
| Types | `types/fin.ts` | `FinAttachmentKind`, `FinAttachment`, `FinMessage.attachments?` |
| Component | `Components/Fin/ChatInput.tsx` | drag-and-drop (overlay), paperclip + `<input type=file multiple>`, chips removíveis, `onSend(content, files)` |
| Component | `Components/Fin/ChatArea.tsx` | atualizar contrato `onSend` |
| Page | `Pages/Fin/Index.tsx` | `handleSend` monta `FormData` (multipart) |
| Component | `Components/Fin/MessageBubble.tsx` | renderizar anexos do usuário (chips: nome + ícone) |

## 7. Estratégia de testes (TDD-first)

### PHPUnit (`tests/Feature/Fin/`)

1. **`FinAttachmentServiceTest`** (novo): store retorna `StoredImage` p/ imagem e `StoredDocument` p/ pdf/xlsx/txt; arquivo realmente gravado no disco; round-trip serialize → hydrate (`File::fromArray`) preserva `name`/`path`/`disk`.
2. **`StoreFinMessageRequestTest`** (novo): aceita tipos permitidos; rejeita extensão não permitida (ex. `.exe`, `.html`); rejeita >10MB; rejeita >5 arquivos; exige `content` **ou** `attachments` (422 quando ambos vazios).
3. **`FinMessageControllerTest`** (atualizar): POST multipart com arquivo → 201, mensagem persistida com `attachments` não vazio, arquivo no disco, `DispatchFinAssistantJob` pushed.
4. **`FinAssistantServiceTest`** (atualizar): `sendMessage` com arquivo grava metadados; `run` reconstrói e passa attachments ao agente (fake captura o arg `attachments`); fallback prompt quando `content` vazio.

### Cypress (`cypress/e2e/fin.cy.js`)

1. Stage de arquivo: `selectFile` no input oculto (ou `trigger('drop', {dataTransfer})`) → chip aparece → enviar → chip limpo.
2. Rejeição: tipo não permitido não entra no stage (toast de erro).
3. Smoke: manter renderização sem crash (já existente).

## 8. Tarefas (ordem, atômicas, TDD-first)

**Fase A — Fundação backend (storage + serialização)**
- **T1**: `FinAttachmentService` (store/serialize/hydrate) + `FinAttachmentServiceTest`. *Gate: `composer quality` + `php artisan test`.*

**Fase B — Validação e threading**
- **T2**: `StoreFinMessageRequest` (content nullable + `attachments[]` + regra content-ou-attachments) + testes de validação.
- **T3**: `FinAssistantService` (`sendMessage`/`persistMessage`/`run` com attachments + fallback) + `FinAssistantServiceTest`.
- **T4**: `FinMessageController::store` repassando attachments + `FinMessageControllerTest` (multipart).

**Fase C — Frontend**
- **T5**: `types/fin.ts` + `ChatInput.tsx` (drag-and-drop, paperclip, chips, `onSend(content, files)`) + `ChatArea.tsx`.
- **T6**: `Pages/Fin/Index.tsx` `handleSend` com `FormData` + `FinMessageResource` + `MessageBubble.tsx` (renderizar anexos).

**Fase D — E2E + qualidade**
- **T7**: Cypress `fin.cy.js` (stage/send/clear + rejeição) + gates finais (`composer quality`, `npm run quality`, `npm run build`).

## 9. Notas técnicas

1. **Multipart, não JSON**: o envio atual usa `fetch` com `Content-Type: application/json`. Para arquivos, o frontend monta `FormData` e **remove** o header `Content-Type` (o browser define o boundary). Manter apenas `X-Requested-With`, `X-CSRF-TOKEN` e `Accept`. Campo do arquivo: `attachments[]` (array). Ex.: `formData.append('attachments[]', file)`.
2. **Arquivos na fila**: os arquivos devem ser persistidos em disco **na requisição HTTP** (controller→service), pois o temp do upload é descartado ao fim da request; o job `DispatchFinAssistantJob` reconstrói a partir do disco no processamento.
3. **`buildHistory` permanece texto**: o `Message` do SDK (`Laravel\Ai\Messages\Message`) só tem `role`/`content` (sem attachments). O histórico anterior continua texto; apenas a **última** mensagem user (a que originou o run) tem seus attachments passados via arg `attachments` de `prompt()`.
4. **`File::fromArray`** suporta `stored-image`/`stored-document` (path + disk) — é o formato exato que `StoredImage::toArray()`/`StoredDocument::toArray()` produzem, garantindo round-trip limpo sem reimplementar serialização.
5. **`attachments` no resource**: expor apenas `{name, kind}` (`kind` derivado de `type`: `*-image` → `image`, senão `document`), sem expor `path`/`disk` crus ao frontend. Download via signed URL é **adiado** (follow-up).
6. **Fallback de conteúdo**: quando a mensagem é só arquivo (`content` vazio), o `content` persistido fica vazio e o fallback é aplicado apenas no `run()` (na montagem do prompt), mantendo a UI coerente (bubble com chips de anexo).
