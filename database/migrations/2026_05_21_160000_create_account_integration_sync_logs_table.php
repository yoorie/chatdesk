<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_integration_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_integration_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider');
            $table->string('status')->default('running');
            $table->string('trigger')->default('manual');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->json('summary')->nullable();
            $table->text('error_message')->nullable();
            $table->string('source_ip')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'provider']);
            $table->index(['account_id', 'status']);
            $table->index(['provider', 'trigger']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_integration_sync_logs');
    }
};
