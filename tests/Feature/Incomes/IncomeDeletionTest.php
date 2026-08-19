<?php

declare(strict_types=1);

namespace Tests\Feature\Incomes;

use App\Models\Recurrence;
use App\Models\Transaction;

class IncomeDeletionTest extends IncomeTestCase
{
    private function makeRecurringSeries(): array
    {
        $recurrence = Recurrence::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'description' => 'Freelance',
        ]);

        $current = Transaction::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'type' => 'income',
            'description' => 'Freelance',
            'date' => now()->format('Y-m-d'),
            'recurrence_id' => $recurrence->id,
        ]);

        $past = Transaction::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'type' => 'income',
            'description' => 'Freelance',
            'date' => now()->subMonth()->format('Y-m-d'),
            'recurrence_id' => $recurrence->id,
        ]);

        $future = Transaction::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'type' => 'income',
            'description' => 'Freelance',
            'date' => now()->addMonth()->format('Y-m-d'),
            'recurrence_id' => $recurrence->id,
        ]);

        return [$recurrence, $past, $current, $future];
    }

    public function test_user_can_delete_avulsa_income(): void
    {
        $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData());

        $transaction = Transaction::where('description', 'Salário')->first();

        $this->actingAs($this->user)
            ->delete(route('incomes.destroy', [$this->workspace, $transaction]));

        $this->assertNotNull($transaction->refresh()->deleted_at);
    }

    public function test_delete_scope_future_soft_deletes_current_future_and_recurrence(): void
    {
        [$recurrence, $past, $current, $future] = $this->makeRecurringSeries();

        $this->actingAs($this->user)
            ->delete(route('incomes.destroy', [$this->workspace, $current]), [
                'scope' => 'future',
            ]);

        $this->assertNotNull($current->refresh()->deleted_at);
        $this->assertNotNull($future->refresh()->deleted_at);
        $this->assertNotNull($recurrence->refresh()->deleted_at);
        $this->assertNull($past->refresh()->deleted_at);
    }

    public function test_delete_scope_single_only_touches_current(): void
    {
        [$recurrence, , $current] = $this->makeRecurringSeries();

        $this->actingAs($this->user)
            ->delete(route('incomes.destroy', [$this->workspace, $current]), [
                'scope' => 'single',
            ]);

        $this->assertNotNull($current->refresh()->deleted_at);
        $this->assertNull($recurrence->refresh()->deleted_at);
    }
}
