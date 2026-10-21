<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_usage', function (Blueprint $table) {
            if (! Schema::hasColumn('account_usage', 'meta_conversations_free_used')) {
                $table->integer('meta_conversations_free_used')->default(0)->after('ai_credits_used');
            }
            if (! Schema::hasColumn('account_usage', 'meta_conversations_paid')) {
                $table->integer('meta_conversations_paid')->default(0)->after('meta_conversations_free_used');
            }
            if (! Schema::hasColumn('account_usage', 'meta_conversations_marketing')) {
                $table->integer('meta_conversations_marketing')->default(0)->after('meta_conversations_paid');
            }
            if (! Schema::hasColumn('account_usage', 'meta_conversations_utility')) {
                $table->integer('meta_conversations_utility')->default(0)->after('meta_conversations_marketing');
            }
            if (! Schema::hasColumn('account_usage', 'meta_conversations_authentication')) {
                $table->integer('meta_conversations_authentication')->default(0)->after('meta_conversations_utility');
            }
            if (! Schema::hasColumn('account_usage', 'meta_conversations_service')) {
                $table->integer('meta_conversations_service')->default(0)->after('meta_conversations_authentication');
            }
            if (! Schema::hasColumn('account_usage', 'meta_estimated_cost_minor')) {
                $table->bigInteger('meta_estimated_cost_minor')->default(0)->after('meta_conversations_service');
            }
        });
    }

    public function down(): void
    {
        Schema::table('account_usage', function (Blueprint $table) {
            foreach ([
                'meta_conversations_free_used',
                'meta_conversations_paid',
                'meta_conversations_marketing',
                'meta_conversations_utility',
                'meta_conversations_authentication',
                'meta_conversations_service',
                'meta_estimated_cost_minor',
            ] as $column) {
                if (Schema::hasColumn('account_usage', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
