<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * ChatDesk open-source edition: drop tables that only served the removed SaaS
 * features (billing, wallets, add-ons, platform email campaigns, CMS, support
 * desk, updater, mobile app tokens, widgets, surveys and integration sync logs).
 * Nothing in this edition reads or writes them.
 */
return new class extends Migration
{
    private array $tables = [
        'account_survey_responses',
        'account_surveys',
        'account_integration_sync_logs',
        'account_ecommerce_orders',
        'account_addons',
        'plan_addons',
        'wallet_transactions',
        'wallet_topup_orders',
        'account_wallets',
        'payment_orders',
        'billing_events',
        'billing_discounts',
        'billing_webhook_receipts',
        'platform_email_campaign_recipients',
        'platform_email_campaigns',
        'contact_requests',
        'mobile_access_tokens',
        'cms_pages',
        'support_message_attachments',
        'support_messages',
        'support_threads',
        'system_update_steps',
        'system_updates',
        'floater_widget_events',
        'floater_widgets',
    ];

    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach ($this->tables as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Intentionally irreversible: these features are not part of the open-source edition.
    }
};
