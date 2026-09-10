# Importação de Receitas e Despesas via CSV com IA (com Processamento Assíncrono)

**Feature**: IMPT-01
**Fase**: P2 (Importação de Extratos via IA)
**Diretório**: `.specs/features/importacao-csv/`
**Status**: Design completo (processamento assíncrono com pooling), aguardando implementação

---

## 📝 Descrição

Implementar o fluxo de importação em lote de receitas e despesas a partir do upload de arquivos CSV. O arquivo será processado de forma **assíncrona no backend** (via Job/fila) enquanto o frontend exibe indicador de progresso e faz **polling** do status. Após conclusão, a IA estrutura e estratifica os lançamentos. Antes de efetivar a criação no banco de dados, o sistema apresentará uma tela de conferência para validação, ajuste de dados, desmarcação de itens indesejados e identificação de possíveis duplicidades.

*Nota: A funcionalidade contemplará apenas receitas e despesas avulsas. Lançamentos recorrentes estão fora do escopo.*

---

## 🎯 Funcionalidades & Requisitos

### 1. Botões de Acesso (Tela de Listagem)
- Incluir dois botões distintos na interface de listagem de lançamentos:
  - **Importar Receitas** (na listagem/aba de receitas).
  - **Importar Despesas** (na listagem/aba de despesas).
- Cada botão deve redirecionar/abrir a rota específica para o fluxo de importação correspondente ao tipo selecionado.

### 2. Upload & Processamento Assíncrono
- **Envio do Arquivo:** Rota dedicada com componente para upload de arquivo no formato `.csv`.
- **Backend (Laravel):**
  - Receber upload, validar, armazenar arquivo temporário.
  - Criar registro `ImportJob` (status: pending).
  - Despachar `ProcessImportCsvJob` para fila.
  - Retornar `job_uuid` imediatamente ao frontend.
- **Job (Background):**
  - Fazer o parse e formatação do CSV para o formato JSON.
  - Enviar o JSON formatado para a IA utilizando uma **Facade customizada do Laravel** (garantindo desacoplamento e agnosticism em relação ao provedor de LLM utilizado).
  - Estruturar o retorno da LLM em uma lista padronizada com campos básicos do lançamento (ex: Descrição, Valor, Data, Categoria, etc.).
  - Detectar duplicatas contra a base do workspace.
  - Atualizar `ImportJob` com resultado (status: completed/failed).
- **Frontend (Polling):**
  - Após receber `job_uuid`, consultar endpoint de status a cada 2 segundos.
  - Exibir indicador visual de processamento ("Processando arquivo...").
  - Ao receber status "completed", renderizar tabela de preview.
  - Ao receber status "failed", exibir mensagem de erro com opção de retry.
  - Timeout visual de 5 minutos com mensagem apropriada.

### 3. Tela de Pré-Visualização, Validação e Ajustes
- Apresentar uma tabela interativa com todas as transações extraídas do arquivo:
  - Permissão para o usuário editar os campos de cada linha caso a LLM tenha interpretado algo incorretamente.
  - Caixas de seleção (*checkbox*) em cada linha para permitir desmarcar itens que o usuário não deseja importar.
- **Detecção de Duplicidades:**
  - O sistema deve analisar os registros já existentes no workspace ativo e comparar com os dados do CSV (ex: correspondência de mesmo valor e datas iguais ou próximas).
  - Sinalizar visualmente na tabela as linhas identificadas como possíveis duplicatas para que o usuário confirme explicitamente antes da importação.

### 4. Confirmação e Efetivação
- Botão **"Importar"** para processar a gravação final apenas das receitas/despesas selecionadas e validadas pelo usuário.
- Ao finalizar, o usuário deve ser redirecionado para a listagem principal com o retorno de feedback correspondente.

---

## ✅ Critérios de Aceite

| ID | Critério | Validação |
|----|----------|-----------|
| AC-1 | Isolamento de Tipos: rota "Importar Receitas" importa estritamente receitas, e "Importar Despesas" estritamente despesas | Teste: expense route cria apenas type=Expense |
| AC-2 | Agnosticismo de LLM: envio via Facade do Laravel, permitindo trocar provedor sem impactar regras de negócio | Teste: mock do AiService via Facade |
| AC-3 | Detecção de Duplicatas: destacar lançamentos com risco de duplicidade | Teste: upload com duplicatas → linhas sinalizadas |
| AC-4 | UX & Feedbacks: loading state durante processamento, editar, desmarcar, confirmar + toasts Sonner/shadcn | E2E: fluxo completo com feedback + tela de processamento visível |
| AC-5 | Contexto de Workspace: todos lançamentos vinculados ao workspace ativo | Teste: non-member → 403 |
| AC-6 | Processamento assíncrono: upload retorna imediatamente, job processa em background, frontend faz polling | Teste: store retorna 201 com job_uuid; polling retorna preview quando completed |
| AC-7 | Resiliência: job retry em caso de falha na IA; erro amigável ao usuário | Teste: mock IA falha 3x → status=failed com mensagem |

---

## 📌 Mapeamento de Implementação

### Backend (Laravel)

| Item | Status | Task |
|------|--------|------|
| Criar Facade + Service de integração com LLM | 🔴 | T1, T2 |
| Criar rota e endpoint para upload + status | 🔴 | T7, T8 |
| Implementar ImportJob model + migration + enum | 🔴 | T4 |
| Implementar ProcessImportCsvJob (parse → IA → duplicatas) | 🔴 | T4 |
| Implementar endpoint de status para polling | 🔴 | T7 |
| Implementar endpoint de confirmação para salvar em lote | 🔴 | T7 |
| Auto-criar categoria "Sem Categoria" por workspace | 🔴 | T3 |

### Frontend (React + Inertia.js)

| Item | Status | Task |
|------|--------|------|
| Adicionar botões de importação nas listagens | 🔴 | T8 |
| Criar página de Upload + Processing + Preview | 🔴 | T10, T12 |
| Criar hook de polling do status do job | 🔴 | T10 |
| Criar componente de indicador de processamento | 🔴 | T10 |
| Criar tabela interativa (edição, checkbox, alertas) | 🔴 | T11 |
| Integrar toasts Sonner (upload, erro, sucesso) | 🔴 | T10, T12 |

### Testes

| Item | Status | Task |
|------|--------|------|
| PHPUnit: AiService tests | 🔴 | T1 |
| PHPUnit: ImportService tests | 🔴 | T4 |
| PHPUnit: ProcessImportCsvJob tests | 🔴 | T4 |
| PHPUnit: ImportController tests (TDD red → green) | 🔴 | T6, T7 |
| PHPUnit: Smoke tests (GET routes) | 🔴 | T13 |
| Cypress: E2E fluxo completo (incluindo processing state) | 🔴 | T14 |
| Quality gate final | 🔴 | T15 |

---

## 🏗️ Arquitetura Resumida

```
[Botão na listagem] → [Página Upload]
                            ↓
                   [POST /{type}/import]
                            ↓
                   [UploadCsvRequest valida]
                            ↓
              [Controller cria ImportJob + armazena arquivo]
                            ↓
             [ProcessImportCsvJob despachado para fila]
                            ↓
              [Retorna {job_uuid, status: "pending"}]
                            ↓
         [Frontend: "Processando..." + polling 2s]
                            ↓
              [GET /{type}/import/{job}/status]
                            ↓
            ┌───────────────┼───────────────┐
            ↓               ↓               ↓
      [pending/         [completed]     [failed]
       processing]           ↓               ↓
            ↓          [Preview JSON]   [Error message]
      [Continuar          ↓               ↓
       polling]    [Tela Pré-visualização]  [Retry?]
                            ↓
              [POST /{type}/import/confirm]
                            ↓
                   [ConfirmImportRequest]
                            ↓
                [ImportService::confirm]
                            ↓
              [TransactionService::create × N]
                            ↓
              [Toast sucesso + redirect listagem]
```

---

## 📁 Arquivos Planejados

### Novos (22)
- `app/Facades/Ai.php` — Facade AI
- `app/Services/AiService.php` — Integração DeepSeek
- `app/Services/ImportService.php` — Orquestração do fluxo
- `app/Models/ImportJob.php` — Model para rastreamento
- `app/Enums/ImportJobStatus.php` — Enum de status
- `app/Jobs/ProcessImportCsvJob.php` — Job assíncrono
- `app/Http/Controllers/ImportController.php` — Endpoints (store, status, confirm)
- `app/Http/Requests/UploadCsvRequest.php` — Validação upload
- `app/Http/Requests/ConfirmImportRequest.php` — Validação confirmação
- `app/Http/Resources/ImportPreviewResource.php` — Resource preview
- `app/Http/Resources/ImportJobResource.php` — Resource status
- `config/ai.php` — Configuração AI
- `database/migrations/2026_09_10_000002_create_import_jobs_table.php` — Migration
- `resources/js/Pages/Imports/Index.tsx` — Página orquestradora
- `resources/js/Components/Import/ImportPreviewTable.tsx` — Tabela interativa
- `resources/js/Components/Import/CsvUploadForm.tsx` — Formulário upload
- `resources/js/Components/Import/ImportProcessing.tsx` — Indicador de processamento
- `resources/js/hooks/use-import-polling.ts` — Hook de polling
- `resources/js/types/import.ts` — Tipos TypeScript
- `tests/Feature/Import/AiServiceTest.php` — Testes AiService
- `tests/Feature/Import/ImportServiceTest.php` — Testes ImportService + Job

### Modificados (4)
- `app/Providers/AppServiceProvider.php` — Registrar singleton 'ai'
- `routes/web.php` — 8 rotas de importação (incluindo status)
- `resources/js/Pages/Transactions/Index.tsx` — Botão "Importar Despesas"
- `resources/js/Pages/Incomes/Index.tsx` — Botão "Importar Receitas"

---

## 🔗 Referências

- **Spec principal**: `.specs/features/workspace-financeiro/spec.md` (linhas 231–251)
- **Design**: `.specs/features/importacao-csv/design.md`
- **Tasks**: `.specs/features/importacao-csv/tasks.md`
- **Decisões relacionadas**: D-22 (DeepSeek via AI SDK), D-31 (categoria sistema auto-criada), D-43 (job pattern reference), D-51 (quality gates)
- **Decisões novas**: D-66 (Facade AI), D-67 (async + pooling), D-68 (Sem Categoria), D-69 (duplicatas), D-70 (ImportJob model)
- **Issue**: [Feature] Importação de Receitas e Despesas via CSV com IA
