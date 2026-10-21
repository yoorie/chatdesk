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
        Schema::create('bot_action_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->foreignId('bot_execution_id')->constrained()->onDelete('cascade');
            $table->foreignId('node_id')->constrained('bot_nodes')->onDelete('cascade');
            $table->timestamp('run_at');
            $table->string('status')->default('queued'); // queued|running|done|failed
            $table->integer('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'run_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_action_jobs');
    }
};
