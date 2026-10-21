<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_media_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('source')->default('upload');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['account_id', 'type']);
        });

        Schema::create('account_catalog_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('category')->nullable();
            $table->unsignedInteger('price')->default(0);
            $table->string('currency', 3)->default('INR');
            $table->integer('stock')->default(0);
            $table->string('image_url')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['account_id', 'status']);
        });

        Schema::create('account_ecommerce_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('order_number');
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->unsignedInteger('amount')->default(0);
            $table->string('currency', 3)->default('INR');
            $table->string('status')->default('pending');
            $table->string('source')->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['account_id', 'order_number']);
            $table->index(['account_id', 'status']);
        });

        Schema::create('account_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('CSAT');
            $table->string('trigger')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedInteger('responses_count')->default(0);
            $table->decimal('average_score', 4, 2)->nullable();
            $table->json('questions')->nullable();
            $table->timestamps();
            $table->index(['account_id', 'status']);
        });

        Schema::create('account_appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('contact_name');
            $table->string('contact_phone')->nullable();
            $table->timestamp('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->string('staff_name')->nullable();
            $table->string('status')->default('scheduled');
            $table->string('type')->nullable();
            $table->boolean('reminder_enabled')->default(true);
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();
            $table->index(['account_id', 'scheduled_at']);
            $table->index(['account_id', 'status']);
        });

        Schema::create('account_meta_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('city')->nullable();
            $table->string('stage')->default('new');
            $table->string('platform')->default('Facebook');
            $table->string('form_name')->nullable();
            $table->string('ad_name')->nullable();
            $table->unsignedInteger('cost_per_lead')->default(0);
            $table->unsignedSmallInteger('score')->default(0);
            $table->string('assignee_name')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->unique(['account_id', 'external_id']);
            $table->index(['account_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_meta_leads');
        Schema::dropIfExists('account_appointments');
        Schema::dropIfExists('account_surveys');
        Schema::dropIfExists('account_ecommerce_orders');
        Schema::dropIfExists('account_catalog_products');
        Schema::dropIfExists('account_media_assets');
    }
};
