<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('token_prefix', 24)->index();
            $table->string('token_hash', 64)->unique();
            $table->json('scopes')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_api_keys');
    }
};
