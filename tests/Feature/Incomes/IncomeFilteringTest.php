<?php

declare(strict_types=1);

namespace Tests\Feature\Incomes;

use App\Models\Recurrence;
use App\Models\Transaction;

class IncomeFilteringTest extends IncomeTestCase
{
    public function test_filter_by_description(): void
    {
        $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData(['description' => 'Salário Base']));
        $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData(['description' => 'Freela Extra']));

        $response = $this->actingAs($this->user)
            ->getJson(route('incomes.datatable', [$this->workspace, 'description' => 'Salário']));

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'Salário Base');
    }

    public function test_filter_by_origin_recurring(): void
    {
        $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData(['description' => 'Avulsa']));

        $recurrence = Recurrence::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'description' => 'Recorrente',
        ]);

        Transaction::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'type' => 'income',
            'description' => 'Recorrente',
            'recurrence_id' => $recurrence->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('incomes.datatable', [$this->workspace, 'origin' => 'recurring']));

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'Recorrente');
    }

    public function test_filter_by_status_paid(): void
    {
        $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData(['description' => 'Prevista']));

        Transaction::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'type' => 'income',
            'description' => 'Recebida',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('incomes.datatable', [$this->workspace, 'status' => 'paid']));

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'Recebida');
    }
}
