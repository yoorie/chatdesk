<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->foreignId('account_wallet_id')->constrained('account_wallets')->onDelete('cascade');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('direction', 10); // credit|debit
            $table->bigInteger('amount_minor');
            $table->bigInteger('balance_after_minor')->default(0);
            $table->string('currency', 3)->default('INR');
            $table->string('source', 50); // topup|plan_proration_charge|plan_proration_credit|meta_usage_charge|manual_adjustment
            $table->string('status', 20)->default('success'); // success|failed|pending
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'status']);
            $table->index(['account_id', 'source']);
            $table->index(['reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
