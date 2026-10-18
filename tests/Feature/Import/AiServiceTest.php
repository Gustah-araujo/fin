<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Contracts\AiParserInterface;
use App\Enums\WorkspaceRole;
use App\Facades\Ai;
use App\Models\User;
use App\Models\Workspace;
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
        $result = Ai::parseFile($workspace, $file);

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
        $result = Ai::parseFile($workspace, $file);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_parse_file_returns_empty_array_for_empty_csv(): void
    {
        $workspace = $this->createWorkspace();

        $file = UploadedFile::fake()->createWithContent('test.csv', '');
        $result = Ai::parseFile($workspace, $file);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_parse_file_reads_type_from_csv_column(): void
    {
        $workspace = $this->createWorkspace();

        $csv = "description,value,date,category,type\n"
            .'Salário,5000.00,2026-09-05,Trabalho,credit';

        $file = UploadedFile::fake()->createWithContent('test.csv', $csv);
        $result = Ai::parseFile($workspace, $file);

        $this->assertCount(1, $result);
        $this->assertSame('credit', $result[0]['type']);
    }

    public function test_parse_file_handles_missing_category_gracefully(): void
    {
        $workspace = $this->createWorkspace();

        $csv = "description,value,date\n"
            .'Transação Sem Categoria,99.90,2026-09-10';

        $file = UploadedFile::fake()->createWithContent('test.csv', $csv);
        $result = Ai::parseFile($workspace, $file);

        $this->assertCount(1, $result);
        $this->assertSame('Outros', $result[0]['category_name']);
    }
}
