<?php

declare(strict_types=1);

namespace Tests\Feature\Incomes;

use App\Enums\TransactionType;
use App\Enums\WorkspaceRole;
use App\Models\Account;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class IncomeTestCase extends TestCase
{
    use RefreshDatabase;

    protected function createWorkspaceWithMember(string $role = WorkspaceRole::Admin->value): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => $role]);

        return [$user, $workspace];
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

    protected function createIncomeCategory(Workspace $workspace, User $user): Category
    {
        return Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => TransactionType::Income->value,
        ]);
    }

    protected function createBothCategory(Workspace $workspace, User $user): Category
    {
        return Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => TransactionType::Both->value,
        ]);
    }

    protected function createExpenseCategory(Workspace $workspace, User $user): Category
    {
        return Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => TransactionType::Expense->value,
        ]);
    }

    protected function createTag(Workspace $workspace, User $user): Tag
    {
        return Tag::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);
    }

    protected function attachMember(Workspace $workspace, string $role = WorkspaceRole::Viewer->value): User
    {
        $user = User::factory()->create();
        $workspace->members()->attach($user, ['role' => $role]);

        return $user;
    }
}
