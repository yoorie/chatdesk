<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_orders', 'billing_cycle')) {
                $table->string('billing_cycle', 20)->nullable()->after('currency')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_orders', function (Blueprint $table) {
            if (Schema::hasColumn('payment_orders', 'billing_cycle')) {
                $table->dropIndex(['billing_cycle']);
                $table->dropColumn('billing_cycle');
            }
        });
    }
};
