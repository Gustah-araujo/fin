<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurrences', function (Blueprint $table) {
            $table->unsignedTinyInteger('buffer_ahead')->default(12)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('recurrences', function (Blueprint $table) {
            $table->dropColumn('buffer_ahead');
        });
    }
};
