<?php

declare(strict_types=1);

namespace Tests\Feature\Recurrences;

use App\Enums\RecurrenceFrequency;
use App\Enums\RecurrenceStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Recurrence;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class RecurrenceModelTest extends TestCase
{
    public function test_factory_can_create_recurrence(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $account = Account::factory()->create(['workspace_id' => $workspace->id]);
        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => 'income',
        ]);

        $recurrence = Recurrence::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
        ]);

        $this->assertNotNull($recurrence);
        $this->assertInstanceOf(Recurrence::class, $recurrence);
        $this->assertDatabaseHas('recurrences', [
            'id' => $recurrence->id,
            'uuid' => $recurrence->uuid,
        ]);
    }

    public function test_recurrence_binds_on_uuid(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $account = Account::factory()->create(['workspace_id' => $workspace->id]);
        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => 'income',
        ]);

        $recurrence = Recurrence::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
        ]);

        $this->assertEquals('uuid', $recurrence->getRouteKeyName());
        $this->assertIsString($recurrence->uuid);
    }

    public function test_recurrence_belongs_to_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $account = Account::factory()->create(['workspace_id' => $workspace->id]);
        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => 'income',
        ]);

        $recurrence = Recurrence::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
        ]);

        $this->assertInstanceOf(Workspace::class, $recurrence->workspace);
        $this->assertTrue($recurrence->workspace->is($workspace));
    }

    public function test_recurrence_belongs_to_account(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $account = Account::factory()->create(['workspace_id' => $workspace->id]);
        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => 'income',
        ]);

        $recurrence = Recurrence::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
        ]);

        $this->assertInstanceOf(Account::class, $recurrence->account);
        $this->assertTrue($recurrence->account->is($account));
    }

    public function test_recurrence_belongs_to_category(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $account = Account::factory()->create(['workspace_id' => $workspace->id]);
        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => 'income',
        ]);

        $recurrence = Recurrence::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
        ]);

        $this->assertInstanceOf(Category::class, $recurrence->category);
        $this->assertTrue($recurrence->category->is($category));
    }

    public function test_recurrence_belongs_to_creator(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $account = Account::factory()->create(['workspace_id' => $workspace->id]);
        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => 'income',
        ]);

        $recurrence = Recurrence::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
        ]);

        $this->assertInstanceOf(User::class, $recurrence->creator);
        $this->assertTrue($recurrence->creator->is($user));
    }

    public function test_recurrence_has_many_transactions(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $account = Account::factory()->create(['workspace_id' => $workspace->id]);
        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => 'income',
        ]);

        $recurrence = Recurrence::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
        ]);

        $transaction = Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'type' => 'income',
        ]);

        $this->assertInstanceOf(Transaction::class, $recurrence->transactions()->make());
        $this->assertCount(0, $recurrence->transactions);
    }

    public function test_recurrence_morph_to_many_tags(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $account = Account::factory()->create(['workspace_id' => $workspace->id]);
        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => 'income',
        ]);

        $recurrence = Recurrence::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
        ]);

        $tag = Tag::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $recurrence->tags()->attach($tag);

        $this->assertInstanceOf(Tag::class, $recurrence->tags()->first());
        $this->assertCount(1, $recurrence->tags);
    }

    public function test_recurrence_has_enum_casts(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $account = Account::factory()->create(['workspace_id' => $workspace->id]);
        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => 'income',
        ]);

        $recurrence = Recurrence::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'type' => 'income',
            'frequency' => 'monthly',
            'status' => 'active',
        ]);

        $this->assertInstanceOf(TransactionType::class, $recurrence->type);
        $this->assertEquals(TransactionType::Income, $recurrence->type);

        $this->assertInstanceOf(RecurrenceFrequency::class, $recurrence->frequency);
        $this->assertEquals(RecurrenceFrequency::Monthly, $recurrence->frequency);

        $this->assertInstanceOf(RecurrenceStatus::class, $recurrence->status);
        $this->assertEquals(RecurrenceStatus::Active, $recurrence->status);
    }

    public function test_recurrence_soft_deletes(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $account = Account::factory()->create(['workspace_id' => $workspace->id]);
        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => 'income',
        ]);

        $recurrence = Recurrence::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
        ]);

        $uuid = $recurrence->uuid;

        $recurrence->delete();

        $this->assertSoftDeleted($recurrence);
        $this->assertDatabaseHas('recurrences', [
            'uuid' => $uuid,
        ]);

        $this->assertNull(Recurrence::find($recurrence->id));
        $this->assertNotNull(Recurrence::withTrashed()->find($recurrence->id));
    }
}
