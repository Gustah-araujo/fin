<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AiParserInterface;
use App\Enums\TransactionType;
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
        return app(AiParserInterface::class)->parseFile($workspace, $file);
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

            $tags = $item['tags'] ?? null;

            if (is_array($tags) && $tags !== []) {
                app(TransactionService::class)->syncTags(
                    $transaction,
                    array_values(array_filter($tags, is_string(...))),
                );
            }

            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveOrCreateCategory(Workspace $workspace, array $item, TransactionType $type): Category
    {
        // If category_uuid is provided, try to find it
        $categoryUuid = $item['category_uuid'] ?? null;

        if (is_string($categoryUuid) && $categoryUuid !== '') {
            $existing = Category::where('uuid', $categoryUuid)
                ->where('workspace_id', $workspace->id)
                ->first();

            if ($existing instanceof Category) {
                return $existing;
            }
        }

        // If category_name is provided, try to find a matching category
        $categoryName = $item['category_name'] ?? null;

        if (is_string($categoryName) && $categoryName !== '') {
            $existing = $workspace->categories()
                ->where('name', $categoryName)
                ->whereIn('type', [$type, TransactionType::Both])
                ->first();

            if ($existing instanceof Category) {
                return $existing;
            }

            // Create new category with the given name
            return Category::create([
                'uuid' => Str::orderedUuid()->toString(),
                'workspace_id' => $workspace->id,
                'name' => $categoryName,
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
