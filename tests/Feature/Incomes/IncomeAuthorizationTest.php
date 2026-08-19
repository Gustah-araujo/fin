<?php

declare(strict_types=1);

namespace Tests\Feature\Incomes;

use App\Enums\WorkspaceRole;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;

class IncomeAuthorizationTest extends IncomeTestCase
{
    private function viewer(): User
    {
        $viewer = User::factory()->create();
        $this->workspace->members()->attach($viewer, ['role' => WorkspaceRole::Viewer->value]);

        return $viewer;
    }

    public function test_viewer_cannot_create_income(): void
    {
        $viewer = $this->viewer();

        $response = $this->actingAs($viewer)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData());

        $response->assertForbidden();
    }

    public function test_viewer_cannot_update_income(): void
    {
        $viewer = $this->viewer();

        $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData());

        $transaction = Transaction::where('description', 'Salário')->first();

        $response = $this->actingAs($viewer)
            ->put(route('incomes.update', [$this->workspace, $transaction]), [
                'description' => 'Hack',
            ]);

        $response->assertForbidden();
    }

    public function test_viewer_cannot_confirm_income(): void
    {
        $viewer = $this->viewer();

        $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData());

        $transaction = Transaction::where('description', 'Salário')->first();

        $response = $this->actingAs($viewer)
            ->post(route('incomes.pay', [$this->workspace, $transaction]));

        $response->assertForbidden();
    }

    public function test_cross_workspace_income_returns_404(): void
    {
        $otherWorkspace = Workspace::factory()->create();

        $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData());

        $transaction = Transaction::where('description', 'Salário')->first();

        $response = $this->actingAs($this->user)
            ->get(route('incomes.edit', [$otherWorkspace, $transaction]));

        $response->assertNotFound();
    }
}
