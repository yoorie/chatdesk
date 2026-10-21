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
        // Older local snapshots recorded this migration with the 034657 timestamp.
        if (Schema::hasTable('account_addons')) {
            return;
        }

        Schema::create('account_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->foreignId('addon_id')->constrained('plan_addons')->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->string('status')->default('active'); // active|canceled
            $table->timestamp('started_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'addon_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_addons');
    }
};
