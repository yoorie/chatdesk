<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('status')->default('disconnected');
            $table->json('config')->nullable();
            $table->json('features')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->unsignedInteger('events_24h')->default(0);
            $table->string('health')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'provider']);
            $table->index(['account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_integrations');
    }
};
