<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("accounts", function (Blueprint $table) {
            $table->id();
            $table->uuid("uuid")->unique();
            $table->foreignId("workspace_id")->constrained()->cascadeOnDelete();
            $table->foreignId("created_by")->nullable()->constrained("users")->nullOnDelete();
            $table->string("name");
            $table->string("type");
            $table->decimal("initial_balance", 15, 2)->default(0);
            $table->decimal("current_balance", 15, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("accounts");
    }
};
