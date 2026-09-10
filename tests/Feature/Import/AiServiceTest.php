<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Facades\Ai;
use App\Services\FakeAiService;
use Tests\TestCase;

class AiServiceTest extends TestCase
{
    public function test_fake_ai_service_is_registered_in_testing_environment(): void
    {
        $service = app('ai');

        $this->assertInstanceOf(FakeAiService::class, $service);
    }

    public function test_parse_returns_structured_transactions_from_csv(): void
    {
        $csv = "description,value,date,category\n"
            ."Supermercado XYZ,150.00,2026-09-01,Alimentação\n"
            .'Farmácia ABC,45.90,2026-09-02,Saúde';

        $result = Ai::parse($csv, 'expense');

        $this->assertCount(2, $result);

        $this->assertSame('Supermercado XYZ', $result[0]['description']);
        $this->assertSame(150.00, $result[0]['value']);
        $this->assertSame('2026-09-01', $result[0]['date']);
        $this->assertSame('debit', $result[0]['type']);
        $this->assertSame('Alimentação', $result[0]['category_name']);
    }

    public function test_parse_returns_empty_array_for_csv_with_only_header(): void
    {
        $csv = 'description,value,date,category';

        $result = Ai::parse($csv, 'expense');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_parse_returns_empty_array_for_empty_csv(): void
    {
        $result = Ai::parse('', 'expense');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_parse_sets_credit_type_for_income(): void
    {
        $csv = "description,value,date,category\n"
            .'Salário,5000.00,2026-09-05,Trabalho';

        $result = Ai::parse($csv, 'income');

        $this->assertCount(1, $result);
        $this->assertSame('credit', $result[0]['type']);
    }

    public function test_parse_handles_missing_category_gracefully(): void
    {
        $csv = "description,value,date\n"
            .'Transação Sem Categoria,99.90,2026-09-10';

        $result = Ai::parse($csv, 'expense');

        $this->assertCount(1, $result);
        $this->assertSame('Outros', $result[0]['category_name']);
    }
}
