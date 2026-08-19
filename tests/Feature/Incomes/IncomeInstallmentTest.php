<?php

declare(strict_types=1);

namespace Tests\Feature\Incomes;

use App\Models\Transaction;

class IncomeInstallmentTest extends IncomeTestCase
{
    public function test_create_3_installments_splits_value_correctly(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);

        $response = $this->actingAs($user)
            ->post(route('incomes.store', $workspace), [
                'description' => 'Venda Parcelada',
                'value' => 1000,
                'date' => '2026-01-15',
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
                'installments_total' => 3,
            ]);

        $response->assertRedirect(route('incomes.index', $workspace));

        $installments = Transaction::where('description', 'Venda Parcelada')
            ->orderBy('installment_number')
            ->get();

        $this->assertCount(3, $installments);
        $this->assertEquals(333.33, (float) $installments[0]->value);
        $this->assertEquals(333.33, (float) $installments[1]->value);
        $this->assertEquals(333.34, (float) $installments[2]->value);
        $this->assertEquals(1000, (float) $installments->sum('value'));
    }

    public function test_installment_dates_are_monthly_apart(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('incomes.store', $workspace), [
                'description' => 'Parcelada',
                'value' => 600,
                'date' => '2026-01-31',
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
                'installments_total' => 3,
            ]);

        $rows = Transaction::where('description', 'Parcelada')->orderBy('installment_number')->get();
        $this->assertEquals('2026-01-31', $rows[0]->date->format('Y-m-d'));
        $this->assertEquals('2026-02-28', $rows[1]->date->format('Y-m-d'));
        $this->assertEquals('2026-03-31', $rows[2]->date->format('Y-m-d'));
    }

    public function test_installment_group_shares_same_group_id(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('incomes.store', $workspace), [
                'description' => 'Parcelada',
                'value' => 900,
                'date' => '2026-01-15',
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
                'installments_total' => 3,
            ]);

        $rows = Transaction::where('description', 'Parcelada')->get();
        $this->assertCount(3, $rows);
        $this->assertNotNull($rows[0]->installment_group_id);
        $this->assertEquals($rows[0]->installment_group_id, $rows[1]->installment_group_id);
        $this->assertEquals($rows[0]->installment_group_id, $rows[2]->installment_group_id);
        $this->assertEquals([1, 2, 3], $rows->pluck('installment_number')->toArray());
    }

    public function test_installments_start_with_null_paid_at(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('incomes.store', $workspace), [
                'description' => 'Parcelada',
                'value' => 300,
                'date' => '2026-01-15',
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
                'installments_total' => 3,
            ]);

        $rows = Transaction::where('description', 'Parcelada')->get();
        foreach ($rows as $row) {
            $this->assertNull($row->paid_at);
        }
    }

    public function test_delete_group_soft_deletes_all_installments(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('incomes.store', $workspace), [
                'description' => 'Parcelada',
                'value' => 300,
                'date' => '2026-01-15',
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
                'installments_total' => 3,
            ]);

        $first = Transaction::where('description', 'Parcelada')->first();

        $this->actingAs($user)
            ->delete(route('incomes.destroy-group', [$workspace, $first]));

        $this->assertEquals(0, Transaction::where('description', 'Parcelada')->count());
        $this->assertEquals(3, Transaction::withTrashed()->where('description', 'Parcelada')->count());
    }

    public function test_delete_group_with_received_installments_reverts_balance(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user, 1000);
        $category = $this->createIncomeCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('incomes.store', $workspace), [
                'description' => 'Parcelada',
                'value' => 300,
                'date' => '2026-01-15',
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
                'installments_total' => 3,
            ]);

        $rows = Transaction::where('description', 'Parcelada')->orderBy('installment_number')->get();
        $this->actingAs($user)->post(route('incomes.receive', [$workspace, $rows[0]]));
        $this->actingAs($user)->post(route('incomes.receive', [$workspace, $rows[2]]));

        $account->refresh();
        $this->assertEquals(1000 + 100 + 100, (float) $account->current_balance);

        $this->actingAs($user)
            ->delete(route('incomes.destroy-group', [$workspace, $rows[0]]));

        $account->refresh();
        $this->assertEquals(1000, (float) $account->current_balance);
    }

    public function test_cross_workspace_destroy_group_returns_404(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('incomes.store', $workspace), [
                'description' => 'P', 'value' => 200, 'date' => '2026-01-15',
                'account_id' => $account->uuid, 'category_id' => $category->uuid,
                'installments_total' => 2,
            ]);
        $income = Transaction::where('description', 'P')->first();

        [$otherUser, $otherWorkspace] = $this->createWorkspaceWithMember();

        $response = $this->actingAs($otherUser)
            ->delete(route('incomes.destroy-group', [$otherWorkspace, $income->uuid]));

        $response->assertNotFound();
    }
}
