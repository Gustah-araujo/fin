<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Enums\WorkspaceRole;
use App\Facades\Ai;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ImportService;
use Tests\TestCase;

class ImportServiceTest extends TestCase
{
    public function test_parse_csv_delegates_to_ai_facade(): void
    {
        $parsed = [
            ['description' => 'Supermercado XYZ', 'value' => 150.00, 'date' => '2026-09-01', 'type' => 'debit', 'category_name' => 'Alimentação'],
        ];

        Ai::shouldReceive('parse')
            ->once()
            ->with('conteúdo-do-csv', 'expense')
            ->andReturn($parsed);

        $service = app(ImportService::class);

        $result = $service->parseCsv('conteúdo-do-csv', 'expense');

        $this->assertSame($parsed, $result);
    }

    public function test_detect_duplicates_flags_matching_transactions(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);
        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
        ]);

        Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'type' => 'expense',
            'value' => 150.00,
            'date' => '2026-09-01',
        ]);

        $transactions = [
            ['description' => 'Supermercado XYZ', 'value' => 150.00, 'date' => '2026-09-01', 'type' => 'debit', 'category_name' => 'Alimentação'],
        ];

        $service = app(ImportService::class);

        $result = $service->detectDuplicates($workspace, $transactions, 'expense');

        $this->assertTrue($result[0]['is_duplicate']);
    }

    public function test_detect_duplicates_does_not_flag_different_values(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);
        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
        ]);

        Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'type' => 'expense',
            'value' => 150.00,
            'date' => '2026-09-01',
        ]);

        $transactions = [
            ['description' => 'Farmácia ABC', 'value' => 45.90, 'date' => '2026-09-02', 'type' => 'debit', 'category_name' => 'Saúde'],
        ];

        $service = app(ImportService::class);

        $result = $service->detectDuplicates($workspace, $transactions, 'expense');

        $this->assertFalse($result[0]['is_duplicate']);
    }

    public function test_create_transactions_creates_transactions_with_import_account_and_category(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $items = [
            ['description' => 'Supermercado XYZ', 'value' => 150.00, 'date' => '2026-09-01', 'type' => 'debit', 'category_name' => 'Alimentação'],
        ];

        $service = app(ImportService::class);

        $count = $service->createTransactions($workspace, $user, $items, 'expense');

        $this->assertSame(1, $count);

        $importAccount = Account::where('workspace_id', $workspace->id)
            ->where('name', 'Importado')
            ->first();
        $importCategory = Category::where('workspace_id', $workspace->id)
            ->where('name', 'Importadas')
            ->first();

        $this->assertNotNull($importAccount);
        $this->assertNotNull($importCategory);

        $this->assertDatabaseHas('transactions', [
            'workspace_id' => $workspace->id,
            'account_id' => $importAccount->id,
            'category_id' => $importCategory->id,
            'description' => 'Supermercado XYZ',
            'value' => '150.00',
            'type' => 'expense',
        ]);
    }

    public function test_create_transactions_skips_unconfirmed_duplicates(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $items = [
            ['description' => 'Duplicada', 'value' => 150.00, 'date' => '2026-09-01', 'type' => 'debit', 'category_name' => 'Alimentação', 'is_duplicate' => true, 'confirm_duplicate' => false],
            ['description' => 'Nova', 'value' => 99.00, 'date' => '2026-09-02', 'type' => 'debit', 'category_name' => 'Saúde', 'is_duplicate' => false, 'confirm_duplicate' => false],
        ];

        $service = app(ImportService::class);

        $count = $service->createTransactions($workspace, $user, $items, 'expense');

        $this->assertSame(1, $count);

        $this->assertDatabaseHas('transactions', [
            'workspace_id' => $workspace->id,
            'description' => 'Nova',
        ]);
        $this->assertDatabaseMissing('transactions', [
            'workspace_id' => $workspace->id,
            'description' => 'Duplicada',
        ]);
    }

    public function test_create_transactions_creates_importado_account_and_importadas_category(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $items = [
            ['description' => 'Teste', 'value' => 100.00, 'date' => '2026-09-01', 'type' => 'debit', 'category_name' => 'Outros'],
        ];

        $service = app(ImportService::class);

        $service->createTransactions($workspace, $user, $items, 'expense');

        $this->assertDatabaseHas('accounts', [
            'workspace_id' => $workspace->id,
            'name' => 'Importado',
            'type' => 'checking',
            'initial_balance' => 0,
            'current_balance' => 0,
        ]);

        $this->assertDatabaseHas('categories', [
            'workspace_id' => $workspace->id,
            'name' => 'Importadas',
            'type' => 'expense',
        ]);
    }
}
