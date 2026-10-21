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
        Schema::create('bot_flows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->foreignId('bot_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->json('trigger'); // trigger config
            $table->boolean('enabled')->default(true);
            $table->integer('priority')->default(100); // lower runs earlier
            $table->timestamps();

            $table->index(['account_id', 'bot_id', 'enabled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_flows');
    }
};
