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
    public function parseFile(Workspace $workspace, UploadedFile $file): array
    {
        return Ai::parseFile($workspace, $file);
    }

    /**
     * Create transactions in batch from validated import items.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public function createTransactions(Workspace $workspace, User $user, array $items): int
    {
        $account = $this->getOrCreateImportAccount($workspace);
        $count = 0;

        foreach ($items as $item) {
            if (($item['is_duplicate'] ?? false) && ! ($item['confirm_duplicate'] ?? false)) {
                continue;
            }

            $itemType = match ($item['type']) {
                'credit' => TransactionType::Income,
                default => TransactionType::Expense,
            };

            $category = $this->resolveOrCreateCategory($workspace, $item, $itemType);

            $data = [
                'description' => $item['description'],
                'value' => $item['value'],
                'date' => $item['date'],
                'type' => $itemType->value,
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
            ];

            $transaction = app(TransactionService::class)->create($workspace, $user, $data);

            if (! empty($item['tags'])) {
                app(TransactionService::class)->syncTags($transaction, $item['tags']);
            }

            $count++;
        }

        return $count;
    }

    private function resolveOrCreateCategory(Workspace $workspace, array $item, TransactionType $type): Category
    {
        // If category_uuid is provided, try to find it
        if (! empty($item['category_uuid'])) {
            $existing = Category::where('uuid', $item['category_uuid'])
                ->where('workspace_id', $workspace->id)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        // If category_name is provided, try to find a matching category
        if (! empty($item['category_name'])) {
            $existing = $workspace->categories()
                ->where('name', $item['category_name'])
                ->whereIn('type', [$type, TransactionType::Both])
                ->first();

            if ($existing) {
                return $existing;
            }

            // Create new category with the given name
            return Category::create([
                'uuid' => Str::orderedUuid()->toString(),
                'workspace_id' => $workspace->id,
                'name' => $item['category_name'],
                'type' => $type,
                'color' => '#6b7280',
                'icon' => null,
            ]);
        }

        // Fallback: get or create default "Importadas" category for this type
        $categoryType = $type;

        return $workspace->categories()->firstOrCreate(
            ['name' => 'Importadas', 'type' => $categoryType],
            [
                'uuid' => Str::orderedUuid()->toString(),
                'color' => '#6b7280',
                'icon' => null,
            ],
        );
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
}
