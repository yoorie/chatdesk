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
        Schema::create('contact_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('filters')->nullable(); // Filter criteria for segment
            $table->integer('contact_count')->default(0); // Cached count
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();

            $table->index('account_id');
        });

        // Pivot table for contact-segment relationship
        Schema::create('contact_segment_contact', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('whatsapp_contacts')->onDelete('cascade');
            $table->foreignId('contact_segment_id')->constrained('contact_segments')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['contact_id', 'contact_segment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_segment_contact');
        Schema::dropIfExists('contact_segments');
    }
};
