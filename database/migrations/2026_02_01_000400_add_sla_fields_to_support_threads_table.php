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
        Schema::table('support_threads', function (Blueprint $table) {
            $table->timestamp('first_response_due_at')->nullable()->after('assigned_to');
            $table->timestamp('first_response_at')->nullable()->after('first_response_due_at');
            $table->timestamp('due_at')->nullable()->after('first_response_at');
            $table->timestamp('resolved_at')->nullable()->after('due_at');
            $table->timestamp('last_response_at')->nullable()->after('resolved_at');
            $table->timestamp('escalated_at')->nullable()->after('last_response_at');
            $table->unsignedInteger('escalation_level')->default(0)->after('escalated_at');
            $table->json('tags')->nullable()->after('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('support_threads', function (Blueprint $table) {
            $table->dropColumn([
                'first_response_due_at',
                'first_response_at',
                'due_at',
                'resolved_at',
                'last_response_at',
                'escalated_at',
                'escalation_level',
                'tags',
            ]);
        });
    }
};
