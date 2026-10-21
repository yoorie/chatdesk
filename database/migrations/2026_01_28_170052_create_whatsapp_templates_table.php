<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->foreignId('whatsapp_connection_id')->constrained()->onDelete('cascade');
            $table->string('meta_template_id')->nullable(); // ID from Meta if available
            $table->string('name'); // unique per connection+language
            $table->string('language');
            $table->string('category'); // MARKETING, UTILITY, AUTHENTICATION
            $table->string('status'); // approved/pending/rejected/paused/disabled/unknown
            $table->string('quality_score')->nullable();
            $table->text('body_text')->nullable(); // extracted for preview/search
            $table->string('header_type')->nullable(); // TEXT/IMAGE/VIDEO/DOCUMENT/LOCATION
            $table->text('header_text')->nullable();
            $table->text('footer_text')->nullable();
            $table->json('buttons')->nullable(); // normalized buttons for UI
            $table->json('components'); // raw template components from Meta
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_meta_error')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->unique(
                ['account_id', 'whatsapp_connection_id', 'name', 'language'],
                'wa_tpl_ws_conn_name_lang_uq'
            );
            $table->index(['account_id', 'status']);
            $table->index(['account_id', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
