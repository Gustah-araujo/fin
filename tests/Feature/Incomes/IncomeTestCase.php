<?php

declare(strict_types=1);

namespace Tests\Feature\Incomes;

use App\Enums\WorkspaceRole;
use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

abstract class IncomeTestCase extends TestCase
{
    protected User $user;

    protected Workspace $workspace;

    protected Account $account;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->members()->attach($this->user, ['role' => WorkspaceRole::Admin->value]);

        $this->account = Account::factory()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        $this->category = Category::factory()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
            'type' => 'income',
        ]);
    }

    protected function validIncomeData(array $overrides = []): array
    {
        return array_merge([
            'description' => 'Salário',
            'value' => 5000,
            'date' => now()->format('Y-m-d'),
            'account_id' => $this->account->uuid,
            'category_id' => $this->category->uuid,
        ], $overrides);
    }
}
