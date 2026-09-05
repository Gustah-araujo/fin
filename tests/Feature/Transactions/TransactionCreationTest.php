<?php

declare(strict_types=1);

namespace Tests\Feature\Transactions;

use App\Enums\WorkspaceRole;
use App\Models\Account;
use App\Models\Category;
use App\Models\Recurrence;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Tests\TestCase;

class TransactionCreationTest extends TestCase
{
    public function test_user_can_create_transaction(): void
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
            'type' => 'expense',
        ]);

        $response = $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Compra no mercado',
                'value' => 150.75,
                'date' => '2026-07-10',
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
            ]);

        $response->assertRedirect(route('transactions.index', $workspace));

        $this->assertDatabaseHas('transactions', [
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Compra no mercado',
            'value' => 150.75,
            'paid_at' => null,
        ]);
    }

    public function test_validation_errors_on_create(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->post(route('transactions.store', $workspace), []);

        $response->assertSessionHasErrors(['description', 'value', 'date', 'account_id', 'category_id']);
    }

    public function test_transaction_with_zero_value_is_rejected(): void
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
            'type' => 'expense',
        ]);

        $response = $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Valor zero',
                'value' => 0,
                'date' => '2026-07-10',
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
            ]);

        $response->assertSessionHasErrors(['value']);
    }

    public function test_transaction_with_negative_value_is_rejected(): void
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
            'type' => 'expense',
        ]);

        $response = $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Valor negativo',
                'value' => -50,
                'date' => '2026-07-10',
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
            ]);

        $response->assertSessionHasErrors(['value']);
    }

    public function test_transaction_with_excessive_value_is_rejected(): void
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
            'type' => 'expense',
        ]);

        $response = $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Valor no limite',
                'value' => 999999999.99,
                'date' => '2026-07-10',
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
            ]);

        $response->assertRedirect();

        $response = $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Valor excessivo',
                'value' => 1000000000,
                'date' => '2026-07-10',
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
            ]);

        $response->assertSessionHasErrors(['value']);
    }

    public function test_transaction_with_future_date_is_accepted(): void
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
            'type' => 'expense',
        ]);

        $futureDate = now()->addYear()->format('Y-m-d');

        $response = $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Futura',
                'value' => 100,
                'date' => $futureDate,
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'description' => 'Futura',
        ]);
    }

    public function test_transaction_with_income_only_category_is_rejected(): void
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

        $response = $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Categoria receita',
                'value' => 100,
                'date' => '2026-07-10',
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
            ]);

        $response->assertSessionHasErrors(['category_id']);
    }

    public function test_transaction_account_must_belong_to_same_workspace(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $otherWorkspace = Workspace::factory()->create();

        $account = Account::factory()->create([
            'workspace_id' => $otherWorkspace->id,
            'created_by' => $user->id,
        ]);

        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'expense',
        ]);

        $response = $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Conta externa',
                'value' => 100,
                'date' => '2026-07-10',
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
            ]);

        $response->assertSessionHasErrors(['account_id']);
    }

    public function test_transaction_stores_tags_correctly(): void
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
            'type' => 'expense',
        ]);

        $tag1 = Tag::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'name' => 'Urgente',
        ]);

        $tag2 = Tag::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'name' => 'Viagem',
        ]);

        $response = $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Com tags',
                'value' => 200,
                'date' => '2026-07-10',
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
                'tags' => [$tag1->uuid, $tag2->uuid],
            ]);

        $response->assertRedirect();

        $transaction = Transaction::where('description', 'Com tags')->first();

        $this->assertNotNull($transaction);
        $this->assertEquals(2, $transaction->tags()->count());

        $this->assertDatabaseHas('taggables', [
            'tag_id' => $tag1->id,
            'taggable_type' => Transaction::class,
            'taggable_id' => $transaction->id,
        ]);

        $this->assertDatabaseHas('taggables', [
            'tag_id' => $tag2->id,
            'taggable_type' => Transaction::class,
            'taggable_id' => $transaction->id,
        ]);
    }

    public function test_transaction_list_shows_correct_data(): void
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
            'type' => 'expense',
        ]);

        Transaction::factory()->count(3)->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'type' => 'expense',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('transactions.datatable', $workspace));

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_empty_transaction_list(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->getJson(route('transactions.datatable', $workspace));

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_user_can_create_recurring_expense(): void
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
            'type' => 'expense',
        ]);

        $today = Carbon::today()->format('Y-m-d');

        $response = $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Netflix',
                'value' => 39.90,
                'date' => $today,
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
                'is_recurring' => true,
                'frequency' => 'monthly',
                'frequency_day' => 15,
            ]);

        $response->assertRedirect(route('transactions.index', $workspace));

        // Recurrence created with type=expense
        $this->assertDatabaseHas('recurrences', [
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Netflix',
            'value' => '39.90',
            'type' => 'expense',
        ]);

        // Transaction created linked to the recurrence
        $recurrence = Recurrence::where('workspace_id', $workspace->id)
            ->where('description', 'Netflix')
            ->first();

        $this->assertNotNull($recurrence);

        $this->assertDatabaseHas('transactions', [
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Netflix',
            'value' => '39.90',
            'type' => 'expense',
            'recurrence_id' => $recurrence->id,
            'paid_at' => null,
        ]);
    }

    public function test_user_can_create_future_dated_recurring_expense(): void
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
            'type' => 'expense',
        ]);

        $futureDate = Carbon::today()->addMonth()->format('Y-m-d');

        $response = $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Empréstimo pai',
                'value' => 250,
                'date' => $futureDate,
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
                'is_recurring' => true,
                'frequency' => 'monthly',
                'frequency_day' => 10,
            ]);

        $response->assertRedirect(route('transactions.index', $workspace));

        // Recurrence created with type=expense and next_date = start_date
        $this->assertDatabaseHas('recurrences', [
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Empréstimo pai',
            'value' => '250.00',
            'type' => 'expense',
        ]);

        // No transaction created yet (future-dated recurrence)
        $this->assertDatabaseMissing('transactions', [
            'workspace_id' => $workspace->id,
            'description' => 'Empréstimo pai',
        ]);
    }

    public function test_user_can_create_avulsa_expense(): void
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
            'type' => 'expense',
        ]);

        $response = $this->actingAs($user)
            ->post(route('transactions.store', $workspace), [
                'description' => 'Compra avulsa',
                'value' => 75.50,
                'date' => '2026-07-10',
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
                'is_recurring' => false,
            ]);

        $response->assertRedirect(route('transactions.index', $workspace));

        // Single transaction created
        $this->assertDatabaseHas('transactions', [
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Compra avulsa',
            'value' => 75.50,
            'paid_at' => null,
        ]);

        // No recurrence created
        $this->assertDatabaseMissing('recurrences', [
            'workspace_id' => $workspace->id,
            'description' => 'Compra avulsa',
        ]);
    }
}
