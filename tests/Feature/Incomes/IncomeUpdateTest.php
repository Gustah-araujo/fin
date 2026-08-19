<?php

declare(strict_types=1);

namespace Tests\Feature\Incomes;

use App\Models\Recurrence;
use App\Models\Transaction;

class IncomeUpdateTest extends IncomeTestCase
{
    private function makeRecurringSeries(): array
    {
        $recurrence = Recurrence::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'description' => 'Freelance',
            'value' => 1000,
        ]);

        $current = Transaction::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'type' => 'income',
            'description' => 'Freelance',
            'value' => 1000,
            'date' => now()->format('Y-m-d'),
            'recurrence_id' => $recurrence->id,
        ]);

        $future = Transaction::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'type' => 'income',
            'description' => 'Freelance',
            'value' => 1000,
            'date' => now()->addMonth()->format('Y-m-d'),
            'recurrence_id' => $recurrence->id,
        ]);

        return [$recurrence, $current, $future];
    }

    public function test_user_can_update_avulsa_income(): void
    {
        $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData());

        $transaction = Transaction::where('description', 'Salário')->first();

        $response = $this->actingAs($this->user)
            ->put(route('incomes.update', [$this->workspace, $transaction]), [
                'description' => 'Salário Atualizado',
            ]);

        $response->assertRedirect(route('incomes.index', $this->workspace));

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'description' => 'Salário Atualizado',
        ]);
    }

    public function test_update_scope_single_only_touches_current_instance(): void
    {
        [$recurrence, $current] = $this->makeRecurringSeries();

        $this->actingAs($this->user)
            ->put(route('incomes.update', [$this->workspace, $current]), [
                'description' => 'Freelance Editado',
                'scope' => 'single',
            ]);

        $this->assertEquals('Freelance Editado', $current->refresh()->description);
        $this->assertEquals('Freelance', $recurrence->refresh()->description);
    }

    public function test_update_scope_future_updates_current_recurrence_and_future(): void
    {
        [$recurrence, $current, $future] = $this->makeRecurringSeries();

        $this->actingAs($this->user)
            ->put(route('incomes.update', [$this->workspace, $current]), [
                'value' => 6000,
                'scope' => 'future',
            ]);

        $this->assertEquals(6000, (float) $current->refresh()->value);
        $this->assertEquals(6000, (float) $future->refresh()->value);
        $this->assertEquals(6000, (float) $recurrence->refresh()->value);
    }

    public function test_edit_page_returns_transaction(): void
    {
        $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData());

        $transaction = Transaction::where('description', 'Salário')->first();

        $response = $this->actingAs($this->user)
            ->get(route('incomes.edit', [$this->workspace, $transaction]));

        $response->assertInertia(fn ($page) => $page
            ->component('Incomes/Edit', false)
            ->where('transaction.uuid', $transaction->uuid)
        );
    }
}
