<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('credit_card_id')->nullable()->after('account_id')->constrained('credit_cards');
            $table->foreignId('credit_card_bill_id')->nullable()->after('credit_card_id')->constrained('credit_card_bills');
            $table->integer('installment_number')->nullable()->after('date');
            $table->integer('installments_total')->nullable()->after('installment_number');
            $table->uuid('installment_group_id')->nullable()->after('installments_total');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['installment_group_id', 'installments_total', 'installment_number']);
            $table->dropConstrainedForeignId('credit_card_bill_id');
            $table->dropConstrainedForeignId('credit_card_id');
        });
    }
};
