<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkspacesTable extends Migration
{
    public function up(): void
    {
        Schema::create("workspaces", function (Blueprint $table) {
            $table->id();
            $table->uuid("uuid")->unique();
            $table->string("name");
            $table->text("description")->nullable();
            $table->timestamps();
        });

        Schema::create("workspace_user", function (Blueprint $table) {
            $table->foreignId("workspace_id")->constrained("workspaces")->cascadeOnDelete();
            $table->foreignId("user_id")->constrained("users")->cascadeOnDelete();
            $table->string("role")->default("admin");
            $table->timestamp("last_visited_at")->nullable();
            $table->timestamps();

            $table->primary(["workspace_id", "user_id"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("workspace_user");
        Schema::dropIfExists("workspaces");
    }
};
