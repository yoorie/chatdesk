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
        Schema::create('contact_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('color')->default('#3B82F6'); // Hex color for UI
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'name']);
            $table->index('account_id');
        });

        // Pivot table for contact-tag relationship
        Schema::create('contact_tag_contact', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('whatsapp_contacts')->onDelete('cascade');
            $table->foreignId('contact_tag_id')->constrained('contact_tags')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['contact_id', 'contact_tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_tag_contact');
        Schema::dropIfExists('contact_tags');
    }
};
