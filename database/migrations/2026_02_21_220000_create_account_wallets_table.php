<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->bigInteger('balance_minor')->default(0);
            $table->string('currency', 3)->default('INR');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('account_id');
            $table->index(['currency', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_wallets');
    }
};
