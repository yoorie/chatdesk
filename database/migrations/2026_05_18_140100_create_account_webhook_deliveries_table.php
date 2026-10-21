<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_webhook_endpoint_id')->nullable()->constrained()->nullOnDelete();
            $table->string('delivery_id')->index();
            $table->string('event');
            $table->string('url', 500);
            $table->unsignedSmallInteger('status')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('payload')->nullable();
            $table->text('response_body')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->unique(['account_webhook_endpoint_id', 'delivery_id'], 'webhook_deliveries_endpoint_delivery_unique');
            $table->index(['account_id', 'created_at']);
            $table->index(['account_id', 'event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_webhook_deliveries');
    }
};
