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
        Schema::create('notification_outbox', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('notifiable');
            $table->string('channel', 50)->default('mail');
            $table->string('notification_class')->nullable();
            $table->string('template_key')->nullable();
            $table->string('recipient')->nullable();
            $table->string('subject')->nullable();
            $table->string('status', 20)->default('queued'); // queued|retrying|sent|failed
            $table->unsignedInteger('attempts')->default(0);
            $table->string('provider_code', 100)->nullable();
            $table->string('provider_message_id')->nullable();
            $table->json('provider_response')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['status', 'channel']);
            $table->index(['template_key', 'created_at']);
            $table->index(['recipient', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_outbox');
    }
};
