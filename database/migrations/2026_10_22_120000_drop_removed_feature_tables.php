<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ChatDesk open-source edition: drop tables of features that are no longer in
 * the codebase (plans and subscriptions, AI agents, WhatsApp calling, CRM deals
 * and appointments, integrations, Meta leads, media library, custom fields), and the AI and payment-link usage counters.
 */
return new class extends Migration
{
    private array $tables = [
        'subscriptions',
        'ai_agent_runs',
        'ai_suggestion_feedback',
        'ai_usage_logs',
        'ai_voice_consents',
        'ai_voice_calls',
        'whatsapp_call_voice_sessions',
        'ai_agents',
        'account_appointments',
        'account_deals',
        'account_integrations',
        'account_media_assets',
        'account_meta_leads',
        'contact_custom_fields',
        'plans',
    ];

    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach ($this->tables as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();

        $columns = array_values(array_filter(
            ['ai_credits_used', 'ai_requests', 'ai_estimated_tokens', 'ai_estimated_cost_minor', 'razorpay_payment_links_created', 'razorpay_payment_links_paid'],
            fn (string $column) => Schema::hasColumn('account_usage', $column)
        ));
        if ($columns !== []) {
            Schema::table('account_usage', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: these features are not part of the open-source edition.
    }
};
