<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (! Schema::hasColumn('plans', 'razorpay_plan_id')) {
                $table->string('razorpay_plan_id')->nullable()->after('currency')->index();
            }
            if (! Schema::hasColumn('plans', 'billing_period')) {
                $table->string('billing_period')->default('monthly')->after('razorpay_plan_id');
            }
            if (! Schema::hasColumn('plans', 'billing_interval')) {
                $table->unsignedInteger('billing_interval')->default(1)->after('billing_period');
            }
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'provider_plan_ref')) {
                $table->string('provider_plan_ref')->nullable()->after('provider_ref')->index();
            }
            if (! Schema::hasColumn('subscriptions', 'provider_customer_ref')) {
                $table->string('provider_customer_ref')->nullable()->after('provider_plan_ref')->index();
            }
            if (! Schema::hasColumn('subscriptions', 'provider_status')) {
                $table->string('provider_status')->nullable()->after('provider_customer_ref');
            }
            if (! Schema::hasColumn('subscriptions', 'provider_payload')) {
                $table->json('provider_payload')->nullable()->after('provider_status');
            }
            if (! Schema::hasColumn('subscriptions', 'discount_code')) {
                $table->string('discount_code')->nullable()->after('provider_payload')->index();
            }
            if (! Schema::hasColumn('subscriptions', 'discount_snapshot')) {
                $table->json('discount_snapshot')->nullable()->after('discount_code');
            }
        });

        Schema::table('payment_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_orders', 'provider_subscription_id')) {
                $table->string('provider_subscription_id')->nullable()->after('provider_payment_id')->index();
            }
            if (! Schema::hasColumn('payment_orders', 'discount_code')) {
                $table->string('discount_code')->nullable()->after('currency')->index();
            }
            if (! Schema::hasColumn('payment_orders', 'discount_amount')) {
                $table->integer('discount_amount')->default(0)->after('discount_code');
            }
        });

        Schema::create('billing_discounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('discount_type'); // percent|fixed
            $table->unsignedInteger('percent_off')->nullable();
            $table->integer('amount_off')->nullable();
            $table->string('currency', 3)->default('INR');
            $table->string('duration')->default('once'); // once|recurring|forever
            $table->unsignedInteger('duration_cycles')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redemptions')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('plan_keys')->nullable();
            $table->string('provider')->nullable();
            $table->string('provider_offer_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_discounts');

        Schema::table('payment_orders', function (Blueprint $table) {
            foreach (['provider_subscription_id', 'discount_code', 'discount_amount'] as $column) {
                if (Schema::hasColumn('payment_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            foreach (['provider_plan_ref', 'provider_customer_ref', 'provider_status', 'provider_payload', 'discount_code', 'discount_snapshot'] as $column) {
                if (Schema::hasColumn('subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('plans', function (Blueprint $table) {
            foreach (['razorpay_plan_id', 'billing_period', 'billing_interval'] as $column) {
                if (Schema::hasColumn('plans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
