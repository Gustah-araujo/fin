<?php

declare(strict_types=1);

namespace Tests\Feature\Fin;

use App\Enums\TransactionType;
use App\Enums\WorkspaceRole;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Services\DuplicateDetectionService;
use Carbon\Carbon;
use Tests\TestCase;

class DuplicateDetectionServiceTest extends TestCase
{
    private Workspace $workspace;

    private User $user;

    private Category $category;

    private DuplicateDetectionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->members()->attach($this->user, ['role' => WorkspaceRole::Admin->value]);
        $this->category = Category::factory()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
            'type' => 'expense',
        ]);

        $this->service = app(DuplicateDetectionService::class);
    }

    private function createTransaction(array $overrides = []): Transaction
    {
        return Transaction::factory()->create(array_merge([
            'workspace_id' => $this->workspace->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'type' => 'expense',
            'value' => 100.00,
            'date' => '2026-01-15',
            'description' => 'Supermercado Extra',
        ], $overrides));
    }

    public function test_find_duplicate_returns_matching_transaction(): void
    {
        $transaction = $this->createTransaction();

        $duplicate = $this->service->findDuplicate(
            $this->workspace,
            TransactionType::Expense,
            100.00,
            Carbon::parse('2026-01-15'),
            'Supermercado Extra'
        );

        $this->assertNotNull($duplicate);
        $this->assertTrue($duplicate->is($transaction));
    }

    public function test_find_duplicate_tolerates_small_value_and_date_variance(): void
    {
        $transaction = $this->createTransaction();

        $duplicate = $this->service->findDuplicate(
            $this->workspace,
            TransactionType::Expense,
            100.005,
            Carbon::parse('2026-01-16'),
            'Supermercado Extra hoje'
        );

        $this->assertNotNull($duplicate);
        $this->assertTrue($duplicate->is($transaction));
    }

    public function test_find_duplicate_ignores_different_description(): void
    {
        $this->createTransaction();

        $duplicate = $this->service->findDuplicate(
            $this->workspace,
            TransactionType::Expense,
            100.00,
            Carbon::parse('2026-01-15'),
            'Pagamento de aluguel do apartamento'
        );

        $this->assertNull($duplicate);
    }

    public function test_find_duplicate_ignores_different_workspace(): void
    {
        $otherWorkspace = Workspace::factory()->create();
        $otherCategory = Category::factory()->create([
            'workspace_id' => $otherWorkspace->id,
            'created_by' => $this->user->id,
            'type' => 'expense',
        ]);

        Transaction::factory()->create([
            'workspace_id' => $otherWorkspace->id,
            'category_id' => $otherCategory->id,
            'created_by' => $this->user->id,
            'type' => 'expense',
            'value' => 100.00,
            'date' => '2026-01-15',
            'description' => 'Supermercado Extra',
        ]);

        $duplicate = $this->service->findDuplicate(
            $this->workspace,
            TransactionType::Expense,
            100.00,
            Carbon::parse('2026-01-15'),
            'Supermercado Extra'
        );

        $this->assertNull($duplicate);
    }

    public function test_find_duplicate_respects_type(): void
    {
        $this->createTransaction(['type' => 'income']);

        $duplicate = $this->service->findDuplicate(
            $this->workspace,
            TransactionType::Expense,
            100.00,
            Carbon::parse('2026-01-15'),
            'Supermercado Extra'
        );

        $this->assertNull($duplicate);
    }
}
