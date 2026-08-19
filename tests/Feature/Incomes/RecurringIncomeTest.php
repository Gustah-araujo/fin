<?php

declare(strict_types=1);

namespace Tests\Feature\Incomes;

use App\Models\Transaction;
use App\Services\RecurringIncomeService;
use Carbon\Carbon;

class RecurringIncomeTest extends IncomeTestCase
{
    public function test_create_template_generates_occurrences_up_to_current_month(): void
    {
        Carbon::setTestNow('2026-05-15');
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);

        $response = $this->actingAs($user)
            ->post(route('incomes.store', $workspace), [
                'description' => 'Salário',
                'value' => 5000,
                'date' => '2026-01-05',
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
                'is_recurring' => true,
            ]);

        $response->assertRedirect();

        $template = Transaction::where('description', 'Salário')->where('is_recurring', true)->whereNull('recurring_parent_uuid')->first();
        $this->assertNotNull($template);
        $occurrences = Transaction::where('recurring_parent_uuid', $template->uuid)->get();
        $this->assertCount(5, $occurrences);
        $this->assertEquals(['2026-01', '2026-02', '2026-03', '2026-04', '2026-05'], $occurrences->pluck('recurring_year_month')->sort()->values()->toArray());
        Carbon::setTestNow();
    }

    public function test_occurrences_carry_year_month_bucket(): void
    {
        Carbon::setTestNow('2026-03-15');
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('incomes.store', $workspace), [
                'description' => 'Aluguel', 'value' => 1500, 'date' => '2026-02-01',
                'account_id' => $account->uuid, 'category_id' => $category->uuid,
                'is_recurring' => true,
            ]);

        $occurrences = Transaction::whereNotNull('recurring_parent_uuid')->get();
        $this->assertContains('2026-02', $occurrences->pluck('recurring_year_month')->toArray());
        $this->assertContains('2026-03', $occurrences->pluck('recurring_year_month')->toArray());
        Carbon::setTestNow();
    }

    public function test_repeat_generation_is_idempotent(): void
    {
        Carbon::setTestNow('2026-04-15');
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('incomes.store', $workspace), [
                'description' => 'Salário', 'value' => 100, 'date' => '2026-01-01',
                'account_id' => $account->uuid, 'category_id' => $category->uuid,
                'is_recurring' => true,
            ]);
        $template = Transaction::where('is_recurring', true)->whereNull('recurring_parent_uuid')->first();
        $count = Transaction::where('recurring_parent_uuid', $template->uuid)->count();

        $service = app(RecurringIncomeService::class);
        $new = $service->generateOccurrencesUpTo($template, Carbon::parse('2026-04-01')->startOfMonth());
        $this->assertEquals(0, $new);
        $this->assertEquals($count, Transaction::where('recurring_parent_uuid', $template->uuid)->count());
        Carbon::setTestNow();
    }

    public function test_update_template_with_propagate_touches_only_unpaid_occurrences(): void
    {
        Carbon::setTestNow('2026-03-15');
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('incomes.store', $workspace), [
                'description' => 'Salário', 'value' => 1000, 'date' => '2026-01-05',
                'account_id' => $account->uuid, 'category_id' => $category->uuid,
                'is_recurring' => true,
            ]);
        $template = Transaction::where('is_recurring', true)->whereNull('recurring_parent_uuid')->first();
        $occurrences = Transaction::where('recurring_parent_uuid', $template->uuid)->orderBy('recurring_year_month')->get();
        $this->actingAs($user)->post(route('incomes.receive', [$workspace, $occurrences->first()]));

        $this->actingAs($user)
            ->put(route('recurring-incomes.update', [$workspace, $template]), [
                'description' => 'Salário Atualizado',
                'value' => 1200,
                'category_id' => $category->uuid,
                'account_id' => $account->uuid,
                'propagate' => true,
            ]);

        $received = $occurrences->first()->refresh();
        $unreceived = $occurrences->last()->refresh();

        $this->assertEquals('Salário', $received->description);
        $this->assertEquals(1000, (float) $received->value);

        $this->assertEquals('Salário Atualizado', $unreceived->description);
        $this->assertEquals(1200, (float) $unreceived->value);
        Carbon::setTestNow();
    }

    public function test_cancel_template_sets_recurring_ends_at(): void
    {
        Carbon::setTestNow('2026-03-15');
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('incomes.store', $workspace), [
                'description' => 'Salário', 'value' => 1000, 'date' => '2026-01-05',
                'account_id' => $account->uuid, 'category_id' => $category->uuid,
                'is_recurring' => true,
            ]);
        $template = Transaction::where('is_recurring', true)->whereNull('recurring_parent_uuid')->first();

        $this->actingAs($user)
            ->post(route('recurring-incomes.cancel', [$workspace, $template]));

        $template->refresh();
        $this->assertNotNull($template->recurring_ends_at);
        $this->assertEquals('2026-03-15', $template->recurring_ends_at->format('Y-m-d'));
        Carbon::setTestNow();
    }

    public function test_delete_template_orphan_true_promotes_occurrences_to_standalone(): void
    {
        Carbon::setTestNow('2026-03-15');
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('incomes.store', $workspace), [
                'description' => 'Salário', 'value' => 1000, 'date' => '2026-01-05',
                'account_id' => $account->uuid, 'category_id' => $category->uuid,
                'is_recurring' => true,
            ]);
        $template = Transaction::where('is_recurring', true)->whereNull('recurring_parent_uuid')->first();
        $occurrenceCount = Transaction::where('recurring_parent_uuid', $template->uuid)->count();

        $this->actingAs($user)
            ->delete(route('recurring-incomes.destroy', [$workspace, $template]).'?orphan_occurrences=1');

        $this->assertSoftDeleted('transactions', ['id' => $template->id]);
        $standalones = Transaction::whereNull('recurring_parent_uuid')->where('is_recurring', false)->where('description', 'Salário')->get();
        $this->assertEquals($occurrenceCount, $standalones->count());
        Carbon::setTestNow();
    }

    public function test_delete_template_orphan_false_deletes_only_unreceived_occurrences(): void
    {
        Carbon::setTestNow('2026-03-15');
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user, 1000);
        $category = $this->createIncomeCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('incomes.store', $workspace), [
                'description' => 'Salário', 'value' => 1000, 'date' => '2026-01-05',
                'account_id' => $account->uuid, 'category_id' => $category->uuid,
                'is_recurring' => true,
            ]);
        $template = Transaction::where('is_recurring', true)->whereNull('recurring_parent_uuid')->first();
        $occurrences = Transaction::where('recurring_parent_uuid', $template->uuid)->orderBy('recurring_year_month')->get();
        $this->actingAs($user)->post(route('incomes.receive', [$workspace, $occurrences->first()]));

        $this->actingAs($user)
            ->delete(route('recurring-incomes.destroy', [$workspace, $template]).'?orphan_occurrences=0');

        $this->assertSoftDeleted('transactions', ['id' => $template->id]);
        $received = $occurrences->first()->fresh();
        $this->assertNull($received->deleted_at);
        $unreceivedKept = collect();
        foreach ($occurrences->skip(1) as $row) {
            $unreceivedKept->push($row->fresh()->deleted_at);
        }
        $this->assertTrue($unreceivedKept->every(fn ($v) => $v !== null));
        Carbon::setTestNow();
    }

    public function test_index_lists_only_active_templates(): void
    {
        Carbon::setTestNow('2026-03-15');
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('incomes.store', $workspace), [
                'description' => 'Salário', 'value' => 1000, 'date' => '2026-01-05',
                'account_id' => $account->uuid, 'category_id' => $category->uuid,
                'is_recurring' => true,
            ]);

        $response = $this->actingAs($user)
            ->get(route('recurring-incomes.index', $workspace));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('RecurringIncomes/Index', false)
            ->has('templates', 1));
        Carbon::setTestNow();
    }

    public function test_cross_workspace_recurring_returns_404(): void
    {
        Carbon::setTestNow('2026-03-15');
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);

        $this->actingAs($user)
            ->post(route('incomes.store', $workspace), [
                'description' => 'Salário', 'value' => 1000, 'date' => '2026-01-05',
                'account_id' => $account->uuid, 'category_id' => $category->uuid,
                'is_recurring' => true,
            ]);
        $template = Transaction::where('is_recurring', true)->whereNull('recurring_parent_uuid')->first();

        [$otherUser, $otherWorkspace] = $this->createWorkspaceWithMember();

        $response = $this->actingAs($otherUser)
            ->put(route('recurring-incomes.update', [$otherWorkspace, $template->uuid]), [
                'description' => 'Hacker', 'propagate' => true,
            ]);
        $response->assertNotFound();
        Carbon::setTestNow();
    }
}
