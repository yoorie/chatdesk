<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'country_code')) {
                $table->string('country_code', 8)->default('+91')->after('phone');
            }

            if (! Schema::hasColumn('users', 'job_title')) {
                $table->string('job_title', 120)->nullable()->after('country_code');
            }

            if (! Schema::hasColumn('users', 'locale')) {
                $table->string('locale', 16)->default('en-IN')->after('job_title');
            }

            if (! Schema::hasColumn('users', 'timezone')) {
                $table->string('timezone', 80)->default('Asia/Kolkata')->after('locale');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('users', 'timezone') ? 'timezone' : null,
                Schema::hasColumn('users', 'locale') ? 'locale' : null,
                Schema::hasColumn('users', 'job_title') ? 'job_title' : null,
                Schema::hasColumn('users', 'country_code') ? 'country_code' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
