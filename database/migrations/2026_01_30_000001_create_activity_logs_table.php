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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // 'user_action', 'system_event', 'webhook', 'api_call', 'error'
            $table->text('description');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('account_id')->nullable()->constrained()->onDelete('cascade');
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['type', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['account_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
