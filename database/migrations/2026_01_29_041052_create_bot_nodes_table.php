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
        Schema::create('bot_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->foreignId('bot_flow_id')->constrained()->onDelete('cascade');
            $table->string('type'); // condition|action|delay|router
            $table->json('config'); // node config
            $table->integer('sort_order'); // sequential execution
            $table->timestamps();

            $table->index(['account_id', 'bot_flow_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_nodes');
    }
};
