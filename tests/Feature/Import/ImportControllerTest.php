<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Enums\WorkspaceRole;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportControllerTest extends TestCase
{
    public function test_import_create_page_renders_for_expenses(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->get(route('transactions.import.create', $workspace));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Imports/Index')
            ->where('type', 'expense')
        );
    }

    public function test_import_create_page_renders_for_incomes(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->get(route('incomes.import.create', $workspace));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Imports/Index')
            ->where('type', 'income')
        );
    }

    public function test_guest_cannot_access_import_pages(): void
    {
        $workspace = Workspace::factory()->create();

        $response = $this->get(route('transactions.import.create', $workspace));

        $response->assertRedirect(route('login'));
    }

    public function test_non_member_cannot_access_import(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('transactions.import.create', $workspace));

        $response->assertForbidden();
    }

    public function test_store_returns_preview_with_parsed_items(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $csv = "description,value,date,category\n"
            ."Supermercado XYZ,150.00,2026-09-01,Alimentação\n"
            .'Farmácia ABC,45.90,2026-09-02,Saúde';

        $file = UploadedFile::fake()->createWithContent('test.csv', $csv);

        $response = $this->actingAs($user)
            ->post(route('transactions.import.store', $workspace), [
                'type' => 'expense',
                'file' => $file,
            ]);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Imports/Index')
            ->has('preview', 2)
            ->where('preview.0.description', 'Supermercado XYZ')
            ->where('preview.0.value', fn ($value) => (float) $value === 150.00)
            ->where('preview.0.date', '2026-09-01')
            ->where('preview.0.type', 'debit')
            ->where('preview.0.category_name', 'Alimentação')
            ->where('preview.0.is_duplicate', false)
            ->where('preview.1.description', 'Farmácia ABC')
            ->where('preview.1.is_duplicate', false)
        );
    }

    public function test_store_returns_error_for_empty_csv(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $csv = 'description,value,date,category';

        $file = UploadedFile::fake()->createWithContent('test.csv', $csv);

        $response = $this->actingAs($user)
            ->post(route('transactions.import.store', $workspace), [
                'type' => 'expense',
                'file' => $file,
            ]);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Imports/Index')
            ->has('preview', 0)
            ->where('flash.error', 'Nenhuma transação encontrada no arquivo.')
        );
    }

    public function test_store_detects_duplicates_in_preview(): void
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

        Transaction::factory()->create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'type' => 'expense',
            'value' => 150.00,
            'date' => '2026-09-01',
        ]);

        $csv = "description,value,date,category\nSupermercado XYZ,150.00,2026-09-01,Alimentação";

        $file = UploadedFile::fake()->createWithContent('test.csv', $csv);

        $response = $this->actingAs($user)
            ->post(route('transactions.import.store', $workspace), [
                'type' => 'expense',
                'file' => $file,
            ]);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('preview', 1)
            ->where('preview.0.is_duplicate', true)
        );
    }

    public function test_store_requires_valid_csv_file(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->post(route('transactions.import.store', $workspace), [
                'type' => 'expense',
                'file' => 'not-a-file',
            ]);

        $response->assertSessionHasErrors(['file']);
    }

    public function test_store_requires_valid_type(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $csv = "description,value,date,category\nTeste,100.00,2026-09-01,Categoria";

        $file = UploadedFile::fake()->createWithContent('test.csv', $csv);

        $response = $this->actingAs($user)
            ->post(route('transactions.import.store', $workspace), [
                'type' => 'invalid',
                'file' => $file,
            ]);

        $response->assertSessionHasErrors(['type']);
    }

    public function test_confirm_creates_transactions(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $items = [
            [
                'description' => 'Supermercado XYZ',
                'value' => 150.00,
                'date' => '2026-09-01',
                'category_name' => 'Alimentação',
                'is_duplicate' => false,
                'confirm_duplicate' => false,
            ],
        ];

        $response = $this->actingAs($user)
            ->post(route('transactions.import.confirm', $workspace), [
                'type' => 'expense',
                'items' => $items,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'workspace_id' => $workspace->id,
            'description' => 'Supermercado XYZ',
            'value' => '150.00',
            'type' => 'expense',
        ]);

        $this->assertDatabaseHas('accounts', [
            'workspace_id' => $workspace->id,
            'name' => 'Importado',
        ]);

        $this->assertDatabaseHas('categories', [
            'workspace_id' => $workspace->id,
            'name' => 'Importadas',
            'type' => 'expense',
        ]);
    }

    public function test_confirm_redirects_to_transactions_index(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $items = [
            [
                'description' => 'Mercado',
                'value' => 80.00,
                'date' => '2026-09-01',
                'category_name' => 'Alimentação',
                'is_duplicate' => false,
                'confirm_duplicate' => false,
            ],
        ];

        $response = $this->actingAs($user)
            ->post(route('transactions.import.confirm', $workspace), [
                'type' => 'expense',
                'items' => $items,
            ]);

        $response->assertRedirect(route('transactions.index', $workspace));
    }

    public function test_confirm_redirects_to_incomes_index(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $items = [
            [
                'description' => 'Salário',
                'value' => 5000.00,
                'date' => '2026-09-05',
                'category_name' => 'Trabalho',
                'is_duplicate' => false,
                'confirm_duplicate' => false,
            ],
        ];

        $response = $this->actingAs($user)
            ->post(route('incomes.import.confirm', $workspace), [
                'type' => 'income',
                'items' => $items,
            ]);

        $response->assertRedirect(route('incomes.index', $workspace));
    }

    public function test_confirm_requires_items(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->post(route('transactions.import.confirm', $workspace), [
                'type' => 'expense',
                'items' => [],
            ]);

        $response->assertSessionHasErrors(['items']);
    }

    public function test_confirm_skips_items_marked_as_duplicate_without_confirmation(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $items = [
            [
                'description' => 'Duplicada',
                'value' => 150.00,
                'date' => '2026-09-01',
                'category_name' => 'Alimentação',
                'is_duplicate' => true,
                'confirm_duplicate' => false,
            ],
            [
                'description' => 'Nova',
                'value' => 99.00,
                'date' => '2026-09-02',
                'category_name' => 'Saúde',
                'is_duplicate' => false,
                'confirm_duplicate' => false,
            ],
        ];

        $response = $this->actingAs($user)
            ->post(route('transactions.import.confirm', $workspace), [
                'type' => 'expense',
                'items' => $items,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'workspace_id' => $workspace->id,
            'description' => 'Nova',
        ]);
        $this->assertDatabaseMissing('transactions', [
            'workspace_id' => $workspace->id,
            'description' => 'Duplicada',
        ]);
    }
}
