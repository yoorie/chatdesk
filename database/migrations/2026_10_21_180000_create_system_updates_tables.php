<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_updates')) {
            Schema::create('system_updates', function (Blueprint $table) {
                $table->id();
                $table->string('from_version', 32);
                $table->string('to_version', 32);
                $table->string('kind', 32)->default('update');
                $table->string('package_sha256', 64)->nullable();
                $table->string('package_path')->nullable();
                $table->string('status', 32)->index();
                $table->json('state')->nullable();
                $table->string('backup_path')->nullable();
                $table->string('db_backup_path')->nullable();
                $table->unsignedInteger('migration_batch')->nullable();
                $table->longText('log')->nullable();
                $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('system_update_steps')) {
            Schema::create('system_update_steps', function (Blueprint $table) {
                $table->id();
                $table->foreignId('update_id')->constrained('system_updates')->cascadeOnDelete();
                $table->string('step');
                $table->string('status', 32)->default('pending');
                $table->text('output')->nullable();
                $table->timestamp('finished_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_update_steps');
        Schema::dropIfExists('system_updates');
    }
};
