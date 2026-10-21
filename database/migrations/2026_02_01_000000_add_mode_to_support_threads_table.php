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
            $table->string('mode')->default('bot')->after('status'); // bot|human
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('support_threads', function (Blueprint $table) {
            $table->dropColumn('mode');
        });
    }
};
