<?php

declare(strict_types=1);

namespace Tests\Feature\Incomes;

use App\Models\Account;
use App\Models\Category;
use App\Models\Workspace;

class IncomeValidationTest extends IncomeTestCase
{
    public function test_required_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), []);

        $response->assertSessionHasErrors(['description', 'value', 'date', 'account_id', 'category_id']);
    }

    public function test_expense_category_is_rejected(): void
    {
        $expenseCategory = Category::factory()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
            'type' => 'expense',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData([
                'category_id' => $expenseCategory->uuid,
            ]));

        $response->assertSessionHasErrors(['category_id']);
    }

    public function test_archived_account_is_rejected(): void
    {
        $archived = Account::factory()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);
        $archived->delete();

        $response = $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData([
                'account_id' => $archived->uuid,
            ]));

        $response->assertSessionHasErrors(['account_id']);
    }

    public function test_cross_workspace_account_is_rejected(): void
    {
        $otherWorkspace = Workspace::factory()->create();
        $externalAccount = Account::factory()->create([
            'workspace_id' => $otherWorkspace->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData([
                'account_id' => $externalAccount->uuid,
            ]));

        $response->assertSessionHasErrors(['account_id']);
    }

    public function test_zero_value_is_rejected(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData(['value' => 0]));

        $response->assertSessionHasErrors(['value']);
    }

    public function test_weekly_frequency_day_out_of_range_is_rejected(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData([
                'is_recurring' => true,
                'frequency' => 'weekly',
                'frequency_day' => 9,
            ]));

        $response->assertSessionHasErrors(['frequency_day']);
    }

    public function test_monthly_frequency_day_out_of_range_is_rejected(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData([
                'is_recurring' => true,
                'frequency' => 'monthly',
                'frequency_day' => 32,
            ]));

        $response->assertSessionHasErrors(['frequency_day']);
    }

    public function test_until_date_before_start_date_is_rejected(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData([
                'is_recurring' => true,
                'frequency' => 'monthly',
                'frequency_day' => 5,
                'until_date' => '2020-01-01',
            ]));

        $response->assertSessionHasErrors(['until_date']);
    }

    public function test_recurring_income_requires_frequency(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('incomes.store', $this->workspace), $this->validIncomeData([
                'is_recurring' => true,
            ]));

        $response->assertSessionHasErrors(['frequency', 'frequency_day']);
    }
}
