<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_card_bills', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('credit_card_id')->constrained('credit_cards')->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->year('period_year');
            $table->tinyInteger('period_month');
            $table->date('closing_date');
            $table->date('due_date');
            $table->string('status')->default('open');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_to_account_id')->nullable()->constrained('accounts');
            $table->foreignId('payment_transaction_id')->nullable()->constrained('transactions');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['credit_card_id', 'period_year', 'period_month']);
            $table->index(['credit_card_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_card_bills');
    }
};
