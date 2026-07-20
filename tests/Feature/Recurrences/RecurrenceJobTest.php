<?php

declare(strict_types=1);

namespace Tests\Feature\Recurrences;

use App\Enums\RecurrenceStatus;
use App\Enums\TransactionType;
use App\Enums\WorkspaceRole;
use App\Jobs\ApplyRecurrenceScopeChangeJob;
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

class RecurrenceJobTest extends TestCase
{
    private User $user;
    private Workspace $workspace;
    private Account $account;
    private Category $category;

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

        $this->category = Category::factory()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
            'type' => 'income',
        ]);
    }

    // ─── ProcessRecurrencesJob ─────────────────────────────────────────

    public function test_process_recurrences_job_creates_transaction_for_due_recurrence(): void
    {
        $today = Carbon::today();

        $recurrence = Recurrence::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'type' => 'income',
            'frequency' => 'monthly',
            'frequency_day' => 15,
            'next_date' => $today->toDateString(),
            'start_date' => $today->copy()->subMonth()->toDateString(),
            'status' => RecurrenceStatus::Active,
        ]);

        (new ProcessRecurrencesJob())->handle(app(\App\Services\RecurrenceService::class));

        $transaction = Transaction::where('recurrence_id', $recurrence->id)->first();
        $this->assertNotNull($transaction);
        $this->assertEquals(TransactionType::Income, $transaction->type);
        $this->assertEquals($today->format('Y-m-d'), $transaction->date->format('Y-m-d'));
    }

    public function test_process_recurrences_job_does_not_duplicate_on_second_run(): void
    {
        $today = Carbon::today();

        $recurrence = Recurrence::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'type' => 'income',
            'frequency' => 'monthly',
            'frequency_day' => 15,
            'next_date' => $today->toDateString(),
            'start_date' => $today->copy()->subMonth()->toDateString(),
            'status' => RecurrenceStatus::Active,
        ]);

        $originalNextDate = $recurrence->next_date->toDateString();

        $service = app(\App\Services\RecurrenceService::class);

        // First run — generates the transaction and advances next_date
        (new ProcessRecurrencesJob())->handle($service);

        // Verify one transaction was created
        $this->assertEquals(1, Transaction::where('recurrence_id', $recurrence->id)->count());

        // Next_date should have advanced past the original
        $recurrence->refresh();
        $this->assertNotNull($recurrence->next_date);
        $this->assertTrue(
            $recurrence->next_date->gt(Carbon::parse($originalNextDate)),
            'next_date should have advanced past original date',
        );

        $nextDateAfterFirstRun = $recurrence->next_date->toDateString();

        // Second run — since next_date > today, no transaction should be generated
        (new ProcessRecurrencesJob())->handle($service);

        // Still only one transaction (no duplicate)
        $this->assertEquals(1, Transaction::where('recurrence_id', $recurrence->id)->count());

        // next_date should remain unchanged after second run
        $recurrence->refresh();
        $this->assertEquals($nextDateAfterFirstRun, $recurrence->next_date->toDateString());
    }

    public function test_process_recurrences_job_skips_paused_recurrences(): void
    {
        $today = Carbon::today();

        Recurrence::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'type' => 'income',
            'frequency' => 'monthly',
            'frequency_day' => 15,
            'next_date' => $today->toDateString(),
            'start_date' => $today->copy()->subMonth()->toDateString(),
            'status' => RecurrenceStatus::Paused,
        ]);

        (new ProcessRecurrencesJob())->handle(app(\App\Services\RecurrenceService::class));

        $this->assertEquals(0, Transaction::count());
    }

    public function test_process_recurrences_job_skips_recurrences_with_future_next_date(): void
    {
        Recurrence::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'type' => 'income',
            'frequency' => 'monthly',
            'frequency_day' => 15,
            'next_date' => Carbon::tomorrow()->toDateString(),
            'start_date' => Carbon::today()->subMonth()->toDateString(),
            'status' => RecurrenceStatus::Active,
        ]);

        (new ProcessRecurrencesJob())->handle(app(\App\Services\RecurrenceService::class));

        $this->assertEquals(0, Transaction::count());
    }

    // ─── ApplyRecurrenceScopeChangeJob ────────────────────────────────

    public function test_apply_recurrence_scope_change_job_updates_future_transactions(): void
    {
        $recurrence = Recurrence::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'description' => 'Original',
            'value' => 1000,
        ]);

        $current = Transaction::factory()->income()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'recurrence_id' => $recurrence->id,
            'description' => 'Original',
            'value' => 1000,
            'date' => Carbon::today()->toDateString(),
        ]);

        $future = Transaction::factory()->income()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'recurrence_id' => $recurrence->id,
            'description' => 'Original',
            'value' => 1000,
            'date' => Carbon::today()->addMonth()->toDateString(),
        ]);

        (new ApplyRecurrenceScopeChangeJob(
            operation: 'update',
            transactionUuid: $current->uuid,
            payload: [
                'description' => 'Updated',
                'value' => 2000,
            ],
            userId: $this->user->id,
        ))->handle(app(\App\Services\RecurrenceService::class));

        $current->refresh();
        $future->refresh();
        $recurrence->refresh();

        $this->assertEquals('Updated', $current->description);
        $this->assertEquals(2000, (float) $current->value);
        $this->assertEquals('Updated', $future->description);
        $this->assertEquals(2000, (float) $future->value);
        $this->assertEquals('Updated', $recurrence->description);
        $this->assertEquals(2000, (float) $recurrence->value);
    }

    public function test_apply_recurrence_scope_change_job_deletes_future_transactions_and_recurrence(): void
    {
        $recurrence = Recurrence::factory()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
        ]);

        $current = Transaction::factory()->income()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'recurrence_id' => $recurrence->id,
            'date' => Carbon::today()->toDateString(),
        ]);

        $future = Transaction::factory()->income()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'recurrence_id' => $recurrence->id,
            'date' => Carbon::today()->addMonth()->toDateString(),
        ]);

        $past = Transaction::factory()->income()->create([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'recurrence_id' => $recurrence->id,
            'date' => Carbon::today()->subMonth()->toDateString(),
        ]);

        (new ApplyRecurrenceScopeChangeJob(
            operation: 'delete',
            transactionUuid: $current->uuid,
        ))->handle(app(\App\Services\RecurrenceService::class));

        $this->assertSoftDeleted($current);
        $this->assertSoftDeleted($future);
        $this->assertSoftDeleted($recurrence);

        // Past transaction should remain
        $past->refresh();
        $this->assertNull($past->deleted_at);
    }

    // ─── Schedule ──────────────────────────────────────────────────────

    public function test_schedule_contains_process_recurrences_job(): void
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
        $events = $schedule->events();

        $found = false;
        foreach ($events as $event) {
            if (str_contains($event->command ?? $event->description ?? '', 'ProcessRecurrencesJob')) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'ProcessRecurrencesJob should be scheduled in console.php');
    }
}
