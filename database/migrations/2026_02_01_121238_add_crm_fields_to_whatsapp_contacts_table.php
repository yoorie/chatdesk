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
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            $table->string('email')->nullable()->after('name');
            $table->string('phone')->nullable()->after('email');
            $table->string('company')->nullable()->after('phone');
            $table->text('notes')->nullable()->after('company');
            $table->enum('status', ['active', 'inactive', 'blocked', 'opt_out'])->default('active')->after('notes');
            $table->string('source')->nullable()->after('status'); // How contact was added
            $table->timestamp('last_contacted_at')->nullable()->after('last_seen_at');
            $table->integer('message_count')->default(0)->after('last_contacted_at');
            $table->json('custom_fields')->nullable()->after('metadata');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            $table->dropColumn([
                'email',
                'phone',
                'company',
                'notes',
                'status',
                'source',
                'last_contacted_at',
                'message_count',
                'custom_fields',
            ]);
        });
    }
};
