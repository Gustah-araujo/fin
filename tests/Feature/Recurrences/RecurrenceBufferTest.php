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
use App\Services\RecurrenceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurrenceBufferTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Workspace $workspace;

    protected Account $account;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->members()->attach($this->user, ['role' => 'admin']);

        $this->account = Account::factory()->create([
            'workspace_id' => $this->workspace->id,
        ]);

        $this->category = Category::factory()->create([
            'workspace_id' => $this->workspace->id,
            'type' => TransactionType::Income,
        ]);
    }

    // AC1: Creating a monthly recurrence with start_date 3 months ago and buffer=12
    // generates 3 past + 12 future transactions
    public function test_creating_recurrence_with_past_start_date_generates_retroactive_and_buffer_instances(): void
    {
        $this->actingAs($this->user);

        $startDate = Carbon::today()->subMonths(3);
        $response = $this->post(route('incomes.store', $this->workspace), [
            'description' => 'Salário Test',
            'value' => 5000,
            'date' => $startDate->toDateString(),
            'account_id' => $this->account->uuid,
            'category_id' => $this->category->uuid,
            'is_recurring' => true,
            'frequency' => RecurrenceFrequency::Monthly->value,
            'frequency_day' => $startDate->day,
            'buffer_ahead' => 12,
        ]);

        $response->assertRedirect(route('incomes.index', $this->workspace));

        $recurrence = Recurrence::first();
        $this->assertNotNull($recurrence);
        $this->assertEquals(12, $recurrence->buffer_ahead);

        // 3 retroactive + 12 future = 15 total
        $this->assertEquals(15, Transaction::where('recurrence_id', $recurrence->id)->count());
    }

    // AC2: Buffer count (max 50) only considers future instances (date >= today)
    public function test_buffer_count_excludes_past_instances(): void
    {
        $this->actingAs($this->user);

        $startDate = Carbon::today()->subMonths(6);
        $this->post(route('incomes.store', $this->workspace), [
            'description' => 'Renda Mensal',
            'value' => 3000,
            'date' => $startDate->toDateString(),
            'account_id' => $this->account->uuid,
            'category_id' => $this->category->uuid,
            'is_recurring' => true,
            'frequency' => RecurrenceFrequency::Monthly->value,
            'frequency_day' => $startDate->day,
            'buffer_ahead' => 10,
        ]);

        $recurrence = Recurrence::first();

        // 6 retroactive + 10 future = 16 total
        $this->assertEquals(16, Transaction::where('recurrence_id', $recurrence->id)->count());

        // But countFutureInstances only counts future (>= today)
        $service = app(RecurrenceService::class);
        $this->assertEquals(10, $service->countFutureInstances($recurrence));
    }

    // Creating recurrence with future start_date generates only buffer instances
    public function test_creating_recurrence_with_future_start_date_generates_only_buffer_instances(): void
    {
        $this->actingAs($this->user);

        $startDate = Carbon::today()->addMonth();
        $this->post(route('incomes.store', $this->workspace), [
            'description' => 'Future Income',
            'value' => 2000,
            'date' => $startDate->toDateString(),
            'account_id' => $this->account->uuid,
            'category_id' => $this->category->uuid,
            'is_recurring' => true,
            'frequency' => RecurrenceFrequency::Monthly->value,
            'frequency_day' => $startDate->day,
            'buffer_ahead' => 5,
        ]);

        $recurrence = Recurrence::first();

        // Only 5 future instances, no retroactive
        $this->assertEquals(5, Transaction::where('recurrence_id', $recurrence->id)->count());
    }

    // Buffer max is 50
    public function test_buffer_max_is_50(): void
    {
        $this->actingAs($this->user);

        $response = $this->post(route('incomes.store', $this->workspace), [
            'description' => 'Test Max Buffer',
            'value' => 100,
            'date' => Carbon::today()->toDateString(),
            'account_id' => $this->account->uuid,
            'category_id' => $this->category->uuid,
            'is_recurring' => true,
            'frequency' => RecurrenceFrequency::Monthly->value,
            'frequency_day' => Carbon::today()->day,
            'buffer_ahead' => 51,
        ]);

        $response->assertSessionHasErrors('buffer_ahead');
    }

    // Buffer min is 1
    public function test_buffer_min_is_1(): void
    {
        $this->actingAs($this->user);

        $response = $this->post(route('incomes.store', $this->workspace), [
            'description' => 'Test Min Buffer',
            'value' => 100,
            'date' => Carbon::today()->toDateString(),
            'account_id' => $this->account->uuid,
            'category_id' => $this->category->uuid,
            'is_recurring' => true,
            'frequency' => RecurrenceFrequency::Monthly->value,
            'frequency_day' => Carbon::today()->day,
            'buffer_ahead' => 0,
        ]);

        $response->assertSessionHasErrors('buffer_ahead');
    }
}
