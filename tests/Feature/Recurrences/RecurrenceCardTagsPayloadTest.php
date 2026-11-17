<?php

declare(strict_types=1);

namespace Tests\Feature\Recurrences;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\Recurrence;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Tests\TestCase;

class RecurrenceCardTagsPayloadTest extends TestCase
{
    private User $user;

    private Workspace $workspace;

    private CreditCard $card;

    private Category $category;

    private Tag $tag;

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

        $this->tag = Tag::factory()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
            'name' => 'Assinatura',
            'color' => '#ef4444',
        ]);
    }

    private function createCardRecurrenceWithTags(): void
    {
        $today = Carbon::today()->format('Y-m-d');

        $this->actingAs($this->user)
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
                'tags' => [$this->tag->uuid],
            ])->assertRedirect();
    }

    public function test_creating_recurring_expense_on_credit_card_with_tags_generates_tagged_instances_and_redirects_to_listing(): void
    {
        $this->createCardRecurrenceWithTags();

        $recurrence = Recurrence::where('description', 'Netflix Cartão')->first();
        $this->assertNotNull($recurrence);
        $this->assertEquals($this->card->uuid, $recurrence->credit_card_id);
        $this->assertNull($recurrence->account_id);
        $this->assertEquals($this->workspace->id, $recurrence->workspace_id);

        $instances = Transaction::where('recurrence_id', $recurrence->id)
            ->whereNull('deleted_at')
            ->get();
        $this->assertNotEmpty($instances);

        foreach ($instances as $instance) {
            $this->assertEquals($this->card->id, $instance->credit_card_id);
            $this->assertNull($instance->account_id);
            $this->assertEquals($this->workspace->id, $instance->workspace_id);
        }

        $taggedInstance = $instances->first(fn (Transaction $instance) => $instance->tags()->whereKey($this->tag->id)->exists());
        $this->assertNotNull($taggedInstance, 'At least one generated card instance must carry the recurrence tag.');
    }

    public function test_transactions_listing_page_returns_tag_payload_with_color_for_recurring_card_instances(): void
    {
        $this->createCardRecurrenceWithTags();

        $recurrence = Recurrence::where('description', 'Netflix Cartão')->first();
        $this->assertNotNull($recurrence);

        $todayInstance = Transaction::where('recurrence_id', $recurrence->id)
            ->whereDate('date', Carbon::today())
            ->whereNull('deleted_at')
            ->first();
        $this->assertNotNull($todayInstance);

        $page = $this->actingAs($this->user)
            ->get(route('transactions.index', $this->workspace));

        $page->assertOk();
        $page->assertInertia(fn ($inertia) => $inertia->component('Transactions/Index'));

        $response = $this->actingAs($this->user)
            ->getJson(route('transactions.datatable', $this->workspace));

        $response->assertOk();

        $rows = collect($response->json('data'));
        $row = $rows->firstWhere('uuid', $todayInstance->uuid);
        $this->assertNotNull($row, 'Recurring card instance must appear in the transactions listing payload.');

        $tags = collect(Arr::wrap($row['tags'] ?? []));
        $this->assertCount(1, $tags, 'Recurring card instance must have exactly the synced tag.');

        $tagPayload = $tags->first();
        $this->assertSame($this->tag->uuid, $tagPayload['uuid']);
        $this->assertSame('Assinatura', $tagPayload['name']);
        $this->assertSame('#ef4444', $tagPayload['color']);
    }
}
