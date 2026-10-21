<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('name');
            $table->string('key');
            $table->text('description')->nullable();
            $table->json('permissions')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['account_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_roles');
    }
};
