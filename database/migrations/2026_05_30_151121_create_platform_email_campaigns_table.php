<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('platform_email_campaign_recipients');
        Schema::dropIfExists('platform_email_campaigns');

        Schema::create('platform_email_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('subject');
            $table->string('audience')->default('workspace_owners');
            $table->text('body');
            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->string('offer_code')->nullable();
            $table->string('status')->default('draft')->index();
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_email_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('platform_email_campaign_id');
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('status')->default('pending')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->unsignedBigInteger('notification_outbox_id')->nullable();
            $table->timestamps();

            $table->unique(['platform_email_campaign_id', 'email'], 'platform_campaign_recipient_unique');
            $table->foreign('platform_email_campaign_id', 'pec_recipients_campaign_fk')->references('id')->on('platform_email_campaigns')->cascadeOnDelete();
            $table->foreign('account_id', 'pec_recipients_account_fk')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('user_id', 'pec_recipients_user_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('notification_outbox_id', 'pec_recipients_outbox_fk')->references('id')->on('notification_outbox')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_email_campaign_recipients');
        Schema::dropIfExists('platform_email_campaigns');
    }
};
