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
        Schema::create('system_backups', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40)->default('database');
            $table->string('status', 20)->default('queued'); // queued|running|completed|failed|skipped
            $table->string('disk', 40)->default('local');
            $table->string('path')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->string('checksum', 128)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('restore_drill_at')->nullable();
            $table->string('restore_drill_status', 20)->nullable(); // passed|failed
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['type', 'completed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_backups');
    }
};
