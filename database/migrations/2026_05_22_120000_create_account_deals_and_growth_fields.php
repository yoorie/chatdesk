<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_contact_id')->nullable()->constrained('whatsapp_contacts')->nullOnDelete();
            $table->foreignId('whatsapp_conversation_id')->nullable()->constrained('whatsapp_conversations')->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('stage')->default('new');
            $table->unsignedInteger('value')->default(0);
            $table->string('currency', 3)->default('INR');
            $table->string('source')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->string('lost_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['account_id', 'stage']);
            $table->index(['account_id', 'owner_id']);
        });

        Schema::table('account_meta_leads', function (Blueprint $table) {
            if (! Schema::hasColumn('account_meta_leads', 'source_type')) {
                $table->string('source_type')->default('meta_lead')->after('stage');
            }
            if (! Schema::hasColumn('account_meta_leads', 'campaign_name')) {
                $table->string('campaign_name')->nullable()->after('ad_name');
            }
            if (! Schema::hasColumn('account_meta_leads', 'auto_tags')) {
                $table->json('auto_tags')->nullable()->after('assignee_name');
            }
        });

        Schema::table('account_ecommerce_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('account_ecommerce_orders', 'payment_url')) {
                $table->string('payment_url', 800)->nullable()->after('source');
            }
            if (! Schema::hasColumn('account_ecommerce_orders', 'recovery_status')) {
                $table->string('recovery_status')->default('none')->after('payment_url');
            }
            if (! Schema::hasColumn('account_ecommerce_orders', 'recovered_at')) {
                $table->timestamp('recovered_at')->nullable()->after('recovery_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('account_ecommerce_orders', function (Blueprint $table) {
            foreach (['recovered_at', 'recovery_status', 'payment_url'] as $column) {
                if (Schema::hasColumn('account_ecommerce_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('account_meta_leads', function (Blueprint $table) {
            foreach (['auto_tags', 'campaign_name', 'source_type'] as $column) {
                if (Schema::hasColumn('account_meta_leads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('account_deals');
    }
};
