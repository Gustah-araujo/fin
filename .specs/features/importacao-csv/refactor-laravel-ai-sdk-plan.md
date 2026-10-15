# Refatoração: Importação com IA — Laravel AI SDK

**Issue**: Refactor rotina de importação de receitas/despesas
**Data**: 2026-10-15
**Status**: Planejamento completo, aguardando execução

---

## Contexto

O plano anterior propunha o uso de `openai-php/client` + HTTP bruto para integrar com o opencode go (glm-5.3-flash). O pedido atual é **substituir essa abordagem pelo Laravel AI SDK** oficial, mantendo toda a lógica de negócio intacta:

- ✅ Fluxo de 3 telas: upload → preview → confirmar
- ✅ Suporte multimodal: PDF, imagem, HTML, CSV
- ✅ Detectação de duplicatas
- ✅ Provider: opencode go via driver `openai-compatible`

---

## Estado Atual (mapeado)

| Componente | Arquivo | Papel | Ação |
|---|---|---|---|
| **AiService** | `app/Services/AiService.php` | HTTP bruto para DeepSeek, só CSV texto | **Substituir** |
| **FakeAiService** | `app/Services/FakeAiService.php` | Parse determinístico CSV, estende AiService | **Substituir** |
| **Ai Facade** | `app/Facades/Ai.php` | `parse(string $csvContent, string $type)` | **Atualizar assinatura** |
| **AppServiceProvider** | `app/Providers/AppServiceProvider.php` | Singleton `ai` → FakeAi/AiService | **Refatorar binding** |
| **ImportService** | `app/Services/ImportService.php` | Delega `Ai::parse()`, detecta duplicatas, cria transactions | **Atualizar chamada** |
| **ImportController** | `app/Http/Controllers/ImportController.php` | `create` → `store` (parse+preview) → `confirm` | **Atualizar store** |
| **UploadCsvRequest** | `app/Http/Requests/UploadCsvRequest.php` | Valida apenas CSV/TXT | **Renomear + ampliar** |
| **Frontend** | `resources/js/Pages/Imports/Index.tsx` | Upload → preview editável → confirmar | **Aceitar mais tipos** |
| **Config** | `config/ai.php` | DeepSeek key/model/url | **Reescrever para SDK** |
| **Env** | `.env.example` | `DEEPSEEK_API_KEY` | **Atualizar** |

---

## Arquitetura Proposta (Laravel AI SDK)

```
┌─────────────────────────────────────────────────────────────┐
│  ImportController                                           │
│  store(UploadFileRequest, Workspace)                        │
│    → $file = $request->file('file')                         │
│    → $parsed = Ai::parseFile($file, $type)                  │
│    → detectDuplicates + preview                             │
└──────────────────┬──────────────────────────────────────────┘
                   │
┌──────────────────▼──────────────────────────────────────────┐
│  Ai::parseFile(UploadedFile $file, string $type): array     │
│  (Facade → AiService)                                       │
│                                                             │
│  1. Detecta tipo do arquivo (extensão)                      │
│  2. Monta prompt com contexto (type, instruções)            │
│  3. Por tipo:                                               │
│     • CSV/TXT  → conteúdo como texto no prompt              │
│     • HTML     → strip_tags → texto no prompt               │
│     • PDF      → extrai texto (smalot/pdfparser) → prompt   │
│     • Imagem   → Files\Image::fromPath() → attachment       │
│  4. Agent->prompt($userMessage, attachments: [...])         │
│  5. Retorna $response['transactions'] (structured output)  │
└──────────────────┬──────────────────────────────────────────┘
                   │
┌──────────────────▼──────────────────────────────────────────┐
│  TransactionParser Agent (App\Ai\Agents\TransactionParser)   │
│  ┌─────────────────────────────────────────────────────┐    │
│  │  implements Agent, HasStructuredOutput              │    │
│  │  use Promptable                                     │    │
│  │                                                     │    │
│  │  instructions():                                    │    │
│  │    "Você é um extrator de transações financeiras.   │    │
│  │     Analise o documento e retorne JSON estruturado  │    │
│  │     com as transações encontradas."                 │    │
│  │                                                     │    │
│  │  schema():                                          │    │
│  │    ['transactions' => array of objects:             │    │
│  │       {description, value, date, type, category}]   │    │
│  │                                                     │    │
│  │  provider: 'opencode' (openai-compatible)           │    │
│  │  model: glm-5.3-flash (multimodal nativo)           │    │
│  └─────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────────────┐
│  opencode go inference API                                  │
│  POST https://opencode.ai/inference/openai/v1/chat/...      │
│  model: glm-5.3-flash (multimodal nativo)                   │
│  Auth: Bearer OPENCODE_API_KEY                              │
└─────────────────────────────────────────────────────────────┘
```

---

## Diferenças-Chave: Plano Anterior vs. Este

| Aspecto | Plano Anterior (openai-php/client) | Plano Atual (Laravel AI SDK) |
|---|---|---|
| **Dependência** | `composer require openai-php/client` | `composer require laravel/ai` |
| **Custom Agent** | Classe manual `AiAgent` com HTTP | `php artisan make:agent TransactionParser --structured` |
| **Multimodal** | Montagem manual de content array (base64) | `Laravel\Ai\Files\Image::fromPath()` / `Files\Document::fromPath()` |
| **Structured Output** | `response_format: json_object` manual | `HasStructuredOutput` + `schema()` method → SDK garante parsing |
| **Config** | `config/ai.php` flat (deepseek_key, model, base_url) | `config/ai.php` do SDK com `providers.opencode` array |
| **Provider Driver** | `OpenAI::factory()->withBaseUrl()` | `'driver' => 'openai-compatible', 'url' => '...'` |
| **FakeAiService** | `extends AiService` (herança frágil) | `implements AiParserInterface` (contrato explícito) |
| **AiService** | Depende de `Http` facade | Depende de `TransactionParser` agent |
| **Error Handling** | Try/catch manual de HTTP | SDK lança exceções tipadas + try/catch no controller |

---

## Passos de Implementação

### 1. Instalar Laravel AI SDK

```bash
composer require laravel/ai
php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"
php artisan migrate  # cria agent_conversations, agent_conversation_messages
```

**Nota**: As tabelas de conversação são criadas pelo SDK mas **não serão usadas** pelo TransactionParser (que não é `Conversacional`). São harmless — o SDK exige as migrations.

### 2. Config — Reescrever `config/ai.php`

O SDK publica seu próprio `config/ai.php`. Substituir o conteúdo atual:

```php
<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'opencode' => [
            'driver' => 'openai-compatible',
            'url' => env('OPENCODE_BASE_URL', 'https://opencode.ai/inference/openai/v1'),
            'key' => env('OPENCODE_API_KEY'),
            'models' => [
                'text' => [
                    'default' => env('OPENCODE_MODEL', 'glm-5.3-flash'),
                ],
            ],
        ],
    ],

    // Mantido para compatibilidade com timeout legado
    'timeout' => env('OPENCODE_TIMEOUT', 120),
];
```

### 3. Env — Atualizar `.env.example`

Remover `DEEPSEEK_API_KEY`, adicionar:

```ini
OPENCODE_API_KEY=
OPENCODE_BASE_URL=https://opencode.ai/inference/openai/v1
OPENCODE_MODEL=glm-5.3-flash
OPENCODE_TIMEOUT=120
```

### 4. Interface — Criar `app/Contracts/AiParserInterface.php`

```php
<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface AiParserInterface
{
    /**
     * Parse an uploaded file into structured transactions.
     *
     * @return array<int, array{description: string, value: float, date: string, type: string, category_name: string}>
     *
     * @throws RuntimeException When parsing fails.
     */
    public function parseFile(UploadedFile $file, string $type): array;
}
```

### 5. Agent — Criar `app/Ai/Agents/TransactionParser.php`

```bash
php artisan make:agent TransactionParser --structured
```

Conteúdo final:

```php
<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

class TransactionParser implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * The provider to use for this agent.
     * Can be overridden at prompt time.
     */
    public function provider(): string
    {
        return 'opencode';
    }

    /**
     * Instructions for the AI model.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
            Você é um extrator de transações financeiras. Analise o documento fornecido
            (extrato bancário, nota fiscal, fatura de cartão, comprovante) e identifique
            todas as transações financeiras presentes.

            Para cada transação, extraia:
            - description: descrição clara da transação
            - value: valor numérico (sempre positivo)
            - date: data no formato Y-m-d
            - type: "debit" para saídas, "credit" para entradas
            - category_name: categoria sugerida (ex: Alimentação, Transporte, Saúde)

            Retorne apenas transações reais — ignore cabeçalhos, rodapés, saldos e totais.
        PROMPT;
    }

    /**
     * Structured output schema.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'transactions' => $schema->array()
                ->items(
                    $schema->object(fn (JsonSchema $s): array => [
                        'description' => $s->string()->required(),
                        'value' => $s->number()->required(),
                        'date' => $s->string()->required(),
                        'type' => $s->string()->enum(['debit', 'credit'])->required(),
                        'category_name' => $s->string()->required(),
                    ])
                )
                ->required(),
        ];
    }
}
```

**Pontos importante:**
- `provider()` retorna `'opencode'` — aponta para o provider configurado em `config/ai.php`
- `glm-5.3-flash` é o modelo padrão do provider, multimodal nativo
- Para imagens, o SDK codifica em base64 e monta o content array automaticamente
- Para PDFs extraídos como texto, vai como texto no prompt (não como attachment)

### 6. Refatorar `AiService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Ai\Agents\TransactionParser;
use App\Contracts\AiParserInterface;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Smalot\PdfParser\PdfParser;

class AiService implements AiParserInterface
{
    /**
     * Parse an uploaded file into structured transactions via AI.
     *
     * @return array<int, array{description: string, value: float, date: string, type: string, category_name: string}>
     *
     * @throws RuntimeException When parsing fails.
     */
    public function parseFile(UploadedFile $file, string $type): array
    {
        $agent = new TransactionParser;
        $extension = strtolower($file->getClientOriginalExtension());

        // Prepare content based on file type
        $userMessage = "Tipo esperado: {$type}. Extraia as transações financeiras.";
        $attachments = [];

        match ($extension) {
            'csv', 'txt' => $userMessage .= "\n\nConteúdo:\n" . file_get_contents($file->getRealPath()),
            'html' => $userMessage .= "\n\nConteúdo:\n" . strip_tags(file_get_contents($file->getRealPath())),
            'pdf' => $userMessage .= "\n\nConteúdo extraído:\n" . $this->extractPdfText($file),
            'png', 'jpg', 'jpeg', 'webp' => $attachments[] = \Laravel\Ai\Files\Image::fromPath($file->getRealPath()),
            default => throw new RuntimeException("Formato de arquivo não suportado: {$extension}."),
        };

        try {
            $response = $agent->prompt($userMessage, attachments: $attachments);
        } catch (\Throwable $e) {
            throw new RuntimeException('AI parsing failed: ' . $e->getMessage(), 0, $e);
        }

        return $this->normalizeTransactions($response['transactions']);
    }

    /**
     * Extract text from PDF using smalot/pdfparser.
     */
    private function extractPdfText(UploadedFile $file): string
    {
        $parser = new PdfParser;
        $pdf = $parser->parseFile($file->getRealPath());

        return $pdf->getText();
    }

    /**
     * Normalize structured output into consistent transaction structure.
     *
     * @param  array<int, array>  $transactions
     * @return array<int, array{description: string, value: float, date: string, type: string, category_name: string}>
     */
    private function normalizeTransactions(array $transactions): array
    {
        return array_map(fn (array $t): array => [
            'description' => (string) ($t['description'] ?? ''),
            'value' => (float) ($t['value'] ?? 0),
            'date' => (string) ($t['date'] ?? now()->format('Y-m-d')),
            'type' => (string) ($t['type'] ?? 'debit'),
            'category_name' => (string) ($t['category_name'] ?? 'Outros'),
        ], $transactions);
    }
}
```

### 7. Refatorar `FakeAiService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AiParserInterface;
use Illuminate\Http\UploadedFile;

class FakeAiService implements AiParserInterface
{
    /**
     * Deterministic parse — returns structured transactions from CSV content.
     * No HTTP calls, no API key needed.
     *
     * @return array<int, array{description: string, value: float, date: string, type: string, category_name: string}>
     */
    public function parseFile(UploadedFile $file, string $type): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        // Only CSV/TXT supported in fake mode
        if (! in_array($extension, ['csv', 'txt'])) {
            throw new \RuntimeException(
                "FakeAiService only supports CSV/TXT. Got: {$extension}"
            );
        }

        $content = file_get_contents($file->getRealPath());
        $lines = array_filter(array_map('trim', explode("\n", $content)));

        if (count($lines) <= 1) {
            return [];
        }

        array_shift($lines); // Remove header

        return array_map(function (string $line) use ($type): array {
            $columns = str_getcsv($line);

            return [
                'description' => $columns[0] ?? 'Transação Importada',
                'value' => (float) ($columns[1] ?? 0),
                'date' => $columns[2] ?? now()->format('Y-m-d'),
                'type' => $type === 'income' ? 'credit' : 'debit',
                'category_name' => $columns[3] ?? 'Outros',
            ];
        }, $lines);
    }
}
```

**Mudança**: Não mais `extends AiService`. Agora `implements AiParserInterface` — contrato explícito, sem acoplamento.

### 8. Atualizar `Ai` Facade

```php
<?php

declare(strict_types=1);

namespace App\Facades;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array parseFile(UploadedFile $file, string $type)
 *
 * @see \App\Services\AiService
 * @see \App\Contracts\AiParserInterface
 */
class Ai extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \App\Contracts\AiParserInterface::class;
    }
}
```

### 9. Refatorar `AppServiceProvider.php`

Mover binding de `boot()` para `register()` e usar interface:

```php
public function register(): void
{
    JsonResource::withoutWrapping();

    $this->app->bind(AiParserInterface::class, function () {
        if (app()->environment('testing')) {
            return new FakeAiService;
        }

        return new AiService;
    });
}
```

**Melhorias:**
- `register()` em vez de `boot()` — semântica correta (binding, não boot)
- Interface em vez de string key — type-safe, mockable
- AiService não precisa mais receber config no constructor (SDK gerencia isso)

### 10. Instalar dependência PDF (se não existir)

```bash
composer require smalot/pdfparser
```

Verificar se já está no `composer.json`. Se não, adicionar.

### 11. Renomear `UploadCsvRequest` → `UploadFileRequest`

```php
<?php

declare(strict(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['expense', 'income'])],
            'file' => [
                'required',
                'file',
                'mimes:csv,txt,pdf,html,png,jpg,jpeg,webp',
                'max:10240', // 10MB
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'O tipo é obrigatório.',
            'type.in' => 'Tipo inválido.',
            'file.required' => 'O arquivo é obrigatório.',
            'file.file' => 'O arquivo enviado é inválido.',
            'file.mimes' => 'O arquivo deve ser CSV, PDF, HTML, imagem ou texto.',
            'file.max' => 'O arquivo não pode exceder 10MB.',
        ];
    }
}
```

### 12. Atualizar `ImportController.php`

```php
public function store(UploadFileRequest $request, Workspace $workspace): Response
{
    $this->authorize('create', [Transaction::class, $workspace]);

    $type = (string) $request->validated('type');
    $file = $request->file('file');

    try {
        $parsed = $this->importService->parseFile($file, $type);
    } catch (RuntimeException $e) {
        return inertia('Imports/Index', [
            'type' => $type,
            'preview' => [],
            'flash' => ['error' => 'Erro ao processar arquivo: ' . $e->getMessage()],
        ]);
    }

    // ... resto inalterado (detectDuplicates, preview, etc.)
}
```

### 13. Atualizar `ImportService.php`

```php
public function parseFile(UploadedFile $file, string $type): array
{
    return Ai::parseFile($file, $type);
}
```

Renomear `parseCsv()` → `parseFile()`. Resto (duplicatas, criação) inalterado.

### 14. Atualizar Frontend `resources/js/Pages/Imports/Index.tsx`

Apenas ampliar `accept` do input:

```tsx
accept=".csv,.txt,.pdf,.html,.htm,.png,.jpg,.jpeg,.webp"
```

E texto descritivo: "Aceita CSV, PDF, HTML e imagens".

### 15. Atualizar/criar Testes

#### `tests/Feature/Import/AiServiceTest.php` — Reescrever

```php
class AiServiceTest extends TestCase
{
    public function test_fake_ai_parser_is_registered_in_testing_environment(): void
    {
        $parser = app(AiParserInterface::class);
        $this->assertInstanceOf(FakeAiService::class, $parser);
    }

    public function test_parse_file_returns_structured_transactions_from_csv(): void
    {
        $csv = "description,value,date,category\n"
            . "Supermercado XYZ,150.00,2026-09-01,Alimentação\n"
            . "Farmácia ABC,45.90,2026-09-02,Saúde";

        $file = UploadedFile::fake()->createWithContent('test.csv', $csv);
        $result = Ai::parseFile($file, 'expense');

        $this->assertCount(2, $result);
        $this->assertSame('Supermercado XYZ', $result[0]['description']);
        $this->assertSame(150.00, $result[0]['value']);
        $this->assertSame('2026-09-01', $result[0]['date']);
        $this->assertSame('debit', $result[0]['type']);
    }

    // ... manter outros testes adaptados para parseFile + UploadedFile::fake()
}
```

#### `tests/Feature/Import/ImportServiceTest.php` — Adaptar

- `test_parse_csv_delegates_to_ai_facade` → `test_parse_file_delegates_to_ai_parser`
- Mock: `Ai::shouldReceive('parseFile')->once()->andReturn([...])`
- Receber `UploadedFile::fake()`

#### `tests/Feature/Import/ImportControllerTest.php` — Adaptar

- `test_store_requires_valid_csv_file` → `test_store_requires_valid_file` (mimes atualizado)
- Adicionar: `test_store_accepts_pdf_file` (upload de PDF fake)
- Adicionar: `test_store_handles_ai_parse_error` (mock RuntimeException)
- Atualizar upload de CSV para usar `UploadedFile::fake()->create('test.csv', $content)`

#### Novo: `tests/Feature/Import/TransactionParserAgentTest.php`

```php
class TransactionParserAgentTest extends TestCase
{
    public function test_agent_instructions_contain_extraction_guidance(): void
    {
        $agent = new TransactionParser;
        $instructions = (string) $agent->instructions();

        $this->assertStringContainsString('transações financeiras', $instructions);
        $this->assertStringContainsString('debit', $instructions);
    }

    public function test_agent_schema_returns_expected_structure(): void
    {
        $agent = new TransactionParser;
        $schema = $agent->schema(app(JsonSchema::class));

        $this->assertArrayHasKey('transactions', $schema);
    }

    public function test_agent_uses_opencode_provider(): void
    {
        $agent = new TransactionParser;
        $this->assertSame('opencode', $agent->provider());
    }
}
```

#### Smoke test: `tests/Feature/Import/ImportSmokeTest.php`

```php
class ImportSmokeTest extends TestCase
{
    public function test_import_create_page_renders_for_expenses(): void
    {
        // ... inalterado
    }

    public function test_import_create_page_renders_for_incomes(): void
    {
        // ... inalterado
    }
}
```

---

## Riscos / Atenções

| Risco | Mitigação |
|---|---|
| **glm-5.3-flash pode não aceitar PDF nativo** | Extrair texto via smalot/pdfparser antes de enviar. Attachment só para imagens. |
| **SDK migrations criam tabelas não usadas** | Harmless — TransactionParser não é Conversacional. Tabelas ficam vazias. |
| **FakeAiService: imagens/PDF lançam exceção** | Fake apenas para CSV/TXT. Para testes de imagem/PDF, mockar `Ai::parseFile`. |
| **Breaking change: `Ai::parse()` removido** | Nenhum outro código usa além de ImportService (já atualizado). |
| **Timeout em arquivos grandes** | Configurado `OPENCODE_TIMEOUT=120` (2 min). PDFs grandes: extração local é rápida, prompt pode ser longo. |
| **smalot/pdfparser dependência nova** | Adicionado ao composer.json. Alternativa: usar `Laravel\Ai\Files\Document` se opencode aceitar PDF nativo. |

---

## O que NÃO muda

- ✅ **Frontend**: componentes visuais, layout, tabela de preview — intactos (só amplia `accept`)
- ✅ **Detectção de duplicatas**: mantida em `ImportService::detectDuplicates()`
- ✅ **Fluxo de 3 etapas**: upload → preview → confirm — intacto
- ✅ **Rotas**: intactas (só muda o request validator)
- ✅ **Políticas de autorização**: intactas
- ✅ **ConfirmImportRequest**: intacto
- ✅ **Resources/ApiResources**: não afetados (preview é array simples, não Resource)
- ✅ **TransactionService**: intacto

---

## Resumo de Arquivos

| Ação | Arquivo |
|---|---|
| **Criar** | `app/Contracts/AiParserInterface.php` |
| **Criar** | `app/Ai/Agents/TransactionParser.php` |
| **Criar** | `tests/Feature/Import/TransactionParserAgentTest.php` |
| **Criar** | `tests/Feature/Import/ImportSmokeTest.php` |
| ** Reescrever** | `config/ai.php` |
| **Reescrever** | `app/Services/AiService.php` |
| **Reescrever** | `app/Services/FakeAiService.php` |
| **Atualizar** | `app/Facades/Ai.php` |
| **Atualizar** | `app/Providers/AppServiceProvider.php` |
| **Atualizar** | `app/Services/ImportService.php` (parseCsv → parseFile) |
| **Atualizar** | `app/Http/Controllers/ImportController.php` |
| **Renomear** | `UploadCsvRequest.php` → `UploadFileRequest.php` |
| **Atualizar** | `.env.example` |
| **Atualizar** | `composer.json` (laravel/ai + smalot/pdfparser) |
| **Atualizar** | `resources/js/Pages/Imports/Index.tsx` (aceitar) |
| **Reescrever** | `tests/Feature/Import/AiServiceTest.php` |
| **Atualizar** | `tests/Feature/Import/ImportServiceTest.php` |
| **Atualizar** | `tests/Feature/Import/ImportControllerTest.php` |
| **Remover** | Dependência `DEEPSEEK_API_KEY` do `.env` |
