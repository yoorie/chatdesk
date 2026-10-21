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
        Schema::create('whatsapp_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->foreignId('whatsapp_connection_id')->constrained()->onDelete('cascade');
            $table->string('name'); // List name (e.g., "Product Catalog", "Support Options")
            $table->string('button_text', 20); // Button text (max 20 chars)
            $table->text('description')->nullable(); // Optional description
            $table->string('footer_text', 60)->nullable(); // Optional footer (max 60 chars)
            $table->json('sections'); // Array of sections, each with title and rows
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['account_id', 'whatsapp_connection_id']);
            $table->index(['account_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_lists');
    }
};
