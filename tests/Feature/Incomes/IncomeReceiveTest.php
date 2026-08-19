<?php

declare(strict_types=1);

namespace Tests\Feature\Incomes;

use App\Models\Transaction;
use App\Services\AccountService;

class IncomeReceiveTest extends IncomeTestCase
{
    public function test_receive_unpaid_income_sets_paid_at_and_increases_balance(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user, 1000);
        $category = $this->createIncomeCategory($workspace, $user);
        $income = Transaction::factory()->income()->unpaid()->create([
            'workspace_id' => $workspace->id, 'account_id' => $account->id, 'category_id' => $category->id, 'created_by' => $user->id,
            'value' => 500,
        ]);

        $this->actingAs($user)
            ->post(route('incomes.receive', [$workspace, $income]));

        $income->refresh();
        $account->refresh();
        $this->assertNotNull($income->paid_at);
        $this->assertEquals(1500, (float) $account->current_balance);
    }

    public function test_un_receive_paid_income_reverts_balance(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user, 1000);
        $category = $this->createIncomeCategory($workspace, $user);
        $income = Transaction::factory()->income()->paid()->create([
            'workspace_id' => $workspace->id, 'account_id' => $account->id, 'category_id' => $category->id, 'created_by' => $user->id,
            'value' => 500,
        ]);
        app(AccountService::class)->recalculateBalance($account);
        $account->refresh();
        $this->assertEquals(1500, (float) $account->current_balance);

        $this->actingAs($user)
            ->post(route('incomes.unreceive', [$workspace, $income]));

        $income->refresh();
        $account->refresh();
        $this->assertNull($income->paid_at);
        $this->assertEquals(1000, (float) $account->current_balance);
    }

    public function test_receive_already_received_is_idempotent(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user, 1000);
        $category = $this->createIncomeCategory($workspace, $user);
        $income = Transaction::factory()->income()->paid()->create([
            'workspace_id' => $workspace->id, 'account_id' => $account->id, 'category_id' => $category->id, 'created_by' => $user->id,
            'value' => 500,
        ]);
        app(AccountService::class)->recalculateBalance($account);

        $this->actingAs($user)
            ->post(route('incomes.receive', [$workspace, $income]));

        $income->refresh();
        $account->refresh();
        $this->assertNotNull($income->paid_at);
        $this->assertEquals(1500, (float) $account->current_balance);
    }

    public function test_un_receive_not_received_is_noop(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user, 1000);
        $category = $this->createIncomeCategory($workspace, $user);
        $income = Transaction::factory()->income()->unpaid()->create([
            'workspace_id' => $workspace->id, 'account_id' => $account->id, 'category_id' => $category->id, 'created_by' => $user->id,
            'value' => 500,
        ]);

        $this->actingAs($user)
            ->post(route('incomes.unreceive', [$workspace, $income]));

        $income->refresh();
        $account->refresh();
        $this->assertNull($income->paid_at);
        $this->assertEquals(1000, (float) $account->current_balance);
    }

    public function test_chained_receive_un_receive_preserves_balance(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user, 1000);
        $category = $this->createIncomeCategory($workspace, $user);
        $income = Transaction::factory()->income()->unpaid()->create([
            'workspace_id' => $workspace->id, 'account_id' => $account->id, 'category_id' => $category->id, 'created_by' => $user->id,
            'value' => 250,
        ]);

        $this->actingAs($user)->post(route('incomes.receive', [$workspace, $income]));
        $this->actingAs($user)->post(route('incomes.unreceive', [$workspace, $income]));
        $this->actingAs($user)->post(route('incomes.receive', [$workspace, $income]));

        $account->refresh();
        $this->assertEquals(1250, (float) $account->current_balance);
    }

    public function test_viewer_cannot_receive_income(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $viewer = $this->attachMember($workspace, 'viewer');
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);
        $income = Transaction::factory()->income()->unpaid()->create([
            'workspace_id' => $workspace->id, 'account_id' => $account->id, 'category_id' => $category->id, 'created_by' => $user->id,
        ]);

        $response = $this->actingAs($viewer)
            ->post(route('incomes.receive', [$workspace, $income]));

        $response->assertForbidden();
        $income->refresh();
        $this->assertNull($income->paid_at);
    }
}
