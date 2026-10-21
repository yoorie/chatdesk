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
        Schema::table('whatsapp_conversations', function (Blueprint $table): void {
            $table->dropForeign(['whatsapp_contact_id']);
            $table->foreign('whatsapp_contact_id')
                ->references('id')
                ->on('whatsapp_contacts')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table): void {
            $table->dropForeign(['whatsapp_contact_id']);
            $table->foreign('whatsapp_contact_id')
                ->references('id')
                ->on('whatsapp_contacts')
                ->cascadeOnDelete();
        });
    }
};
