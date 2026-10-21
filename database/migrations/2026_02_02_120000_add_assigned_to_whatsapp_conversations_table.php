<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_conversations', 'assigned_to')) {
                $table->foreignId('assigned_to')
                    ->nullable()
                    ->after('whatsapp_contact_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_conversations', 'assigned_to')) {
                $table->dropConstrainedForeignId('assigned_to');
            }
        });
    }
};
