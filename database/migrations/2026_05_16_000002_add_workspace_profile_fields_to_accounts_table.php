<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('accounts', 'workspace_type')) {
                $table->string('workspace_type', 40)->default('business')->after('slug');
                $table->index('workspace_type');
            }

            if (! Schema::hasColumn('accounts', 'industry')) {
                $table->string('industry', 120)->nullable()->after('workspace_type');
            }

            if (! Schema::hasColumn('accounts', 'timezone')) {
                $table->string('timezone', 80)->default('UTC')->after('industry');
            }
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            if (Schema::hasColumn('accounts', 'workspace_type')) {
                $table->dropIndex(['workspace_type']);
            }

            $columns = array_values(array_filter([
                Schema::hasColumn('accounts', 'timezone') ? 'timezone' : null,
                Schema::hasColumn('accounts', 'industry') ? 'industry' : null,
                Schema::hasColumn('accounts', 'workspace_type') ? 'workspace_type' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
