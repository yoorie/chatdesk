<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive: upgrade credit (paid periods + order credit) and scheduled downgrades.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'paid_periods')) {
                $table->json('paid_periods')->nullable();
            }
            if (! Schema::hasColumn('subscriptions', 'scheduled_plan_id')) {
                $table->unsignedBigInteger('scheduled_plan_id')->nullable()->index();
            }
            if (! Schema::hasColumn('subscriptions', 'scheduled_billing_cycle')) {
                $table->string('scheduled_billing_cycle', 16)->nullable();
            }
            if (! Schema::hasColumn('subscriptions', 'scheduled_change_at')) {
                $table->timestamp('scheduled_change_at')->nullable()->index();
            }
        });

        Schema::table('payment_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_orders', 'credit_amount')) {
                $table->unsignedBigInteger('credit_amount')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            foreach (['paid_periods', 'scheduled_plan_id', 'scheduled_billing_cycle', 'scheduled_change_at'] as $column) {
                if (Schema::hasColumn('subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('payment_orders', function (Blueprint $table) {
            if (Schema::hasColumn('payment_orders', 'credit_amount')) {
                $table->dropColumn('credit_amount');
            }
        });
    }
};
