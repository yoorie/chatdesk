<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_connections', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_connections', 'business_username')) {
                $table->string('business_username', 128)->nullable()->after('business_category');
            }
        });

        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_contacts', 'business_scoped_user_id')) {
                $table->string('business_scoped_user_id', 140)->nullable()->after('wa_id');
            }

            if (! Schema::hasColumn('whatsapp_contacts', 'parent_business_scoped_user_id')) {
                $table->string('parent_business_scoped_user_id', 140)->nullable()->after('business_scoped_user_id');
            }

            if (! Schema::hasColumn('whatsapp_contacts', 'whatsapp_username')) {
                $table->string('whatsapp_username', 128)->nullable()->after('parent_business_scoped_user_id');
            }
        });

        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            if (
                Schema::hasColumn('whatsapp_contacts', 'business_scoped_user_id')
                && ! Schema::hasIndex('whatsapp_contacts', 'wa_contacts_account_bsuid_idx')
            ) {
                $table->index(['account_id', 'business_scoped_user_id'], 'wa_contacts_account_bsuid_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_contacts', 'business_scoped_user_id')) {
                $table->dropIndex('wa_contacts_account_bsuid_idx');
            }

            foreach (['whatsapp_username', 'parent_business_scoped_user_id', 'business_scoped_user_id'] as $column) {
                if (Schema::hasColumn('whatsapp_contacts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('whatsapp_connections', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_connections', 'business_username')) {
                $table->dropColumn('business_username');
            }
        });
    }
};
