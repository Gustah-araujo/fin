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

class RecurrenceUntilDateBufferTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Workspace $workspace;

    protected Account $account;

    protected Category $expenseCategory;

    protected Category $incomeCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->members()->attach($this->user, ['role' => 'admin']);

        $this->account = Account::factory()->create([
            'workspace_id' => $this->workspace->id,
        ]);

        $this->expenseCategory = Category::factory()->create([
            'workspace_id' => $this->workspace->id,
            'type' => TransactionType::Expense,
        ]);

        $this->incomeCategory = Category::factory()->create([
            'workspace_id' => $this->workspace->id,
            'type' => TransactionType::Income,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function expenseRecurrencePayload(string $untilDate, int $bufferAhead = 12): array
    {
        return [
            'description' => 'Aluguel',
            'value' => 1500,
            'date' => '2026-06-15',
            'account_id' => $this->account->uuid,
            'category_id' => $this->expenseCategory->uuid,
            'is_recurring' => true,
            'frequency' => RecurrenceFrequency::Monthly->value,
            'frequency_day' => 15,
            'buffer_ahead' => $bufferAhead,
            'until_date' => $untilDate,
        ];
    }

    public function test_create_with_buffer_stops_at_until_date(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00');

        $response = $this->actingAs($this->user)
            ->post(route('transactions.store', $this->workspace), $this->expenseRecurrencePayload('2026-08-15'));

        $response->assertRedirect(route('transactions.index', $this->workspace));

        $recurrence = Recurrence::first();
        $this->assertNotNull($recurrence);
        $this->assertEquals('2026-08-15', $recurrence->until_date->toDateString());

        $transactions = Transaction::where('recurrence_id', $recurrence->id)
            ->whereNull('deleted_at')
            ->orderBy('date')
            ->get();

        // Monthly on day 15: occurrences on/after 2026-06-15 and <= 2026-08-15
        $this->assertCount(3, $transactions);
        $this->assertSame(['2026-06-15', '2026-07-15', '2026-08-15'],
            $transactions->pluck('date')->map(fn ($date) => $date->toDateString())->toArray()
        );

        foreach ($transactions as $transaction) {
            $this->assertFalse($transaction->date->gt(Carbon::parse('2026-08-15')));
            $this->assertEquals($this->workspace->id, $transaction->workspace_id);
            $this->assertNotNull($transaction->recurrence_id);
        }
    }

    public function test_buffer_job_does_not_generate_past_until_date(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00');

        $this->actingAs($this->user)
            ->post(route('transactions.store', $this->workspace), $this->expenseRecurrencePayload('2026-06-15', 3));

        $recurrence = Recurrence::first();
        $this->assertNotNull($recurrence);

        // Only today's occurrence fits before until_date (equal to until_date)
        $this->assertEquals(1, Transaction::where('recurrence_id', $recurrence->id)->count());

        // Two months pass
        Carbon::setTestNow('2026-08-15 10:00:00');

        ProcessRecurrencesJob::dispatchSync();

        $this->assertEquals(0, Transaction::where('recurrence_id', $recurrence->id)
            ->where('date', '>', '2026-06-15')
            ->count());

        $this->assertEquals(0, Transaction::where('recurrence_id', $recurrence->id)
            ->whereNull('deleted_at')
            ->whereDate('date', '>=', Carbon::today())
            ->count());
    }

    public function test_buffer_job_respects_until_date_when_buffer_would_exceed(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00');

        $this->actingAs($this->user)
            ->post(route('transactions.store', $this->workspace), $this->expenseRecurrencePayload('2026-08-15'));

        $recurrence = Recurrence::first();
        $this->assertEquals(3, Transaction::where('recurrence_id', $recurrence->id)->count());

        // Delete all future instances to simulate the buffer being empty
        Transaction::where('recurrence_id', $recurrence->id)
            ->where('date', '>=', Carbon::today()->toDateString())
            ->delete();

        ProcessRecurrencesJob::dispatchSync();

        $dates = Transaction::where('recurrence_id', $recurrence->id)
            ->whereNull('deleted_at')
            ->orderBy('date')
            ->pluck('date')
            ->map(fn ($date) => $date->toDateString())
            ->toArray();

        // Buffer_ahead is 12, but regeneration stops at until_date
        $this->assertSame(['2026-06-15', '2026-07-15', '2026-08-15'], $dates);
        $this->assertEquals(0, Transaction::where('recurrence_id', $recurrence->id)
            ->whereNull('deleted_at')
            ->where('date', '>', '2026-08-15')
            ->count());
    }

    public function test_income_recurrence_with_until_date_also_respected(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00');

        $response = $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), [
                'description' => 'Salário',
                'value' => 5000,
                'date' => '2026-06-15',
                'account_id' => $this->account->uuid,
                'category_id' => $this->incomeCategory->uuid,
                'is_recurring' => true,
                'frequency' => RecurrenceFrequency::Monthly->value,
                'frequency_day' => 15,
                'buffer_ahead' => 5,
                'until_date' => '2026-08-15',
            ]);

        $response->assertRedirect(route('incomes.index', $this->workspace));

        $recurrence = Recurrence::first();
        $this->assertNotNull($recurrence);
        $this->assertEquals('2026-08-15', $recurrence->until_date->toDateString());

        $transactions = Transaction::where('recurrence_id', $recurrence->id)
            ->whereNull('deleted_at')
            ->orderBy('date')
            ->get();

        $this->assertCount(3, $transactions);

        foreach ($transactions as $transaction) {
            $this->assertFalse($transaction->date->gt(Carbon::parse('2026-08-15')));
            $this->assertEquals($this->workspace->id, $transaction->workspace_id);
            $this->assertNotNull($transaction->recurrence_id);
        }
    }
}
