<?php

declare(strict_types=1);

namespace Tests\Feature\CardExpenses;

use App\Enums\TransactionType;
use App\Enums\WorkspaceRole;
use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class CardExpenseTestCase extends TestCase
{
    use RefreshDatabase;

    protected function createWorkspaceWithMember(string $role = 'admin'): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => $role]);

        return [$user, $workspace];
    }

    protected function createCard(Workspace $workspace, User $user, array $overrides = []): CreditCard
    {
        return CreditCard::factory()->create(array_merge([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'closing_day' => 1,
            'due_day' => 10,
            'credit_limit' => 5000,
            'available_limit' => 5000,
        ], $overrides));
    }

    protected function createExpenseCategory(Workspace $workspace, User $user): Category
    {
        return Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => TransactionType::Expense->value,
        ]);
    }

    protected function createAccount(Workspace $workspace, User $user, float $balance = 5000): Account
    {
        return Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'initial_balance' => $balance,
            'current_balance' => $balance,
        ]);
    }
}
