# Importação de Receitas e Despesas via CSV com IA

**Feature**: IMPT-01
**Fase**: P2 (Importação de Extratos via IA)
**Diretório**: `.specs/features/importacao-csv/`
**Status**: Design completo, aguardando implementação

---

## 📝 Descrição

Implementar o fluxo de importação em lote de receitas e despesas a partir do upload de arquivos CSV. O arquivo será processado no backend e enviado para um modelo de IA (via Facade no Laravel) responsável por estruturar e estratificar os lançamentos. Antes de efetivar a criação no banco de dados, o sistema apresentará uma tela de conferência para validação, ajuste de dados, desmarcação de itens indesejados e identificação de possíveis duplicidades.

*Nota: A funcionalidade contemplará apenas receitas e despesas avulsas. Lançamentos recorrentes estão fora do escopo.*

---

## 🎯 Funcionalidades & Requisitos

### 1. Botões de Acesso (Tela de Listagem)
- Incluir dois botões distintos na interface de listagem de lançamentos:
  - **Importar Receitas** (na listagem/aba de receitas).
  - **Importar Despesas** (na listagem/aba de despesas).
- Cada botão deve redirecionar/abrir a rota específica para o fluxo de importação correspondente ao tipo selecionado.

### 2. Upload & Processamento
- **Envio do Arquivo:** Rota dedicada com componente para upload de arquivo no formato `.csv`.
- **Backend (Laravel):**
  - Fazer o parse e formatação do CSV para o formato JSON.
  - Enviar o JSON formatado para a IA utilizando uma **Facade customizada do Laravel** (garantindo desacoplamento e agnosticism em relação ao provedor de LLM utilizado).
  - Estruturar o retorno da LLM em uma lista padronizada com campos básicos do lançamento (ex: Descrição, Valor, Data, Categoria, etc.).

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
| AC-4 | UX & Feedbacks: editar, desmarcar, confirmar + toasts Sonner/shadcn | E2E: fluxo completo com feedback |
| AC-5 | Contexto de Workspace: todos lançamentos vinculados ao workspace ativo | Teste: non-member → 403 |

---

## 📌 Mapeamento de Implementação

### Backend (Laravel)

| Item | Status | Task |
|------|--------|------|
| Criar Facade + Service de integração com LLM | 🔴 | T1, T2 |
| Criar rota e endpoint para upload/processamento CSV | 🔴 | T7, T8 |
| Implementar parser de CSV → JSON + envio para Facade IA | 🔴 | T1, T4 |
| Implementar serviço de checagem de duplicidades | 🔴 | T4 |
| Criar endpoint de confirmação para salvar em lote | 🔴 | T7 |
| Auto-criar categoria "Sem Categoria" por workspace | 🔴 | T3 |

### Frontend (React + Inertia.js)

| Item | Status | Task |
|------|--------|------|
| Adicionar botões de importação nas listagens | 🔴 | T8 |
| Criar página de Upload e Pré-visualização | 🔴 | T10, T12 |
| Criar tabela interativa (edição, checkbox, alertas) | 🔴 | T11 |
| Integrar toasts Sonner (upload, erro, sucesso) | 🔴 | T10, T12 |

### Testes

| Item | Status | Task |
|------|--------|------|
| PHPUnit: AiService tests | 🔴 | T1 |
| PHPUnit: ImportService tests | 🔴 | T4 |
| PHPUnit: ImportController tests (TDD red → green) | 🔴 | T6, T7 |
| PHPUnit: Smoke tests (GET routes) | 🔴 | T13 |
| Cypress: E2E fluxo completo | 🔴 | T14 |
| Quality gate final | 🔴 | T15 |

---

## 🏗️ Arquitetura Resumida

```
[Botão na listagem] → [Página Upload] → [POST /{type}/import]
                                              ↓
                                    [UploadCsvRequest]
                                              ↓
                                    [ImportService::parseCsv]
                                              ↓
                                       [Ai::parse]
                                              ↓
                              [ImportService::findDuplicates]
                                              ↓
                                   [JSON Preview Response]
                                              ↓
                              [Tela Pré-visualização Interativa]
                                              ↓
                              [POST /{type}/import/confirm]
                                              ↓
                                    [ImportService::confirm]
                                              ↓
                              [TransactionService::create × N]
                                              ↓
                              [Toast sucesso + redirect listagem]
```

---

## 📁 Arquivos Planejados

### Novos (18)
- `app/Facades/Ai.php` — Facade AI
- `app/Services/AiService.php` — Integração DeepSeek
- `app/Services/ImportService.php` — Orquestração do fluxo
- `app/Http/Controllers/ImportController.php` — Endpoints
- `app/Http/Requests/UploadCsvRequest.php` — Validação upload
- `app/Http/Requests/ConfirmImportRequest.php` — Validação confirmação
- `app/Http/Resources/ImportPreviewResource.php` — Resource preview
- `config/ai.php` — Configuração AI
- `resources/js/Pages/Imports/Index.tsx` — Página de importação
- `resources/js/Components/Import/ImportPreviewTable.tsx` — Tabela interativa
- `resources/js/Components/Import/CsvUploadForm.tsx` — Formulário upload
- `resources/js/types/import.ts` — Tipos TypeScript
- `tests/Feature/Import/AiServiceTest.php` — Testes AiService
- `tests/Feature/Import/ImportServiceTest.php` — Testes ImportService
- `tests/Feature/Import/ImportControllerTest.php` — Testes controller
- `tests/Feature/Import/ImportSmokeTest.php` — Smoke tests
- `cypress/e2e/import.cy.ts` — E2E

### Modificados (4)
- `app/Providers/AppServiceProvider.php` — Registrar singleton 'ai'
- `routes/web.php` — Rotas de importação
- `resources/js/Pages/Transactions/Index.tsx` — Botão "Importar Despesas"
- `resources/js/Pages/Incomes/Index.tsx` — Botão "Importar Receitas"

---

## 🔗 Referências

- **Spec principal**: `.specs/features/workspace-financeiro/spec.md` (linhas 231–251)
- **Design**: `.specs/features/importacao-csv/design.md`
- **Tasks**: `.specs/features/importacao-csv/tasks.md`
- **Decisões relacionadas**: D-22 (DeepSeek via AI SDK), D-31 (categoria sistema auto-criada), D-51 (quality gates)
- **Issue**: [Feature] Importação de Receitas e Despesas via CSV com IA
