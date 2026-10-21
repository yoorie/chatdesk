<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_requests', function (Blueprint $table) {
            $table->foreignId('converted_account_id')->nullable()->after('handled_by')->constrained('accounts')->nullOnDelete();
            $table->foreignId('converted_contact_id')->nullable()->after('converted_account_id')->constrained('whatsapp_contacts')->nullOnDelete();
            $table->timestamp('converted_at')->nullable()->after('converted_contact_id');
        });
    }

    public function down(): void
    {
        Schema::table('contact_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('converted_contact_id');
            $table->dropConstrainedForeignId('converted_account_id');
            $table->dropColumn('converted_at');
        });
    }
};
