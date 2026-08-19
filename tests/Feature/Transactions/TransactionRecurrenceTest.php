<?php

declare(strict_types=1);

namespace Tests\Feature\Transactions;

use App\Enums\WorkspaceRole;
use App\Models\Account;
use App\Models\Category;
use App\Models\Recurrence;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class TransactionRecurrenceTest extends TestCase
{
    public function test_transaction_can_be_linked_to_recurrence(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
        ]);

        $recurrence = Recurrence::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
        ]);

        $transaction = Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'type' => 'expense',
            'recurrence_id' => $recurrence->id,
        ]);

        $this->assertInstanceOf(Recurrence::class, $transaction->recurrence);
        $this->assertEquals($recurrence->id, $transaction->recurrence->id);
    }
}
