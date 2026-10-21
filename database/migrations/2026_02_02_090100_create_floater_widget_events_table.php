<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('floater_widget_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('floater_widget_id')->constrained('floater_widgets')->onDelete('cascade');
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->string('event_type', 32);
            $table->string('path')->nullable();
            $table->string('referrer')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['floater_widget_id', 'event_type']);
            $table->index(['account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('floater_widget_events');
    }
};
