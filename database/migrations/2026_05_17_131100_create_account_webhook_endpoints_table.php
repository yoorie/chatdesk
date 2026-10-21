<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->string('secret')->nullable();
            $table->json('events')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('last_tested_at')->nullable();
            $table->unsignedSmallInteger('last_status')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_webhook_endpoints');
    }
};
