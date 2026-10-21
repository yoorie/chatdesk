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
        Schema::create('contact_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->foreignId('contact_id')->constrained('whatsapp_contacts')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('type'); // message_sent, message_received, note_added, tag_added, field_updated, etc.
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Additional data
            $table->timestamps();

            $table->index(['account_id', 'contact_id']);
            $table->index(['contact_id', 'created_at']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_activities');
    }
};
