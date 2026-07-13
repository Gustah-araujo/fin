<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUuidGoogleToUsersTable extends Migration
{
    public function up(): void
    {
        Schema::table("users", function (Blueprint $table) {
            $table->uuid("uuid")->unique()->after("id");
            $table->string("google_id")->nullable()->unique()->after("email");
            $table->string("avatar")->nullable()->after("google_id");
        });

        Schema::table("users", function (Blueprint $table) {
            $table->string("password")->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table("users", function (Blueprint $table) {
            $table->dropColumn(["uuid", "google_id", "avatar"]);
            $table->string("password")->nullable(false)->change();
        });
    }
};
