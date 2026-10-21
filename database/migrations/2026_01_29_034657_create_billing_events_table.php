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
        Schema::create('billing_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->foreignId('actor_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('type'); // plan_changed|trial_started|payment_recorded|subscription_canceled|addon_added|limit_blocked
            $table->json('data')->nullable();
            $table->timestamps();

            $table->index('account_id');
            $table->index('type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_events');
    }
};
