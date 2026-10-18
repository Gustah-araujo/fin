<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Enums\TransactionType;
use App\Enums\WorkspaceRole;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ImportControllerTest extends TestCase
{
    public function test_import_create_page_renders(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->get(route('import.create', $workspace));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Imports/Index')
            ->has('categories')
            ->has('tags')
        );
    }

    public function test_guest_cannot_access_import_pages(): void
    {
        $workspace = Workspace::factory()->create();

        $response = $this->get(route('import.create', $workspace));

        $response->assertRedirect(route('login'));
    }

    public function test_non_member_cannot_access_import(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('import.create', $workspace));

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
            ->post(route('import.store', $workspace), [
                'file' => $file,
            ]);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Imports/Index')
            ->has('preview', 2)
            ->has('categories')
            ->has('tags')
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
            ->post(route('import.store', $workspace), [
                'file' => $file,
            ]);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Imports/Index')
            ->has('preview', 0)
            ->where('flash.error', 'Nenhuma transação encontrada no arquivo.')
        );
    }

    public function test_store_requires_valid_file(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->post(route('import.store', $workspace), [
                'file' => 'not-a-file',
            ]);

        $response->assertSessionHasErrors(['file']);
    }

    public function test_store_accepts_pdf_file(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        // FakeAiService throws for PDF — controller catches and returns error
        $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        $response = $this->actingAs($user)
            ->post(route('import.store', $workspace), [
                'file' => $file,
            ]);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Imports/Index')
            ->has('preview', 0)
            ->where('flash.error', fn ($error) => str_contains($error, 'Erro ao processar arquivo'))
        );
    }

    public function test_store_handles_ai_parse_error_gracefully(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        // Upload a JPG — FakeAiService throws RuntimeException for non-CSV/TXT
        $file = UploadedFile::fake()->create('receipt.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($user)
            ->post(route('import.store', $workspace), [
                'file' => $file,
            ]);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Imports/Index')
            ->has('preview', 0)
        );
    }

    public function test_store_accepts_mixed_types_in_items(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $csv = "description,value,date,category,type\n"
            ."Supermercado XYZ,150.00,2026-09-01,Alimentação,debit\n"
            .'Salário Empresa,5000.00,2026-09-05,Trabalho,credit';

        $file = UploadedFile::fake()->createWithContent('test.csv', $csv);

        $response = $this->actingAs($user)
            ->post(route('import.store', $workspace), [
                'file' => $file,
            ]);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Imports/Index')
            ->has('preview', 2)
            ->has('categories')
            ->has('tags')
            ->where('preview.0.type', 'debit')
            ->where('preview.1.type', 'credit')
        );
    }

    public function test_store_returns_categories_and_tags_for_selectors(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Alimentação',
        ]);

        $csv = "description,value,date,category\n"
            .'Supermercado XYZ,150.00,2026-09-01,Alimentação';

        $file = UploadedFile::fake()->createWithContent('test.csv', $csv);

        $response = $this->actingAs($user)
            ->post(route('import.store', $workspace), [
                'file' => $file,
            ]);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Imports/Index')
            ->has('preview', 1)
            ->has('categories', 1)
            ->where('categories.0.name', 'Alimentação')
        );
    }

    public function test_confirm_creates_mixed_expense_and_income_transactions(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $items = [
            [
                'description' => 'Supermercado XYZ',
                'value' => 150.00,
                'date' => '2026-09-01',
                'type' => 'debit',
                'category_name' => 'Alimentação',
                'is_duplicate' => false,
                'confirm_duplicate' => false,
            ],
            [
                'description' => 'Salário Empresa',
                'value' => 5000.00,
                'date' => '2026-09-05',
                'type' => 'credit',
                'category_name' => 'Trabalho',
                'is_duplicate' => false,
                'confirm_duplicate' => false,
            ],
        ];

        $response = $this->actingAs($user)
            ->post(route('import.confirm', $workspace), [
                'items' => $items,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'workspace_id' => $workspace->id,
            'description' => 'Supermercado XYZ',
            'type' => 'expense',
        ]);

        $this->assertDatabaseHas('transactions', [
            'workspace_id' => $workspace->id,
            'description' => 'Salário Empresa',
            'type' => 'income',
        ]);
    }

    public function test_confirm_redirects_to_dashboard(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $items = [
            [
                'description' => 'Mercado',
                'value' => 80.00,
                'date' => '2026-09-01',
                'type' => 'debit',
                'category_name' => 'Alimentação',
                'is_duplicate' => false,
                'confirm_duplicate' => false,
            ],
        ];

        $response = $this->actingAs($user)
            ->post(route('import.confirm', $workspace), [
                'items' => $items,
            ]);

        $response->assertRedirect(route('dashboard', $workspace));
    }

    public function test_confirm_assigns_category_from_uuid(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => TransactionType::Expense,
        ]);

        $items = [
            [
                'description' => 'Compra com Categoria',
                'value' => 200.00,
                'date' => '2026-09-01',
                'type' => 'debit',
                'category_uuid' => $category->uuid,
                'is_duplicate' => false,
                'confirm_duplicate' => false,
            ],
        ];

        $response = $this->actingAs($user)
            ->post(route('import.confirm', $workspace), [
                'items' => $items,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'workspace_id' => $workspace->id,
            'category_id' => $category->id,
        ]);
    }

    public function test_confirm_assigns_tags_to_transaction(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $tag = Tag::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        $items = [
            [
                'description' => 'Transação com Tag',
                'value' => 100.00,
                'date' => '2026-09-01',
                'type' => 'debit',
                'category_name' => 'Outros',
                'tags' => [$tag->uuid],
                'is_duplicate' => false,
                'confirm_duplicate' => false,
            ],
        ];

        $response = $this->actingAs($user)
            ->post(route('import.confirm', $workspace), [
                'items' => $items,
            ]);

        $response->assertRedirect();

        $transaction = Transaction::where('workspace_id', $workspace->id)
            ->where('description', 'Transação com Tag')
            ->first();

        $this->assertNotNull($transaction);
        $this->assertTrue($transaction->tags->contains($tag));
    }

    public function test_confirm_creates_category_when_only_name_provided(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $items = [
            [
                'description' => 'Nova Categoria Test',
                'value' => 50.00,
                'date' => '2026-09-01',
                'type' => 'debit',
                'category_name' => 'Categoria Nova',
                'is_duplicate' => false,
                'confirm_duplicate' => false,
            ],
        ];

        $response = $this->actingAs($user)
            ->post(route('import.confirm', $workspace), [
                'items' => $items,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('categories', [
            'workspace_id' => $workspace->id,
            'name' => 'Categoria Nova',
            'type' => 'expense',
        ]);
    }

    public function test_confirm_requires_items(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->post(route('import.confirm', $workspace), [
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
                'type' => 'debit',
                'category_name' => 'Alimentação',
                'is_duplicate' => true,
                'confirm_duplicate' => false,
            ],
            [
                'description' => 'Nova',
                'value' => 99.00,
                'date' => '2026-09-02',
                'type' => 'debit',
                'category_name' => 'Saúde',
                'is_duplicate' => false,
                'confirm_duplicate' => false,
            ],
        ];

        $response = $this->actingAs($user)
            ->post(route('import.confirm', $workspace), [
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

    public function test_old_routes_return_404(): void
    {
        // The 6 legacy import route names no longer exist
        $this->assertFalse(Route::has('transactions.import.create'));
        $this->assertFalse(Route::has('transactions.import.store'));
        $this->assertFalse(Route::has('transactions.import.confirm'));
        $this->assertFalse(Route::has('incomes.import.create'));
        $this->assertFalse(Route::has('incomes.import.store'));
        $this->assertFalse(Route::has('incomes.import.confirm'));

        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        // Legacy GET transactions/import is swallowed by the transactions/{transaction}
        // resource route, and legacy GET incomes/import matches incomes/{transaction}
        // (405) — so the definitive check is that the route names are gone and the
        // old confirm endpoints no longer resolve.
        $this->actingAs($user)
            ->post('/w/'.$workspace->uuid.'/transactions/import/confirm', [])
            ->assertNotFound();

        $this->actingAs($user)
            ->post('/w/'.$workspace->uuid.'/incomes/import/confirm', [])
            ->assertNotFound();
    }
}
