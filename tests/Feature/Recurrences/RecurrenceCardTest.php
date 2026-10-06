<?php

declare(strict_types=1);

namespace Tests\Feature\Recurrences;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\CreditCardBill;
use App\Models\Recurrence;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Services\BillService;
use Carbon\Carbon;
use Tests\TestCase;

class RecurrenceCardTest extends TestCase
{
    private User $user;

    private Workspace $workspace;

    private Category $category;

    private CreditCard $card;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->members()->attach($this->user, ['role' => 'admin']);

        $this->card = CreditCard::factory()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
            'closing_day' => 1,
            'due_day' => 10,
            'credit_limit' => 5000,
            'available_limit' => 5000,
        ]);

        $this->category = Category::factory()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
            'type' => TransactionType::Expense->value,
        ]);
    }

    public function test_card_recurrence_creates_buffer_in_bills(): void
    {
        // Create a card recurrence via the store endpoint
        $today = Carbon::today()->format('Y-m-d');

        $response = $this->actingAs($this->user)
            ->post(route('transactions.store', $this->workspace), [
                'description' => 'Netflix Cartão',
                'value' => 39.90,
                'date' => $today,
                'credit_card_id' => $this->card->uuid,
                'category_id' => $this->category->uuid,
                'is_recurring' => true,
                'frequency' => 'monthly',
                'frequency_day' => (int) Carbon::today()->format('d'),
                'buffer_ahead' => 12,
            ]);

        $response->assertRedirect();

        $recurrence = Recurrence::where('description', 'Netflix Cartão')->first();
        $this->assertNotNull($recurrence);
        $this->assertEquals($this->card->uuid, $recurrence->credit_card_id);
        $this->assertNull($recurrence->account_id);

        $transactions = Transaction::where('recurrence_id', $recurrence->id)
            ->whereNull('deleted_at')
            ->get();
        $this->assertCount(12, $transactions);

        foreach ($transactions as $tx) {
            $this->assertNotNull($tx->credit_card_bill_id);
            $this->assertEquals($this->card->id, $tx->credit_card_id);
            $this->assertNull($tx->account_id);
        }
    }

    public function test_card_recurrence_rejected_with_installments(): void
    {
        $today = Carbon::today()->format('Y-m-d');

        $response = $this->actingAs($this->user)
            ->post(route('transactions.store', $this->workspace), [
                'description' => 'Recorrência parcelada',
                'value' => 100,
                'date' => $today,
                'credit_card_id' => $this->card->uuid,
                'category_id' => $this->category->uuid,
                'is_recurring' => true,
                'frequency' => 'monthly',
                'frequency_day' => (int) Carbon::today()->format('d'),
                'installments' => 3,
            ]);

        $response->assertSessionHasErrors(['installments']);
    }

    public function test_card_recurrence_rejected_when_paid_bill_collision(): void
    {
        // Use a card with closing_day=15 so that a start_date on the 6th
        // falls within the same calendar month's billing period.
        $card = CreditCard::factory()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
            'closing_day' => 15,
            'due_day' => 20,
            'credit_limit' => 5000,
            'available_limit' => 5000,
        ]);

        // Create a bill in the past, close it, and pay it
        $pastDate = Carbon::now()->subMonthsNoOverflow(3);
        $bill = CreditCardBill::factory()->closed()->create([
            'credit_card_id' => $card->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
            'period_year' => $pastDate->year,
            'period_month' => $pastDate->month,
            'total_amount' => 100,
        ]);

        // Pay it
        $account = Account::factory()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
            'initial_balance' => 5000,
            'current_balance' => 5000,
        ]);

        app(BillService::class)->payBill($bill, $account, $this->user);

        // Try to create a recurrence starting 3 months ago (collides with paid bill)
        $startDate = $pastDate->format('Y-m-d');

        $response = $this->actingAs($this->user)
            ->post(route('transactions.store', $this->workspace), [
                'description' => 'Recorrência colisão',
                'value' => 50,
                'date' => $startDate,
                'credit_card_id' => $card->uuid,
                'category_id' => $this->category->uuid,
                'is_recurring' => true,
                'frequency' => 'monthly',
                'frequency_day' => 1,
            ]);

        $response->assertSessionHasErrors();
    }

    public function test_card_recurrence_in_recurrences_list(): void
    {
        // Create a card recurrence
        $today = Carbon::today()->format('Y-m-d');

        $response = $this->actingAs($this->user)
            ->post(route('transactions.store', $this->workspace), [
                'description' => 'Listável Cartão',
                'value' => 49.90,
                'date' => $today,
                'credit_card_id' => $this->card->uuid,
                'category_id' => $this->category->uuid,
                'is_recurring' => true,
                'frequency' => 'monthly',
                'frequency_day' => (int) Carbon::today()->format('d'),
            ]);

        $response->assertRedirect();

        $recurrence = Recurrence::where('description', 'Listável Cartão')->first();
        $this->assertNotNull($recurrence);

        $this->assertDatabaseHas('recurrences', [
            'description' => 'Listável Cartão',
            'credit_card_id' => $this->card->uuid,
            'workspace_id' => $this->workspace->id,
        ]);
    }
}
