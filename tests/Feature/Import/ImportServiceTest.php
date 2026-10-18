<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Enums\WorkspaceRole;
use App\Facades\Ai;
use App\Models\Account;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ImportService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportServiceTest extends TestCase
{
    public function test_parse_file_delegates_to_ai_parser(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $parsed = [
            ['uuid' => 'test-uuid', 'description' => 'Supermercado XYZ', 'value' => 150.00, 'date' => '2026-09-01', 'type' => 'debit', 'category_name' => 'Alimentação', 'category_uuid' => null, 'tags' => [], 'is_duplicate' => false],
        ];

        $file = UploadedFile::fake()->createWithContent('test.csv', 'csv content');

        Ai::shouldReceive('parseFile')
            ->once()
            ->with($workspace, $file)
            ->andReturn($parsed);

        $service = app(ImportService::class);

        $result = $service->parseFile($workspace, $file);

        $this->assertSame($parsed, $result);
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

        $count = $service->createTransactions($workspace, $user, $items);

        $this->assertSame(1, $count);

        $importAccount = Account::where('workspace_id', $workspace->id)
            ->where('name', 'Importado')
            ->first();
        // Unified import resolves/creates the per-item category by name
        $itemCategory = Category::where('workspace_id', $workspace->id)
            ->where('name', 'Alimentação')
            ->first();

        $this->assertNotNull($importAccount);
        $this->assertNotNull($itemCategory);

        $this->assertDatabaseHas('transactions', [
            'workspace_id' => $workspace->id,
            'account_id' => $importAccount->id,
            'category_id' => $itemCategory->id,
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

        $count = $service->createTransactions($workspace, $user, $items);

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

        // No category_name — falls back to the default "Importadas" category
        $items = [
            ['description' => 'Teste', 'value' => 100.00, 'date' => '2026-09-01', 'type' => 'debit'],
        ];

        $service = app(ImportService::class);

        $service->createTransactions($workspace, $user, $items);

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

    public function test_create_transactions_handles_mixed_types(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $items = [
            ['description' => 'Despesa Test', 'value' => 100.00, 'date' => '2026-09-01', 'type' => 'debit', 'category_name' => 'Alimentação'],
            ['description' => 'Receita Test', 'value' => 5000.00, 'date' => '2026-09-05', 'type' => 'credit', 'category_name' => 'Trabalho'],
        ];

        $service = app(ImportService::class);

        $count = $service->createTransactions($workspace, $user, $items);

        $this->assertSame(2, $count);

        $this->assertDatabaseHas('transactions', [
            'workspace_id' => $workspace->id,
            'description' => 'Despesa Test',
            'type' => 'expense',
        ]);

        $this->assertDatabaseHas('transactions', [
            'workspace_id' => $workspace->id,
            'description' => 'Receita Test',
            'type' => 'income',
        ]);
    }

    public function test_create_transactions_syncs_tags(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $tag = Tag::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        $items = [
            ['description' => 'Com Tag', 'value' => 100.00, 'date' => '2026-09-01', 'type' => 'debit', 'category_name' => 'Outros', 'tags' => [$tag->uuid]],
        ];

        $service = app(ImportService::class);

        $service->createTransactions($workspace, $user, $items);

        $transaction = Transaction::where('workspace_id', $workspace->id)
            ->where('description', 'Com Tag')
            ->first();

        $this->assertNotNull($transaction);
        $this->assertTrue($transaction->tags->contains($tag));
    }
}
