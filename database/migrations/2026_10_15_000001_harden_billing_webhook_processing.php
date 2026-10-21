<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_orders', function (Blueprint $table) {
            $table->timestamp('subscription_activated_at')->nullable();
        });
        Schema::create('billing_webhook_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 64)->unique();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        // Preserve completed historical activations, while allowing paid orders
        // whose activation failed to be recovered by a later delivery.
        DB::table('payment_orders')->where('status', 'paid')->orderBy('id')->chunkById(100, function ($orders) {
            foreach ($orders as $order) {
                $timeline = json_decode($order->metadata ?? '{}', true)['timeline'] ?? [];
                foreach ($timeline as $entry) {
                    if (in_array($entry['event'] ?? '', ['subscription_activated', 'subscription_renewed'], true)) {
                        DB::table('payment_orders')->where('id', $order->id)->update([
                            'subscription_activated_at' => $order->paid_at ?? $order->updated_at,
                        ]);
                        break;
                    }
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_webhook_receipts');
        Schema::table('payment_orders', fn (Blueprint $table) => $table->dropColumn('subscription_activated_at'));
    }
};
