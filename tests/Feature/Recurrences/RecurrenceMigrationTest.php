<?php

declare(strict_types=1);

namespace Tests\Feature\Recurrences;

use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RecurrenceMigrationTest extends TestCase
{
    public function test_recurrences_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('recurrences'));
    }

    public function test_recurrences_table_has_expected_columns(): void
    {
        $columns = [
            'id', 'uuid', 'workspace_id', 'account_id', 'category_id',
            'type', 'description', 'value', 'frequency', 'frequency_day',
            'start_date', 'until_date', 'next_date', 'status', 'created_by',
            'created_at', 'updated_at', 'deleted_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('recurrences', $column),
                "Missing column: {$column}"
            );
        }
    }

    public function test_transactions_has_recurrence_id_column(): void
    {
        $this->assertTrue(Schema::hasColumn('transactions', 'recurrence_id'));
    }

    public function test_recurrence_id_is_nullable(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => 'admin']);

        $account = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'income',
        ]);

        $id = DB::table('transactions')->insertGetId([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'type' => 'income',
            'description' => 'Test nullable recurrence_id',
            'value' => 100.00,
            'date' => '2026-07-20',
            'created_by' => $user->id,
            'recurrence_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $transaction = DB::table('transactions')->find($id);
        $this->assertNotNull($transaction);
        $this->assertNull($transaction->recurrence_id);
    }

    public function test_invalid_recurrence_id_throws_database_exception(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => 'admin']);

        $account = Account::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $category = Category::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'type' => 'income',
        ]);

        $this->expectException(QueryException::class);

        DB::table('transactions')->insert([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'type' => 'income',
            'description' => 'Test invalid recurrence_id',
            'value' => 100.00,
            'date' => '2026-07-20',
            'created_by' => $user->id,
            'recurrence_id' => 999999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
