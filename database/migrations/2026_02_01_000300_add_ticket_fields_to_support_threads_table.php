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
            $table->string('priority')->default('normal')->after('channel'); // low|normal|high|urgent
            $table->string('category')->nullable()->after('priority');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete()->after('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('support_threads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn(['priority', 'category']);
        });
    }
};
