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
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('status')->default('active')->after('slug'); // active|suspended|disabled
            $table->text('disabled_reason')->nullable()->after('status');
            $table->timestamp('disabled_at')->nullable()->after('disabled_reason');

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'disabled_reason', 'disabled_at']);
        });
    }
};
