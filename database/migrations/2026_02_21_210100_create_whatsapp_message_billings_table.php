<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_message_billings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->string('meta_message_id');
            $table->foreignId('whatsapp_message_id')->nullable()->constrained('whatsapp_messages')->nullOnDelete();
            $table->foreignId('campaign_message_id')->nullable()->constrained('campaign_messages')->nullOnDelete();
            $table->boolean('billable')->default(false);
            $table->string('category')->nullable();
            $table->string('pricing_model')->nullable();
            $table->integer('estimated_cost_minor')->default(0);
            $table->json('meta')->nullable();
            $table->timestamp('counted_at')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'meta_message_id']);
            $table->index(['account_id', 'billable']);
            $table->index(['account_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_billings');
    }
};
