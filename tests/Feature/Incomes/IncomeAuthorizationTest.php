<?php

declare(strict_types=1);

namespace Tests\Feature\Incomes;

use App\Enums\WorkspaceRole;
use App\Models\Transaction;
use App\Services\RecurringIncomeService;
use Carbon\Carbon;

class IncomeAuthorizationTest extends IncomeTestCase
{
    public function test_admin_can_do_all_income_actions(): void
    {
        [$admin, $workspace] = $this->createWorkspaceWithMember(WorkspaceRole::Admin->value);
        $account = $this->createAccount($workspace, $admin);
        $category = $this->createIncomeCategory($workspace, $admin);

        // create
        $response = $this->actingAs($admin)->post(route('incomes.store', $workspace), [
            'description' => 'Income', 'value' => 100, 'date' => '2026-05-01',
            'account_id' => $account->uuid, 'category_id' => $category->uuid,
        ]);
        $response->assertRedirect();

        $income = Transaction::where('description', 'Income')->first();

        // edit
        $this->actingAs($admin)->get(route('incomes.edit', [$workspace, $income]))->assertOk();

        // update
        $this->actingAs($admin)->put(route('incomes.update', [$workspace, $income]), [
            'description' => 'Updated',
        ])->assertRedirect();

        // receive
        $this->actingAs($admin)->post(route('incomes.receive', [$workspace, $income]))->assertRedirect();

        // unreceive
        $this->actingAs($admin)->post(route('incomes.unreceive', [$workspace, $income]))->assertRedirect();

        // delete
        $this->actingAs($admin)->delete(route('incomes.destroy', [$workspace, $income]))->assertRedirect();
    }

    public function test_editor_can_create_update_receive_but_not_delete(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $editor = $this->attachMember($workspace, WorkspaceRole::Editor->value);
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);

        $this->actingAs($editor)->post(route('incomes.store', $workspace), [
            'description' => 'Test', 'value' => 100, 'date' => '2026-05-01',
            'account_id' => $account->uuid, 'category_id' => $category->uuid,
        ])->assertRedirect();

        $income = Transaction::where('description', 'Test')->first();

        $this->actingAs($editor)->put(route('incomes.update', [$workspace, $income]), [
            'description' => 'Updated',
        ])->assertRedirect();

        $this->actingAs($editor)->post(route('incomes.receive', [$workspace, $income]))->assertRedirect();

        $this->actingAs($editor)->delete(route('incomes.destroy', [$workspace, $income]))
            ->assertForbidden();
    }

    public function test_viewer_forbidden_on_all_write_actions(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $viewer = $this->attachMember($workspace, WorkspaceRole::Viewer->value);
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);
        $income = Transaction::factory()->income()->create([
            'workspace_id' => $workspace->id, 'account_id' => $account->id, 'category_id' => $category->id, 'created_by' => $user->id,
        ]);

        $this->actingAs($viewer)->post(route('incomes.store', $workspace), [
            'description' => 'X', 'value' => 100, 'date' => '2026-05-01',
            'account_id' => $account->uuid, 'category_id' => $category->uuid,
        ])->assertForbidden();

        $this->actingAs($viewer)->put(route('incomes.update', [$workspace, $income]), [
            'description' => 'X',
        ])->assertForbidden();

        $this->actingAs($viewer)->post(route('incomes.receive', [$workspace, $income]))
            ->assertForbidden();

        $this->actingAs($viewer)->delete(route('incomes.destroy', [$workspace, $income]))
            ->assertForbidden();

        $this->actingAs($viewer)->delete(route('incomes.destroy-group', [$workspace, $income]))
            ->assertForbidden();
    }

    public function test_viewer_can_list_incomes(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $viewer = $this->attachMember($workspace, WorkspaceRole::Viewer->value);

        $response = $this->actingAs($viewer)->get(route('incomes.index', $workspace));
        $response->assertOk();
    }

    public function test_cross_workspace_income_returns_404(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        [$otherUser, $otherWorkspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);
        $income = Transaction::factory()->income()->create([
            'workspace_id' => $workspace->id, 'account_id' => $account->id, 'category_id' => $category->id, 'created_by' => $user->id,
        ]);

        $response = $this->actingAs($otherUser)->get(route('incomes.edit', [$otherWorkspace, $income->uuid]));
        $response->assertNotFound();

        $response = $this->actingAs($otherUser)->delete(route('incomes.destroy', [$otherWorkspace, $income->uuid]));
        $response->assertNotFound();

        $response = $this->actingAs($otherUser)->post(route('incomes.receive', [$otherWorkspace, $income->uuid]));
        $response->assertNotFound();
    }

    public function test_cross_workspace_recurring_returns_404(): void
    {
        Carbon::setTestNow('2026-03-15');
        [$user, $workspace] = $this->createWorkspaceWithMember();
        [$otherUser, $otherWorkspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);

        $service = app(RecurringIncomeService::class);
        $template = $service->createTemplate($workspace, $user, [
            'description' => 'Salário', 'value' => 1000, 'date' => '2026-01-05',
            'account_id' => $account->uuid, 'category_id' => $category->uuid,
        ]);

        $response = $this->actingAs($otherUser)
            ->post(route('recurring-incomes.cancel', [$otherWorkspace, $template->uuid]));
        $response->assertNotFound();

        $response = $this->actingAs($otherUser)
            ->delete(route('recurring-incomes.destroy', [$otherWorkspace, $template->uuid]));
        $response->assertNotFound();
        Carbon::setTestNow();
    }

    public function test_cross_workspace_transfer_returns_404(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        [$otherUser, $otherWorkspace] = $this->createWorkspaceWithMember();
        $accountA = $this->createAccount($workspace, $user);
        $accountB = $this->createAccount($workspace, $user);

        $this->actingAs($user)->post(route('transfers.store', $workspace), [
            'description' => 'T', 'value' => 100, 'date' => '2026-05-15',
            'from_account_id' => $accountA->uuid, 'to_account_id' => $accountB->uuid,
        ]);
        $transferGroup = Transaction::where('description', 'T')->value('transfer_group_id');

        $response = $this->actingAs($otherUser)
            ->put(route('transfers.update', [$otherWorkspace, $transferGroup]), [
                'value' => 999,
            ]);
        $response->assertNotFound();

        $response = $this->actingAs($otherUser)
            ->delete(route('transfers.destroy', [$otherWorkspace, $transferGroup]));
        $response->assertNotFound();
    }
}
