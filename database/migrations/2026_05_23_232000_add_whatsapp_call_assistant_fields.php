<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_voice_calls')) {
            Schema::table('ai_voice_calls', function (Blueprint $table) {
                if (! Schema::hasColumn('ai_voice_calls', 'whatsapp_connection_id')) {
                    $table->foreignId('whatsapp_connection_id')->nullable()->after('ai_agent_id')->constrained('whatsapp_connections')->nullOnDelete();
                }
                if (! Schema::hasColumn('ai_voice_calls', 'route_mode')) {
                    $table->string('route_mode', 40)->nullable()->after('status');
                }
                if (! Schema::hasColumn('ai_voice_calls', 'routed_to')) {
                    $table->string('routed_to')->nullable()->after('route_mode');
                }
                if (! Schema::hasColumn('ai_voice_calls', 'consent_status')) {
                    $table->string('consent_status', 40)->nullable()->after('routed_to');
                }
            });
        }

        if (! Schema::hasTable('ai_voice_consents')) {
            Schema::create('ai_voice_consents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('account_id')->constrained()->cascadeOnDelete();
                $table->foreignId('whatsapp_connection_id')->nullable()->constrained('whatsapp_connections')->nullOnDelete();
                $table->string('phone_number', 60)->index();
                $table->string('contact_name')->nullable();
                $table->string('status', 40)->default('granted')->index();
                $table->string('source', 80)->default('manual');
                $table->timestamp('consented_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['account_id', 'phone_number', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_voice_consents');

        if (Schema::hasTable('ai_voice_calls')) {
            Schema::table('ai_voice_calls', function (Blueprint $table) {
                foreach (['whatsapp_connection_id', 'route_mode', 'routed_to', 'consent_status'] as $column) {
                    if (Schema::hasColumn('ai_voice_calls', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
