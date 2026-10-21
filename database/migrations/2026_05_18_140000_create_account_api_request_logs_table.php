<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_api_request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('account_api_key_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 12);
            $table->string('path', 500);
            $table->string('route_name')->nullable();
            $table->unsignedSmallInteger('status');
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('ip')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('request_id')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'created_at']);
            $table->index(['account_api_key_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_api_request_logs');
    }
};
