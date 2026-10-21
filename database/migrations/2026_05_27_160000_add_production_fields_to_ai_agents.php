<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_agents', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_agents', 'goal')) {
                $table->text('goal')->nullable()->after('instructions');
            }
            if (! Schema::hasColumn('ai_agents', 'allowed_actions')) {
                $table->json('allowed_actions')->nullable()->after('knowledge_sources');
            }
            if (! Schema::hasColumn('ai_agents', 'max_reply_chars')) {
                $table->unsignedSmallInteger('max_reply_chars')->default(1200)->after('max_auto_replies_per_conversation');
            }
            if (! Schema::hasColumn('ai_agents', 'qualification_fields')) {
                $table->json('qualification_fields')->nullable()->after('allowed_actions');
            }
            if (! Schema::hasColumn('ai_agents', 'handoff_rules')) {
                $table->json('handoff_rules')->nullable()->after('escalation_rules');
            }
            if (! Schema::hasColumn('ai_agents', 'fallback_reply')) {
                $table->text('fallback_reply')->nullable()->after('handoff_rules');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_agents', function (Blueprint $table) {
            foreach (['goal', 'allowed_actions', 'max_reply_chars', 'qualification_fields', 'handoff_rules', 'fallback_reply'] as $column) {
                if (Schema::hasColumn('ai_agents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
