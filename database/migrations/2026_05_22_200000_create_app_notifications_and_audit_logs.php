<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scope', 20)->default('workspace');
            $table->string('type', 80);
            $table->string('severity', 20)->default('info');
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('action_url')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['scope', 'severity', 'created_at']);
            $table->index(['account_id', 'read_at', 'created_at']);
            $table->index(['type', 'created_at']);
        });

        Schema::create('destructive_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->string('auditable_type')->nullable();
            $table->string('auditable_id')->nullable();
            $table->string('description');
            $table->json('data')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('destructive_audit_logs');
        Schema::dropIfExists('app_notifications');
    }
};
