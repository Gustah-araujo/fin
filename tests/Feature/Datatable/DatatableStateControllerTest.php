<?php

declare(strict_types=1);

namespace Tests\Feature\Datatable;

use App\Models\User;
use App\Models\Workspace;
use App\Services\Datatable\TableStateService;
use Illuminate\Http\Request;
use Tests\TestCase;

class DatatableStateControllerTest extends TestCase
{
    private User $user;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->members()->attach($this->user, ['role' => 'admin']);
    }

    private function createSessionRequest(): Request
    {
        $request = Request::create('/test', 'GET');
        $request->setLaravelSession(app('session')->driver());

        return $request;
    }

    public function test_destroy_clears_state_for_entity(): void
    {
        // Save some state first
        $service = new TableStateService;
        $request = $this->createSessionRequest();
        $service->save($request, 'transactions', ['category' => 'uuid-cat'], 'date', 'desc');

        $response = $this->actingAs($this->user)
            ->deleteJson(route('datatable.state.destroy', [
                'workspace' => $this->workspace->uuid,
                'entity' => 'transactions',
            ]));

        $response->assertOk();
        $response->assertJson(['message' => 'Estado limpo.']);

        // Verify session was cleared
        $state = $service->restore($request, 'transactions');
        $this->assertSame([], $state['filters']);
        $this->assertNull($state['sort']);
    }

    public function test_destroy_requires_authentication(): void
    {
        $response = $this->deleteJson(route('datatable.state.destroy', [
            'workspace' => $this->workspace->uuid,
            'entity' => 'transactions',
        ]));

        $response->assertStatus(302); // redirect to login
    }

    public function test_destroy_returns_404_for_invalid_entity(): void
    {
        $response = $this->actingAs($this->user)
            ->deleteJson(route('datatable.state.destroy', [
                'workspace' => $this->workspace->uuid,
                'entity' => 'invalid-entity',
            ]));

        $response->assertStatus(404);
    }

    public function test_destroy_requires_workspace_membership(): void
    {
        $nonMember = User::factory()->create();

        $response = $this->actingAs($nonMember)
            ->deleteJson(route('datatable.state.destroy', [
                'workspace' => $this->workspace->uuid,
                'entity' => 'transactions',
            ]));

        // Non-member should get 404 (workspace not found for them due to ensure.has.workspace middleware)
        $response->assertStatus(404);
    }

    public function test_destroy_clears_only_target_entity(): void
    {
        $service = new TableStateService;
        $request = $this->createSessionRequest();

        // Save state for both entities
        $service->save($request, 'transactions', ['category' => 'uuid-cat'], 'date', 'desc');
        $service->save($request, 'incomes', ['origin' => 'recurring'], 'value', 'asc');

        $response = $this->actingAs($this->user)
            ->deleteJson(route('datatable.state.destroy', [
                'workspace' => $this->workspace->uuid,
                'entity' => 'transactions',
            ]));

        $response->assertOk();

        // Transactions cleared
        $txState = $service->restore($request, 'transactions');
        $this->assertSame([], $txState['filters']);

        // Incomes preserved
        $incState = $service->restore($request, 'incomes');
        $this->assertSame(['origin' => 'recurring'], $incState['filters']);
    }
}
