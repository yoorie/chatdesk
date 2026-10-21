<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('floater_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->foreignId('whatsapp_connection_id')->nullable()->constrained('whatsapp_connections')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->uuid('public_id')->unique();
            $table->boolean('is_active')->default(true);
            $table->json('theme')->nullable();
            $table->string('position')->default('bottom-right');
            $table->json('show_on')->nullable();
            $table->string('welcome_message')->nullable();
            $table->string('whatsapp_phone')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'slug']);
            $table->index(['account_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('floater_widgets');
    }
};
