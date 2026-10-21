<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_usage', function (Blueprint $table) {
            if (! Schema::hasColumn('account_usage', 'ai_requests')) {
                $table->unsignedInteger('ai_requests')->default(0)->after('ai_credits_used');
            }
            if (! Schema::hasColumn('account_usage', 'ai_estimated_tokens')) {
                $table->unsignedInteger('ai_estimated_tokens')->default(0)->after('ai_requests');
            }
            if (! Schema::hasColumn('account_usage', 'ai_estimated_cost_minor')) {
                $table->unsignedInteger('ai_estimated_cost_minor')->default(0)->after('ai_estimated_tokens');
            }
            if (! Schema::hasColumn('account_usage', 'razorpay_payment_links_created')) {
                $table->unsignedInteger('razorpay_payment_links_created')->default(0)->after('template_sends');
            }
            if (! Schema::hasColumn('account_usage', 'razorpay_payment_links_paid')) {
                $table->unsignedInteger('razorpay_payment_links_paid')->default(0)->after('razorpay_payment_links_created');
            }
        });
    }

    public function down(): void
    {
        Schema::table('account_usage', function (Blueprint $table) {
            foreach ([
                'ai_requests',
                'ai_estimated_tokens',
                'ai_estimated_cost_minor',
                'razorpay_payment_links_created',
                'razorpay_payment_links_paid',
            ] as $column) {
                if (Schema::hasColumn('account_usage', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
