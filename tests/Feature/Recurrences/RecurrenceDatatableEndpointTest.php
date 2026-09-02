<?php

declare(strict_types=1);

namespace Tests\Feature\Recurrences;

use App\Enums\WorkspaceRole;
use App\Models\Account;
use App\Models\Category;
use App\Models\Recurrence;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class RecurrenceDatatableEndpointTest extends TestCase
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

    private function makeRecurrence(array $overrides = []): Recurrence
    {
        return Recurrence::factory()->create(array_merge([
            'workspace_id' => $this->workspace->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
        ], $overrides));
    }

    private function datatableUrl(array $params = []): string
    {
        $query = http_build_query($params);

        return route('recurrences.datatable', $this->workspace).($query !== '' ? '?'.$query : '');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('recurrences.datatable', $this->workspace))
            ->assertRedirect(route('login'));
    }

    public function test_non_members_cannot_access_datatable_endpoint(): void
    {
        $outsider = User::factory()->create();
        $otherWorkspace = Workspace::factory()->create();
        $otherWorkspace->members()->attach($outsider, ['role' => WorkspaceRole::Admin->value]);

        $this->actingAs($outsider)
            ->getJson(route('recurrences.datatable', $this->workspace))
            ->assertForbidden();
    }

    public function test_index_lists_recurrences_as_json_with_meta(): void
    {
        $this->makeRecurrence(['description' => 'Salário']);
        $this->makeRecurrence(['description' => 'Freela']);

        $response = $this->actingAs($this->user)
            ->getJson(route('recurrences.datatable', $this->workspace));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [[
                    'uuid',
                    'description',
                    'value',
                    'next_date',
                    'status',
                    'account',
                    'category',
                ]],
                'meta' => ['current_page', 'per_page', 'total', 'last_page', 'from', 'to'],
            ]);
    }

    public function test_filters_by_description_text(): void
    {
        $this->makeRecurrence(['description' => 'Salário Mensal']);
        $this->makeRecurrence(['description' => 'Freela']);

        $response = $this->actingAs($this->user)
            ->getJson($this->datatableUrl(['description' => 'Sal']));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'Salário Mensal')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_filters_by_account_uuid(): void
    {
        $otherAccount = Account::factory()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        $this->makeRecurrence(['description' => 'Na conta principal']);
        $this->makeRecurrence(['account_id' => $otherAccount->id, 'description' => 'Na outra conta']);

        $response = $this->actingAs($this->user)
            ->getJson($this->datatableUrl(['account' => $this->account->uuid]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'Na conta principal');
    }

    public function test_filters_by_category_uuid(): void
    {
        $otherCategory = Category::factory()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
            'type' => 'income',
            'name' => 'Outra',
        ]);

        $this->makeRecurrence(['description' => 'Na categoria principal']);
        $this->makeRecurrence(['category_id' => $otherCategory->id, 'description' => 'Na outra categoria']);

        $response = $this->actingAs($this->user)
            ->getJson($this->datatableUrl(['category' => $this->category->uuid]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'Na categoria principal');
    }

    public function test_filters_by_status_active(): void
    {
        $this->makeRecurrence(['description' => 'Ativa', 'next_date' => now()->addDays(5)->format('Y-m-d')]);
        $this->makeRecurrence(['description' => 'Pausada', 'status' => 'paused']);
        $this->makeRecurrence(['description' => 'Esgotada', 'next_date' => null]);

        $response = $this->actingAs($this->user)
            ->getJson($this->datatableUrl(['status' => 'active']));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'Ativa');
    }

    public function test_filters_by_status_paused(): void
    {
        $this->makeRecurrence(['description' => 'Ativa', 'next_date' => now()->addDays(5)->format('Y-m-d')]);
        $this->makeRecurrence(['description' => 'Pausada', 'status' => 'paused']);
        $this->makeRecurrence(['description' => 'Esgotada', 'next_date' => null]);

        $response = $this->actingAs($this->user)
            ->getJson($this->datatableUrl(['status' => 'paused']));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'Pausada');
    }

    public function test_filters_by_status_exhausted(): void
    {
        $this->makeRecurrence(['description' => 'Ativa', 'next_date' => now()->addDays(5)->format('Y-m-d')]);
        $this->makeRecurrence(['description' => 'Pausada', 'status' => 'paused']);
        $this->makeRecurrence(['description' => 'Esgotada', 'next_date' => null]);

        $response = $this->actingAs($this->user)
            ->getJson($this->datatableUrl(['status' => 'exhausted']));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'Esgotada');
    }

    public function test_defaults_to_next_date_ascending_with_nulls_last(): void
    {
        $this->makeRecurrence(['description' => 'Futura longe', 'next_date' => '2026-12-31']);
        $this->makeRecurrence(['description' => 'Futura perto', 'next_date' => '2026-01-15']);
        $this->makeRecurrence(['description' => 'Esgotada', 'next_date' => null]);

        $response = $this->actingAs($this->user)
            ->getJson(route('recurrences.datatable', $this->workspace));

        $response->assertOk();

        $this->assertSame(
            ['Futura perto', 'Futura longe', 'Esgotada'],
            array_column($response->json('data'), 'description'),
        );
    }

    public function test_sorts_by_next_date_descending(): void
    {
        $this->makeRecurrence(['description' => 'Futura longe', 'next_date' => '2026-12-31']);
        $this->makeRecurrence(['description' => 'Futura perto', 'next_date' => '2026-01-15']);
        $this->makeRecurrence(['description' => 'Esgotada', 'next_date' => null]);

        $response = $this->actingAs($this->user)
            ->getJson($this->datatableUrl(['sort' => 'next_date', 'direction' => 'desc']));

        $response->assertOk();

        $this->assertSame(
            ['Futura longe', 'Futura perto', 'Esgotada'],
            array_column($response->json('data'), 'description'),
        );
    }

    public function test_paginates_with_default_per_page_of_25(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->makeRecurrence(['description' => 'Recorrência '.$i]);
        }

        $firstPage = $this->actingAs($this->user)
            ->getJson(route('recurrences.datatable', $this->workspace));

        $firstPage->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.per_page', 25)
            ->assertJsonPath('meta.total', 30)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.from', 1)
            ->assertJsonPath('meta.to', 25);

        $secondPage = $this->actingAs($this->user)
            ->getJson($this->datatableUrl(['page' => 2]));

        $secondPage->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_response_items_use_uuid_key(): void
    {
        $recurrence = $this->makeRecurrence(['description' => 'Salário']);

        $response = $this->actingAs($this->user)
            ->getJson(route('recurrences.datatable', $this->workspace));

        $response->assertOk()
            ->assertJsonPath('data.0.uuid', $recurrence->uuid)
            ->assertJsonMissingPath('data.0.id');
    }
}
