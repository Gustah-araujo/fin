<?php

declare(strict_types=1);

namespace Tests\Feature\Incomes;

use App\Models\Recurrence;
use App\Models\Transaction;

class IncomeFilteringTest extends IncomeTestCase
{
    public function test_filter_by_search(): void
    {
        $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData(['description' => 'Salário Base']));
        $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData(['description' => 'Freela Extra']));

        $response = $this->actingAs($this->user)
            ->get(route('incomes.index', [$this->workspace, 'search' => 'Salário']));

        $response->assertInertia(fn ($page) => $page
            ->component('Incomes/Index', false)
            ->has('incomes.data', 1)
            ->where('incomes.data.0.description', 'Salário Base')
        );
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
            ->get(route('incomes.index', [$this->workspace, 'origin' => 'recurring']));

        $response->assertInertia(fn ($page) => $page
            ->component('Incomes/Index', false)
            ->has('incomes.data', 1)
            ->where('incomes.data.0.description', 'Recorrente')
        );
    }

    public function test_filter_by_status_paid(): void
    {
        $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData(['description' => 'Prevista']));

        $paid = Transaction::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'type' => 'income',
            'description' => 'Recebida',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('incomes.index', [$this->workspace, 'status' => 'paid']));

        $response->assertInertia(fn ($page) => $page
            ->component('Incomes/Index', false)
            ->has('incomes.data', 1)
            ->where('incomes.data.0.description', 'Recebida')
        );
    }
}
