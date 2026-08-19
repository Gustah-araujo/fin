<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('is_recurring')->default(false)->after('installment_group_id');
            $table->uuid('recurring_parent_uuid')->nullable()->after('is_recurring');
            $table->date('recurring_ends_at')->nullable()->after('recurring_parent_uuid');
            $table->char('recurring_year_month', 7)->nullable()->after('recurring_ends_at');
            $table->uuid('transfer_group_id')->nullable()->after('recurring_year_month');

            $table->unique(['recurring_parent_uuid', 'recurring_year_month'], 'uniq_recurring_occurrence');
            $table->index('transfer_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('uniq_recurring_occurrence');
            $table->dropIndex(['transfer_group_id']);
            $table->dropColumn([
                'is_recurring',
                'recurring_parent_uuid',
                'recurring_ends_at',
                'recurring_year_month',
                'transfer_group_id',
            ]);
        });
    }
};
