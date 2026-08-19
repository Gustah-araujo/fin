<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurrences', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts');
            $table->foreignId('category_id')->constrained('categories');
            $table->string('type')->default('income');
            $table->string('description');
            $table->decimal('value', 15, 2);
            $table->string('frequency');
            $table->unsignedTinyInteger('frequency_day');
            $table->date('start_date');
            $table->date('until_date')->nullable();
            $table->date('next_date')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'next_date']);
            $table->index(['workspace_id', 'deleted_at', 'next_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurrences');
    }
};
