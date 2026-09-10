<?php

declare(strict_types=1);

namespace Tests\Feature\Recurrences;

use App\Enums\RecurrenceFrequency;
use App\Enums\TransactionType;
use App\Jobs\ProcessRecurrencesJob;
use App\Models\Account;
use App\Models\Category;
use App\Models\Recurrence;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurrenceBufferJobTest extends TestCase
{
    use RefreshDatabase;

    // AC4: Daily job replenishes buffer to exact configured count
    public function test_daily_job_fills_buffer_to_configured_count(): void
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

        // Create recurrence with buffer_ahead=5
        $startDate = Carbon::today()->subMonth();
        $this->post(route('incomes.store', $workspace), [
            'description' => 'Job Test Income',
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

        // Initially: 1 retroactive + 5 future = 6
        $this->assertEquals(6, Transaction::where('recurrence_id', $recurrence->id)->count());

        // Delete some future instances to simulate passage of time
        Transaction::where('recurrence_id', $recurrence->id)
            ->whereDate('date', '>=', Carbon::today())
            ->limit(3)
            ->delete();

        // Now only 2 future instances remain
        $this->assertEquals(2, Transaction::where('recurrence_id', $recurrence->id)
            ->whereNull('deleted_at')
            ->whereDate('date', '>=', Carbon::today())
            ->count());

        // Run the job
        ProcessRecurrencesJob::dispatchSync();

        // Buffer should be replenished to 5
        $this->assertEquals(5, Transaction::where('recurrence_id', $recurrence->id)
            ->whereNull('deleted_at')
            ->whereDate('date', '>=', Carbon::today())
            ->count());
    }

    // Job does not over-generate when buffer is full
    public function test_job_does_not_over_generate_when_buffer_is_full(): void
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

        $this->post(route('incomes.store', $workspace), [
            'description' => 'Full Buffer Test',
            'value' => 500,
            'date' => Carbon::today()->toDateString(),
            'account_id' => $account->uuid,
            'category_id' => $category->uuid,
            'is_recurring' => true,
            'frequency' => RecurrenceFrequency::Monthly->value,
            'frequency_day' => Carbon::today()->day,
            'buffer_ahead' => 3,
        ]);

        $recurrence = Recurrence::first();
        $initialCount = Transaction::where('recurrence_id', $recurrence->id)->count();

        // Run the job
        ProcessRecurrencesJob::dispatchSync();

        // Count should be the same (buffer was already full)
        $this->assertEquals($initialCount, Transaction::where('recurrence_id', $recurrence->id)->count());
    }

    // Job handles multiple recurrences independently
    public function test_job_handles_multiple_recurrences_independently(): void
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

        // Create first recurrence with buffer=3
        $this->post(route('incomes.store', $workspace), [
            'description' => 'First Recurrence',
            'value' => 1000,
            'date' => Carbon::today()->toDateString(),
            'account_id' => $account->uuid,
            'category_id' => $category->uuid,
            'is_recurring' => true,
            'frequency' => RecurrenceFrequency::Monthly->value,
            'frequency_day' => Carbon::today()->day,
            'buffer_ahead' => 3,
        ]);

        // Create second recurrence with buffer=5
        $this->post(route('incomes.store', $workspace), [
            'description' => 'Second Recurrence',
            'value' => 2000,
            'date' => Carbon::today()->toDateString(),
            'account_id' => $account->uuid,
            'category_id' => $category->uuid,
            'is_recurring' => true,
            'frequency' => RecurrenceFrequency::Monthly->value,
            'frequency_day' => Carbon::today()->day,
            'buffer_ahead' => 5,
        ]);

        $rec1 = Recurrence::where('description', 'First Recurrence')->first();
        $rec2 = Recurrence::where('description', 'Second Recurrence')->first();

        // Delete some future instances from first recurrence
        Transaction::where('recurrence_id', $rec1->id)
            ->whereDate('date', '>=', Carbon::today())
            ->limit(2)
            ->delete();

        // Run the job
        ProcessRecurrencesJob::dispatchSync();

        // First should be replenished to 3
        $this->assertEquals(3, Transaction::where('recurrence_id', $rec1->id)
            ->whereNull('deleted_at')
            ->whereDate('date', '>=', Carbon::today())
            ->count());

        // Second should still be 5 (untouched)
        $this->assertEquals(5, Transaction::where('recurrence_id', $rec2->id)
            ->whereNull('deleted_at')
            ->whereDate('date', '>=', Carbon::today())
            ->count());
    }
}
