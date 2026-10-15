<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Services\AiService;
use App\Services\FakeAiService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AiServiceTest extends TestCase
{
    public function test_fake_ai_service_is_registered_in_testing_environment(): void
    {
        $service = app(AiService::class);

        $this->assertInstanceOf(FakeAiService::class, $service);
    }

    public function test_parse_file_returns_structured_transactions_from_csv(): void
    {
        $csv = "description,value,date,category\n"
            ."Supermercado XYZ,150.00,2026-09-01,Alimentação\n"
            .'Farmácia ABC,45.90,2026-09-02,Saúde';

        $file = UploadedFile::fake()->createWithContent('test.csv', $csv);

        $service = app(AiService::class);
        $result = $service->parseFile($file, 'expense');

        $this->assertCount(2, $result);

        $this->assertSame('Supermercado XYZ', $result[0]['description']);
        $this->assertSame(150.00, $result[0]['value']);
        $this->assertSame('2026-09-01', $result[0]['date']);
        $this->assertSame('debit', $result[0]['type']);
        $this->assertSame('Alimentação', $result[0]['category_name']);
    }

    public function test_parse_file_returns_empty_array_for_csv_with_only_header(): void
    {
        $csv = 'description,value,date,category';

        $file = UploadedFile::fake()->createWithContent('test.csv', $csv);

        $service = app(AiService::class);
        $result = $service->parseFile($file, 'expense');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_parse_file_returns_empty_array_for_empty_csv(): void
    {
        $file = UploadedFile::fake()->createWithContent('test.csv', '');

        $service = app(AiService::class);
        $result = $service->parseFile($file, 'expense');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_parse_file_sets_credit_type_for_income(): void
    {
        $csv = "description,value,date,category\n"
            .'Salário,5000.00,2026-09-05,Trabalho';

        $file = UploadedFile::fake()->createWithContent('test.csv', $csv);

        $service = app(AiService::class);
        $result = $service->parseFile($file, 'income');

        $this->assertCount(1, $result);
        $this->assertSame('credit', $result[0]['type']);
    }

    public function test_parse_file_handles_missing_category_gracefully(): void
    {
        $csv = "description,value,date\n"
            .'Transação Sem Categoria,99.90,2026-09-10';

        $file = UploadedFile::fake()->createWithContent('test.csv', $csv);

        $service = app(AiService::class);
        $result = $service->parseFile($file, 'expense');

        $this->assertCount(1, $result);
        $this->assertSame('Outros', $result[0]['category_name']);
    }

    public function test_parse_file_returns_fake_transaction_for_non_text_files(): void
    {
        $file = UploadedFile::fake()->image('extrato.png');

        $service = app(AiService::class);
        $result = $service->parseFile($file, 'expense');

        $this->assertCount(1, $result);
        $this->assertSame('debit', $result[0]['type']);
        $this->assertSame('Importadas', $result[0]['category_name']);
    }
}
