<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionType;
use App\Facades\Ai;
use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

class ImportService
{
    /**
     * Parse CSV content into structured transactions via AI Facade.
     *
     * @return array<int, array{description: string, value: float, date: string, type: string, category_name: string}>
     */
    public function parseCsv(string $csvContent, string $type): array
    {
        return Ai::parse($csvContent, $type);
    }

    /**
     * Detect potential duplicates by comparing against existing transactions.
     * Match criteria: same value AND date within ±2 days.
     *
     * @param  array<int, array{description: string, value: float, date: string, type: string, category_name: string}>  $transactions
     * @return array<int, array{original: array<string, mixed>, is_duplicate: bool}>
     */
    public function detectDuplicates(Workspace $workspace, array $transactions, string $type): array
    {
        $transactionType = TransactionType::tryFrom($type);
        if ($transactionType === null) {
            return array_map(fn (array $t): array => ['original' => $t, 'is_duplicate' => false], $transactions);
        }

        $existing = $workspace->transactions()
            ->where('type', $transactionType)
            ->select('value', 'date')
            ->get();

        return array_map(function (array $t) use ($existing): array {
            $isDuplicate = $existing->contains(function ($e) use ($t): bool {
                $sameValue = (float) $e->value === (float) $t['value'];
                if (! $sameValue) {
                    return false;
                }

                $existingDate = $e->date->format('Y-m-d');
                $newDate = (string) $t['date'];
                $daysDiff = abs(strtotime($existingDate) - strtotime($newDate)) / 86400;

                return $daysDiff <= 2;
            });

            return ['original' => $t, 'is_duplicate' => $isDuplicate];
        }, $transactions);
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

            $data = [
                'description' => $item['description'],
                'value' => $item['value'],
                'date' => $item['date'],
                'type' => $transactionType->value,
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
            ];

            app(TransactionService::class)->create($workspace, $user, $data);
            $count++;
        }

        return $count;
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
