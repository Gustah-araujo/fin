<?php

declare(strict_types=1);

namespace Tests\Feature\Recurrences;

use App\Enums\WorkspaceRole;
use App\Http\Resources\RecurrenceResource;
use App\Http\Resources\TransactionResource;
use App\Models\Account;
use App\Models\Category;
use App\Models\Recurrence;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class RecurrenceResourceTest extends TestCase
{
    // TransactionResource tests (can run independently)

    public function test_transaction_resource_includes_is_paid_true_when_paid_at_is_set(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'income',
        ]);

        $transaction = Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'type' => 'income',
            'paid_at' => now(),
            'created_by' => $user->id,
        ]);

        $resource = TransactionResource::make($transaction)->resolve();

        $this->assertTrue($resource['is_paid']);
    }

    public function test_transaction_resource_includes_is_paid_false_when_paid_at_is_null(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'income',
        ]);

        $transaction = Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'type' => 'income',
            'paid_at' => null,
            'created_by' => $user->id,
        ]);

        $resource = TransactionResource::make($transaction)->resolve();

        $this->assertFalse($resource['is_paid']);
    }

    public function test_transaction_resource_omits_recurrence_fields_when_not_loaded(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'income',
        ]);

        $transaction = Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'type' => 'income',
            'created_by' => $user->id,
        ]);

        $resource = TransactionResource::make($transaction)->resolve();

        $this->assertArrayNotHasKey('recurrence_id', $resource);
        $this->assertArrayNotHasKey('recurrence', $resource);
    }

    // RecurrenceResource shape tests (need T3 model + factory + migration)

    public function test_recurrence_resource_returns_expected_shape(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'income',
        ]);

        $recurrence = Recurrence::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
        ]);

        $recurrence->load(['account', 'category', 'tags']);

        $resource = RecurrenceResource::make($recurrence)->resolve();

        $this->assertArrayHasKey('id', $resource);
        $this->assertArrayHasKey('description', $resource);
        $this->assertArrayHasKey('value', $resource);
        $this->assertArrayHasKey('frequency', $resource);
        $this->assertArrayHasKey('frequency_day', $resource);
        $this->assertArrayHasKey('start_date', $resource);
        $this->assertArrayHasKey('until_date', $resource);
        $this->assertArrayHasKey('next_date', $resource);
        $this->assertArrayHasKey('status', $resource);
        $this->assertArrayHasKey('account', $resource);
        $this->assertArrayHasKey('category', $resource);
        $this->assertArrayHasKey('tags', $resource);
        $this->assertArrayHasKey('created_at', $resource);
    }

    public function test_recurrence_resource_returns_correct_types(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'income',
        ]);

        $recurrence = Recurrence::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
        ]);

        $recurrence->load(['account', 'category', 'tags']);

        $resource = RecurrenceResource::make($recurrence)->resolve();

        $this->assertIsString($resource['id']);
        $this->assertIsString($resource['description']);
        $this->assertIsFloat($resource['value']);
        $this->assertIsString($resource['frequency']);
        $this->assertIsInt($resource['frequency_day']);
        $this->assertIsString($resource['start_date']);
        $this->assertIsString($resource['status']);
        $this->assertIsString($resource['created_at']);
    }

    public function test_recurrence_resource_until_date_and_next_date_are_nullable(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'income',
        ]);

        $recurrence = Recurrence::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'until_date' => null,
            'next_date' => null,
            'created_by' => $user->id,
        ]);

        $recurrence->load(['account', 'category', 'tags']);

        $resource = RecurrenceResource::make($recurrence)->resolve();

        $this->assertNull($resource['until_date']);
        $this->assertNull($resource['next_date']);
    }

    public function test_transaction_resource_includes_recurrence_fields_when_loaded(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
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
            'recurrence_id' => $recurrence->id,
            'type' => 'income',
            'created_by' => $user->id,
        ]);

        $transaction->load('recurrence');
        $resource = TransactionResource::make($transaction)->resolve();

        $this->assertArrayHasKey('recurrence_id', $resource);
        $this->assertIsString($resource['recurrence_id']);
        $this->assertEquals($recurrence->uuid, $resource['recurrence_id']);
        $this->assertArrayHasKey('recurrence', $resource);

        $recurrenceResource = $resource['recurrence'];
        $this->assertInstanceOf(RecurrenceResource::class, $recurrenceResource);
        $this->assertEquals($recurrence->uuid, $recurrenceResource->resolve()['id']);
    }
}
