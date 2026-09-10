<?php

declare(strict_types=1);

namespace Tests\Feature\Recurrences;

use App\Enums\RecurrenceFrequency;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Recurrence;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurrencePropagateTest extends TestCase
{
    use RefreshDatabase;

    // AC3: Editing a recurrence with "propagate" updates all future instances
    public function test_updating_recurrence_with_propagate_updates_future_instances(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => 'admin']);

        $account = Account::factory()->create(['workspace_id' => $workspace->id]);
        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => TransactionType::Income,
        ]);

        $this->actingAs($user);

        $startDate = Carbon::today()->subMonth();
        $this->post(route('incomes.store', $workspace), [
            'description' => 'Original Description',
            'value' => 1000,
            'date' => $startDate->toDateString(),
            'account_id' => $account->uuid,
            'category_id' => $category->uuid,
            'is_recurring' => true,
            'frequency' => RecurrenceFrequency::Monthly->value,
            'frequency_day' => $startDate->day,
            'buffer_ahead' => 5,
        ]);

        $recurrence = Recurrence::first();

        // Update with propagate
        $response = $this->put(route('recurrences.update', [$workspace, $recurrence]), [
            'description' => 'Updated Description',
            'value' => 2000,
            'propagate_to_future' => true,
        ]);

        $response->assertRedirect(route('recurrences.index', $workspace));

        // All future instances should have the new description and value
        $futureTransactions = Transaction::where('recurrence_id', $recurrence->id)
            ->whereNull('deleted_at')
            ->whereDate('date', '>=', Carbon::today())
            ->get();

        $this->assertGreaterThan(0, $futureTransactions->count());

        foreach ($futureTransactions as $transaction) {
            $this->assertEquals('Updated Description', $transaction->description);
            $this->assertEquals(2000, (float) $transaction->value);
        }
    }

    // Updating recurrence without propagate leaves future instances unchanged
    public function test_updating_recurrence_without_propagate_leaves_future_instances_unchanged(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => 'admin']);

        $account = Account::factory()->create(['workspace_id' => $workspace->id]);
        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => TransactionType::Income,
        ]);

        $this->actingAs($user);

        $startDate = Carbon::today()->subMonth();
        $this->post(route('incomes.store', $workspace), [
            'description' => 'Original Description',
            'value' => 1000,
            'date' => $startDate->toDateString(),
            'account_id' => $account->uuid,
            'category_id' => $category->uuid,
            'is_recurring' => true,
            'frequency' => RecurrenceFrequency::Monthly->value,
            'frequency_day' => $startDate->day,
            'buffer_ahead' => 5,
        ]);

        $recurrence = Recurrence::first();

        // Update WITHOUT propagate
        $response = $this->put(route('recurrences.update', [$workspace, $recurrence]), [
            'description' => 'Updated Description',
            'value' => 2000,
        ]);

        $response->assertRedirect(route('recurrences.index', $workspace));

        // Future instances should still have original values
        $futureTransactions = Transaction::where('recurrence_id', $recurrence->id)
            ->whereNull('deleted_at')
            ->whereDate('date', '>=', Carbon::today())
            ->get();

        foreach ($futureTransactions as $transaction) {
            $this->assertEquals('Original Description', $transaction->description);
            $this->assertEquals(1000, (float) $transaction->value);
        }
    }
}
