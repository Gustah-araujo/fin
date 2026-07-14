<?php

declare(strict_types=1);

namespace Tests\Feature\Transactions;

use App\Enums\WorkspaceRole;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class TransactionFilteringTest extends TestCase
{
    public function test_can_search_transactions_by_description(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ["role" => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
        ]);
        $category = Category::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
            "type" => "expense",
        ]);

        Transaction::factory()->create([
            "workspace_id" => $workspace->id,
            "account_id" => $account->id,
            "category_id" => $category->id,
            "created_by" => $user->id,
            "description" => "Mercado Extra",
        ]);
        Transaction::factory()->create([
            "workspace_id" => $workspace->id,
            "account_id" => $account->id,
            "category_id" => $category->id,
            "created_by" => $user->id,
            "description" => "Mercado São João",
        ]);
        Transaction::factory()->create([
            "workspace_id" => $workspace->id,
            "account_id" => $account->id,
            "category_id" => $category->id,
            "created_by" => $user->id,
            "description" => "Padaria Pão Doce",
        ]);

        $response = $this->actingAs($user)
            ->get(route("transactions.index", ["workspace" => $workspace, "search" => "mercado"]));

        $response->assertInertia(fn ($page) => $page
            ->component("Transactions/Index", false)
            ->has("transactions.data", 2)
        );
    }

    public function test_search_is_case_insensitive(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ["role" => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
        ]);
        $category = Category::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
            "type" => "expense",
        ]);

        Transaction::factory()->create([
            "workspace_id" => $workspace->id,
            "account_id" => $account->id,
            "category_id" => $category->id,
            "created_by" => $user->id,
            "description" => "Mercado",
        ]);
        Transaction::factory()->create([
            "workspace_id" => $workspace->id,
            "account_id" => $account->id,
            "category_id" => $category->id,
            "created_by" => $user->id,
            "description" => "Padaria",
        ]);

        $response = $this->actingAs($user)
            ->get(route("transactions.index", ["workspace" => $workspace, "search" => "MERCADO"]));

        $response->assertInertia(fn ($page) => $page
            ->component("Transactions/Index", false)
            ->has("transactions.data", 1)
        );
    }

    public function test_can_filter_transactions_by_category(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ["role" => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
        ]);
        $categoryA = Category::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
            "type" => "expense",
            "name" => "Alimentação",
        ]);
        $categoryB = Category::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
            "type" => "expense",
            "name" => "Transporte",
        ]);

        Transaction::factory()->create([
            "workspace_id" => $workspace->id,
            "account_id" => $account->id,
            "category_id" => $categoryA->id,
            "created_by" => $user->id,
        ]);
        Transaction::factory()->create([
            "workspace_id" => $workspace->id,
            "account_id" => $account->id,
            "category_id" => $categoryA->id,
            "created_by" => $user->id,
        ]);
        Transaction::factory()->create([
            "workspace_id" => $workspace->id,
            "account_id" => $account->id,
            "category_id" => $categoryB->id,
            "created_by" => $user->id,
        ]);
        Transaction::factory()->create([
            "workspace_id" => $workspace->id,
            "account_id" => $account->id,
            "category_id" => $categoryB->id,
            "created_by" => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route("transactions.index", ["workspace" => $workspace, "category" => $categoryA->id]));

        $response->assertInertia(fn ($page) => $page
            ->component("Transactions/Index", false)
            ->has("transactions.data", 2)
        );
    }

    public function test_can_filter_transactions_by_account(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ["role" => WorkspaceRole::Admin->value]);

        $accountA = Account::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
            "name" => "Nubank",
        ]);
        $accountB = Account::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
            "name" => "Itaú",
        ]);
        $category = Category::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
            "type" => "expense",
        ]);

        Transaction::factory()->create([
            "workspace_id" => $workspace->id,
            "account_id" => $accountA->id,
            "category_id" => $category->id,
            "created_by" => $user->id,
        ]);
        Transaction::factory()->create([
            "workspace_id" => $workspace->id,
            "account_id" => $accountA->id,
            "category_id" => $category->id,
            "created_by" => $user->id,
        ]);
        Transaction::factory()->create([
            "workspace_id" => $workspace->id,
            "account_id" => $accountB->id,
            "category_id" => $category->id,
            "created_by" => $user->id,
        ]);
        Transaction::factory()->create([
            "workspace_id" => $workspace->id,
            "account_id" => $accountB->id,
            "category_id" => $category->id,
            "created_by" => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route("transactions.index", ["workspace" => $workspace, "account" => $accountA->id]));

        $response->assertInertia(fn ($page) => $page
            ->component("Transactions/Index", false)
            ->has("transactions.data", 2)
        );
    }

    public function test_can_filter_transactions_by_date_range(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ["role" => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
        ]);
        $category = Category::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
            "type" => "expense",
        ]);

        Transaction::factory()->create([
            "workspace_id" => $workspace->id,
            "account_id" => $account->id,
            "category_id" => $category->id,
            "created_by" => $user->id,
            "date" => "2026-01-01",
        ]);
        Transaction::factory()->create([
            "workspace_id" => $workspace->id,
            "account_id" => $account->id,
            "category_id" => $category->id,
            "created_by" => $user->id,
            "date" => "2026-06-15",
        ]);
        Transaction::factory()->create([
            "workspace_id" => $workspace->id,
            "account_id" => $account->id,
            "category_id" => $category->id,
            "created_by" => $user->id,
            "date" => "2026-12-31",
        ]);

        $response = $this->actingAs($user)
            ->get(route("transactions.index", [
                "workspace" => $workspace,
                "from_date" => "2026-03-01",
                "to_date" => "2026-09-30",
            ]));

        $response->assertInertia(fn ($page) => $page
            ->component("Transactions/Index", false)
            ->has("transactions.data", 1)
        );
    }

    public function test_can_filter_by_paid_status(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ["role" => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
        ]);
        $category = Category::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
            "type" => "expense",
        ]);

        Transaction::factory()->paid()->create([
            "workspace_id" => $workspace->id,
            "account_id" => $account->id,
            "category_id" => $category->id,
            "created_by" => $user->id,
        ]);
        Transaction::factory()->paid()->create([
            "workspace_id" => $workspace->id,
            "account_id" => $account->id,
            "category_id" => $category->id,
            "created_by" => $user->id,
        ]);
        Transaction::factory()->unpaid()->create([
            "workspace_id" => $workspace->id,
            "account_id" => $account->id,
            "category_id" => $category->id,
            "created_by" => $user->id,
        ]);

        $paidResponse = $this->actingAs($user)
            ->get(route("transactions.index", ["workspace" => $workspace, "status" => "paid"]));

        $paidResponse->assertInertia(fn ($page) => $page
            ->component("Transactions/Index", false)
            ->has("transactions.data", 2)
        );

        $unpaidResponse = $this->actingAs($user)
            ->get(route("transactions.index", ["workspace" => $workspace, "status" => "unpaid"]));

        $unpaidResponse->assertInertia(fn ($page) => $page
            ->component("Transactions/Index", false)
            ->has("transactions.data", 1)
        );
    }

    public function test_filters_combine_with_and_logic(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ["role" => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
        ]);
        $categoryA = Category::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
            "type" => "expense",
            "name" => "Categoria A",
        ]);
        $categoryB = Category::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
            "type" => "expense",
            "name" => "Categoria B",
        ]);

        Transaction::factory()->paid()->create([
            "workspace_id" => $workspace->id,
            "account_id" => $account->id,
            "category_id" => $categoryA->id,
            "created_by" => $user->id,
        ]);

        Transaction::factory()->paid()->create([
            "workspace_id" => $workspace->id,
            "account_id" => $account->id,
            "category_id" => $categoryB->id,
            "created_by" => $user->id,
        ]);
        Transaction::factory()->paid()->create([
            "workspace_id" => $workspace->id,
            "account_id" => $account->id,
            "category_id" => $categoryB->id,
            "created_by" => $user->id,
        ]);
        Transaction::factory()->unpaid()->create([
            "workspace_id" => $workspace->id,
            "account_id" => $account->id,
            "category_id" => $categoryB->id,
            "created_by" => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route("transactions.index", [
                "workspace" => $workspace,
                "status" => "paid",
                "category" => $categoryB->id,
            ]));

        $response->assertInertia(fn ($page) => $page
            ->component("Transactions/Index", false)
            ->has("transactions.data", 2)
        );
    }

    public function test_transaction_list_paginates_at_25(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ["role" => WorkspaceRole::Admin->value]);

        $account = Account::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
        ]);
        $category = Category::factory()->create([
            "workspace_id" => $workspace->id,
            "created_by" => $user->id,
            "type" => "expense",
        ]);

        Transaction::factory()->count(30)->create([
            "workspace_id" => $workspace->id,
            "account_id" => $account->id,
            "category_id" => $category->id,
            "created_by" => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route("transactions.index", $workspace));

        $response->assertInertia(fn ($page) => $page
            ->component("Transactions/Index", false)
            ->has("transactions.data", 25)
        );
    }
}
