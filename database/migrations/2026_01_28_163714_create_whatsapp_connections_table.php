<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whatsapp_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('waba_id')->nullable();
            $table->string('phone_number_id');
            $table->string('business_phone')->nullable();
            $table->text('access_token_encrypted');
            $table->string('api_version')->default('v20.0');
            $table->string('webhook_verify_token')->unique();
            $table->boolean('webhook_subscribed')->default(false);
            $table->timestamp('webhook_last_received_at')->nullable();
            $table->text('webhook_last_error')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['account_id', 'phone_number_id']);
            $table->index(['account_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_connections');
    }
};
