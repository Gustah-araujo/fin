<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvitesTable extends Migration
{
    public function up(): void
    {
        Schema::create("invites", function (Blueprint $table) {
            $table->id();
            $table->uuid("uuid")->unique();
            $table->foreignId("workspace_id")->constrained("workspaces")->cascadeOnDelete();
            $table->string("email");
            $table->string("role");
            $table->foreignId("inviter_id")->constrained("users")->cascadeOnDelete();
            $table->string("status")->default("pending");
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("invites");
    }
};
