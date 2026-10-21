<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_survey_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('respondent_name')->nullable();
            $table->string('respondent_phone')->nullable();
            $table->string('respondent_email')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->json('answers')->nullable();
            $table->string('ip_hash', 80)->nullable();
            $table->timestamps();
            $table->index(['account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_survey_responses');
    }
};
