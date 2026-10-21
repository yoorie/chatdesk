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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('price_monthly')->nullable(); // in minor units (paise)
            $table->integer('price_yearly')->nullable();
            $table->string('currency')->default('INR');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);
            $table->integer('trial_days')->default(0);
            $table->integer('sort_order')->default(0);
            $table->json('limits')->nullable();
            $table->json('modules')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
