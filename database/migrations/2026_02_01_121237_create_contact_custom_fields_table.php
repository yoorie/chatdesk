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
        Schema::create('contact_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('type')->default('text'); // text, number, date, email, phone, select, multiselect
            $table->json('options')->nullable(); // For select/multiselect fields
            $table->boolean('required')->default(false);
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->unique(['account_id', 'name']);
            $table->index('account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_custom_fields');
    }
};
