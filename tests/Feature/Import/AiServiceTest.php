<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Ai\Agents\TransactionParser;
use App\Contracts\AiParserInterface;
use App\Enums\WorkspaceRole;
use App\Facades\Ai;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AiService;
use App\Services\FakeAiService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AiServiceTest extends TestCase
{
    private function createWorkspace(): Workspace
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        return $workspace;
    }

    public function test_fake_ai_parser_is_registered_in_testing_environment(): void
    {
        $parser = app(AiParserInterface::class);

        $this->assertInstanceOf(FakeAiService::class, $parser);
    }

    public function test_parse_file_returns_structured_transactions_from_csv(): void
    {
        $workspace = $this->createWorkspace();

        $csv = "description,value,date,category\n"
            ."Supermercado XYZ,150.00,2026-09-01,Alimentação\n"
            .'Farmácia ABC,45.90,2026-09-02,Saúde';

        $file = UploadedFile::fake()->createWithContent('test.csv', $csv);
        $result = Ai::parseFile($workspace, $file, 'expense');

        $this->assertCount(2, $result);

        $this->assertSame('Supermercado XYZ', $result[0]['description']);
        $this->assertSame(150.00, $result[0]['value']);
        $this->assertSame('2026-09-01', $result[0]['date']);
        $this->assertSame('debit', $result[0]['type']);
        $this->assertSame('Alimentação', $result[0]['category_name']);
        $this->assertNull($result[0]['category_uuid']);
        $this->assertSame([], $result[0]['tags']);
        $this->assertFalse($result[0]['is_duplicate']);
    }

    public function test_parse_file_returns_empty_array_for_csv_with_only_header(): void
    {
        $workspace = $this->createWorkspace();

        $csv = 'description,value,date,category';

        $file = UploadedFile::fake()->createWithContent('test.csv', $csv);
        $result = Ai::parseFile($workspace, $file, 'expense');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_parse_file_returns_empty_array_for_empty_csv(): void
    {
        $workspace = $this->createWorkspace();

        $file = UploadedFile::fake()->createWithContent('test.csv', '');
        $result = Ai::parseFile($workspace, $file, 'expense');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_parse_file_sets_credit_type_for_income(): void
    {
        $workspace = $this->createWorkspace();

        $csv = "description,value,date,category\n"
            .'Salário,5000.00,2026-09-05,Trabalho';

        $file = UploadedFile::fake()->createWithContent('test.csv', $csv);
        $result = Ai::parseFile($workspace, $file, 'income');

        $this->assertCount(1, $result);
        $this->assertSame('credit', $result[0]['type']);
    }

    public function test_parse_file_handles_missing_category_gracefully(): void
    {
        $workspace = $this->createWorkspace();

        $csv = "description,value,date\n"
            .'Transação Sem Categoria,99.90,2026-09-10';

        $file = UploadedFile::fake()->createWithContent('test.csv', $csv);
        $result = Ai::parseFile($workspace, $file, 'expense');

        $this->assertCount(1, $result);
        $this->assertSame('Outros', $result[0]['category_name']);
    }

    public function test_full_pipeline_with_sdk_fake(): void
    {
        TransactionParser::fake([
            [
                'transactions' => [
                    [
                        'description' => 'Compra Fake',
                        'value' => 250.00,
                        'date' => '2026-10-01',
                        'type' => 'debit',
                        'category_name' => 'Eletrônicos',
                        'category_uuid' => null,
                        'tags' => ['importado'],
                        'is_duplicate' => false,
                    ],
                    [
                        'description' => 'Salário Fake',
                        'value' => 5000.00,
                        'date' => '2026-10-05',
                        'type' => 'credit',
                        'category_name' => 'Trabalho',
                        'category_uuid' => null,
                        'tags' => [],
                        'is_duplicate' => false,
                    ],
                ],
            ],
        ]);

        $workspace = $this->createWorkspace();

        $file = UploadedFile::fake()->createWithContent('test.csv', 'any content');

        // Use real AiService instead of FakeAiService to exercise the SDK
        $service = new AiService;
        $result = $service->parseFile($workspace, $file, 'expense');

        $this->assertCount(2, $result);
        $this->assertSame('Compra Fake', $result[0]['description']);
        $this->assertSame(250.00, $result[0]['value']);
        $this->assertSame('debit', $result[0]['type']);
        $this->assertSame(['importado'], $result[0]['tags']);
        $this->assertFalse($result[0]['is_duplicate']);
        $this->assertNotNull($result[0]['uuid']);

        $this->assertSame('Salário Fake', $result[1]['description']);
        $this->assertSame(5000.00, $result[1]['value']);
        $this->assertSame('credit', $result[1]['type']);
    }
}
