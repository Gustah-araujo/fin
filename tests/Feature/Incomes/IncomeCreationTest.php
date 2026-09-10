<?php

declare(strict_types=1);

namespace Tests\Feature\Incomes;

use App\Models\Recurrence;
use App\Models\Tag;
use App\Models\Transaction;
use Carbon\Carbon;

class IncomeCreationTest extends IncomeTestCase
{
    public function test_user_can_create_avulsa_income(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData());

        $response->assertRedirect(route('incomes.index', $this->workspace));

        $this->assertDatabaseHas('transactions', [
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'type' => 'income',
            'description' => 'Salário',
            'value' => 5000,
            'paid_at' => null,
            'recurrence_id' => null,
        ]);
    }

    public function test_user_can_create_recurring_income_with_start_date_today(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData([
                'is_recurring' => true,
                'frequency' => 'monthly',
                'frequency_day' => 5,
            ]));

        $response->assertRedirect(route('incomes.index', $this->workspace));

        $this->assertDatabaseHas('recurrences', [
            'workspace_id' => $this->workspace->id,
            'description' => 'Salário',
            'frequency' => 'monthly',
            'frequency_day' => 5,
        ]);

        $recurrence = Recurrence::where('description', 'Salário')->first();

        $this->assertNotNull($recurrence);
        $this->assertDatabaseHas('transactions', [
            'recurrence_id' => $recurrence->id,
            'type' => 'income',
            'description' => 'Salário',
        ]);
    }

    public function test_user_can_create_recurring_income_with_future_start_date(): void
    {
        $futureDate = now()->addMonth()->format('Y-m-d');

        $response = $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData([
                'date' => $futureDate,
                'is_recurring' => true,
                'frequency' => 'monthly',
                'frequency_day' => 5,
            ]));

        $response->assertRedirect(route('incomes.index', $this->workspace));

        $recurrence = Recurrence::where('description', 'Salário')->first();

        $this->assertNotNull($recurrence);

        // Buffer instances are generated immediately (default buffer_ahead = 12)
        $this->assertEquals(12, Transaction::where('workspace_id', $this->workspace->id)
            ->where('recurrence_id', $recurrence->id)
            ->count());

        // next_date is advanced past the generated buffer
        $this->assertNotNull($recurrence->next_date);
        $this->assertTrue($recurrence->next_date->gt(Carbon::parse($futureDate)));
    }

    public function test_income_stores_tags_correctly(): void
    {
        $tag = Tag::factory()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
            'name' => 'Prioridade',
        ]);

        $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData([
                'tags' => [$tag->uuid],
            ]));

        $transaction = Transaction::where('description', 'Salário')->first();

        $this->assertNotNull($transaction);
        $this->assertEquals(1, $transaction->tags()->count());
    }

    public function test_recurring_income_syncs_tags_to_recurrence_and_transaction(): void
    {
        $tag = Tag::factory()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
            'name' => 'Fixo',
        ]);

        $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData([
                'is_recurring' => true,
                'frequency' => 'monthly',
                'frequency_day' => 5,
                'tags' => [$tag->uuid],
            ]));

        $recurrence = Recurrence::where('description', 'Salário')->first();
        $transaction = Transaction::where('description', 'Salário')->first();

        $this->assertEquals(1, $recurrence->tags()->count());
        $this->assertEquals(1, $transaction->tags()->count());
    }
}
