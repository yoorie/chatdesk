<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_orders', 'payment_method')) {
                $table->string('payment_method', 40)->nullable()->after('provider');
            }
            if (! Schema::hasColumn('payment_orders', 'proof_path')) {
                $table->string('proof_path')->nullable()->after('tax_snapshot');
            }
            if (! Schema::hasColumn('payment_orders', 'proof_original_name')) {
                $table->string('proof_original_name')->nullable()->after('proof_path');
            }
            if (! Schema::hasColumn('payment_orders', 'proof_uploaded_at')) {
                $table->timestamp('proof_uploaded_at')->nullable()->after('proof_original_name');
            }
            if (! Schema::hasColumn('payment_orders', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('payment_orders', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (! Schema::hasColumn('payment_orders', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('approved_at');
            }
            if (! Schema::hasColumn('payment_orders', 'rejection_reason')) {
                $table->string('rejection_reason')->nullable()->after('rejected_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_orders', function (Blueprint $table) {
            foreach ([
                'rejection_reason',
                'rejected_at',
                'approved_at',
                'approved_by',
                'proof_uploaded_at',
                'proof_original_name',
                'proof_path',
                'payment_method',
            ] as $column) {
                if (Schema::hasColumn('payment_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
