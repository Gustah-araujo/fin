<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionType;
use App\Facades\Ai;
use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ImportService
{
    /**
     * Parse an uploaded file into structured transactions via AI.
     *
     * @return array<int, array{uuid: string, description: string, value: float, date: string, type: string, category_name: string, category_uuid: string|null, tags: list<string>, is_duplicate: bool}>
     */
    public function parseFile(Workspace $workspace, UploadedFile $file, string $type): array
    {
        return Ai::parseFile($workspace, $file, $type);
    }

    /**
     * Create transactions in batch from validated import items.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public function createTransactions(Workspace $workspace, User $user, array $items, string $type): int
    {
        $transactionType = TransactionType::tryFrom($type) ?? TransactionType::Expense;
        $account = $this->getOrCreateImportAccount($workspace);
        $category = $this->getOrCreateImportCategory($workspace, $transactionType);

        $count = 0;

        foreach ($items as $item) {
            // Skip items that are duplicates and not explicitly confirmed
            if (($item['is_duplicate'] ?? false) && ! ($item['confirm_duplicate'] ?? false)) {
                continue;
            }

            $categoryId = $item['category_uuid'] ?? null;

            if ($categoryId === null) {
                $categoryId = $this->resolveCategoryByName($workspace, $item['category_name'] ?? 'Outros', $transactionType)?->uuid;
            }

            $data = [
                'description' => $item['description'],
                'value' => $item['value'],
                'date' => $item['date'],
                'type' => $transactionType->value,
                'account_id' => $account->uuid,
                'category_id' => $categoryId ?? $category->uuid,
            ];

            app(TransactionService::class)->create($workspace, $user, $data);
            $count++;
        }

        return $count;
    }

    /**
     * Resolve an existing category by name, or return null.
     */
    private function resolveCategoryByName(Workspace $workspace, string $name, TransactionType $type): ?Category
    {
        return $workspace->categories()
            ->where('name', $name)
            ->where('type', $type)
            ->first();
    }

    private function getOrCreateImportAccount(Workspace $workspace): Account
    {
        return $workspace->accounts()->firstOrCreate(
            ['name' => 'Importado'],
            [
                'uuid' => Str::orderedUuid()->toString(),
                'type' => 'checking',
                'initial_balance' => 0,
                'current_balance' => 0,
            ],
        );
    }

    private function getOrCreateImportCategory(Workspace $workspace, TransactionType $type): Category
    {
        $categoryType = $type === TransactionType::Expense ? TransactionType::Expense : TransactionType::Income;

        return $workspace->categories()->firstOrCreate(
            ['name' => 'Importadas', 'type' => $categoryType],
            [
                'uuid' => Str::orderedUuid()->toString(),
                'color' => '#6b7280',
                'icon' => null,
            ],
        );
    }
}
