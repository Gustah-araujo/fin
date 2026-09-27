<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurrences', function (Blueprint $table) {
            $table->uuid('credit_card_id')->nullable()->after('account_id')->index();
            $table->foreign('credit_card_id')->references('uuid')->on('credit_cards')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('recurrences', function (Blueprint $table) {
            $table->dropForeign(['credit_card_id']);
            $table->dropIndex(['credit_card_id']);
            $table->dropColumn('credit_card_id');
        });
    }
};
