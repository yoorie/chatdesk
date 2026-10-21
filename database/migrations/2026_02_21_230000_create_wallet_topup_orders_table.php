<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_topup_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider')->default('razorpay');
            $table->string('provider_order_id')->unique();
            $table->string('provider_payment_id')->nullable();
            $table->integer('amount');
            $table->string('currency', 3)->default('INR');
            $table->string('status', 20)->default('created');
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'status']);
            $table->index(['provider', 'provider_payment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_topup_orders');
    }
};
