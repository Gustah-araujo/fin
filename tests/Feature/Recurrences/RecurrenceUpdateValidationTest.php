<?php

declare(strict_types=1);

namespace Tests\Feature\Recurrences;

use App\Enums\WorkspaceRole;
use App\Models\Account;
use App\Models\Category;
use App\Models\Recurrence;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class RecurrenceUpdateValidationTest extends TestCase
{
    private User $user;

    private Workspace $workspace;

    private Account $account;

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
    }

    private function makeRecurrence(array $overrides = []): Recurrence
    {
        return Recurrence::factory()->create(array_merge([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'created_by' => $this->user->id,
        ], $overrides));
    }

    public function test_expense_recurrence_rejects_income_only_category(): void
    {
        $incomeCategory = Category::factory()->income()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        $recurrence = $this->makeRecurrence([
            'type' => 'expense',
            'category_id' => Category::factory()->expense()->create([
                'workspace_id' => $this->workspace->id,
                'created_by' => $this->user->id,
            ])->id,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('recurrences.update', [$this->workspace, $recurrence]), [
                'category_id' => $incomeCategory->uuid,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'category_id' => 'Esta categoria não aceita despesas.',
        ]);
    }

    public function test_income_recurrence_rejects_expense_only_category(): void
    {
        $expenseCategory = Category::factory()->expense()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        $recurrence = $this->makeRecurrence([
            'type' => 'income',
            'category_id' => Category::factory()->income()->create([
                'workspace_id' => $this->workspace->id,
                'created_by' => $this->user->id,
            ])->id,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('recurrences.update', [$this->workspace, $recurrence]), [
                'category_id' => $expenseCategory->uuid,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'category_id' => 'Esta categoria não aceita receitas.',
        ]);
    }
}
