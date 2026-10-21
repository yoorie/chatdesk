<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            foreach ($this->accountColumns() as $column => $definition) {
                if (! Schema::hasColumn('accounts', $column)) {
                    $definition($table);
                }
            }
        });

        Schema::table('payment_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_orders', 'invoice_number')) {
                $table->string('invoice_number')->nullable()->unique()->after('provider_subscription_id');
            }
            if (! Schema::hasColumn('payment_orders', 'base_amount')) {
                $table->integer('base_amount')->default(0)->after('currency');
            }
            if (! Schema::hasColumn('payment_orders', 'taxable_amount')) {
                $table->integer('taxable_amount')->default(0)->after('discount_amount');
            }
            if (! Schema::hasColumn('payment_orders', 'tax_amount')) {
                $table->integer('tax_amount')->default(0)->after('taxable_amount');
            }
            if (! Schema::hasColumn('payment_orders', 'tax_rate')) {
                $table->decimal('tax_rate', 6, 2)->default(0)->after('tax_amount');
            }
            if (! Schema::hasColumn('payment_orders', 'cgst_amount')) {
                $table->integer('cgst_amount')->default(0)->after('tax_rate');
            }
            if (! Schema::hasColumn('payment_orders', 'sgst_amount')) {
                $table->integer('sgst_amount')->default(0)->after('cgst_amount');
            }
            if (! Schema::hasColumn('payment_orders', 'igst_amount')) {
                $table->integer('igst_amount')->default(0)->after('sgst_amount');
            }
            if (! Schema::hasColumn('payment_orders', 'tax_snapshot')) {
                $table->json('tax_snapshot')->nullable()->after('igst_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_orders', function (Blueprint $table) {
            foreach ([
                'tax_snapshot',
                'igst_amount',
                'sgst_amount',
                'cgst_amount',
                'tax_rate',
                'tax_amount',
                'taxable_amount',
                'base_amount',
                'invoice_number',
            ] as $column) {
                if (Schema::hasColumn('payment_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('accounts', function (Blueprint $table) {
            foreach (array_keys($this->accountColumns()) as $column) {
                if (Schema::hasColumn('accounts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function accountColumns(): array
    {
        return [
            'billing_name' => fn (Blueprint $table) => $table->string('billing_name')->nullable()->after('logo_path'),
            'billing_email' => fn (Blueprint $table) => $table->string('billing_email')->nullable()->after('billing_name'),
            'billing_gstin' => fn (Blueprint $table) => $table->string('billing_gstin', 20)->nullable()->after('billing_email'),
            'billing_address_line1' => fn (Blueprint $table) => $table->string('billing_address_line1')->nullable()->after('billing_gstin'),
            'billing_address_line2' => fn (Blueprint $table) => $table->string('billing_address_line2')->nullable()->after('billing_address_line1'),
            'billing_city' => fn (Blueprint $table) => $table->string('billing_city')->nullable()->after('billing_address_line2'),
            'billing_state' => fn (Blueprint $table) => $table->string('billing_state')->nullable()->after('billing_city'),
            'billing_state_code' => fn (Blueprint $table) => $table->string('billing_state_code', 8)->nullable()->after('billing_state'),
            'billing_postal_code' => fn (Blueprint $table) => $table->string('billing_postal_code', 20)->nullable()->after('billing_state_code'),
            'billing_country' => fn (Blueprint $table) => $table->string('billing_country', 2)->default('IN')->after('billing_postal_code'),
        ];
    }
};
