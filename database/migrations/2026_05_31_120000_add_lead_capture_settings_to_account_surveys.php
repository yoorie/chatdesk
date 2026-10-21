<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_surveys', function (Blueprint $table) {
            if (! Schema::hasColumn('account_surveys', 'auto_create_contact')) {
                $table->boolean('auto_create_contact')->default(true)->after('questions');
            }
            if (! Schema::hasColumn('account_surveys', 'contact_name_field')) {
                $table->string('contact_name_field', 80)->nullable()->after('auto_create_contact');
            }
            if (! Schema::hasColumn('account_surveys', 'contact_phone_field')) {
                $table->string('contact_phone_field', 80)->nullable()->after('contact_name_field');
            }
            if (! Schema::hasColumn('account_surveys', 'contact_email_field')) {
                $table->string('contact_email_field', 80)->nullable()->after('contact_phone_field');
            }
            if (! Schema::hasColumn('account_surveys', 'auto_tag_names')) {
                $table->json('auto_tag_names')->nullable()->after('contact_email_field');
            }
            if (! Schema::hasColumn('account_surveys', 'success_message')) {
                $table->text('success_message')->nullable()->after('auto_tag_names');
            }
        });
    }

    public function down(): void
    {
        Schema::table('account_surveys', function (Blueprint $table) {
            foreach (['success_message', 'auto_tag_names', 'contact_email_field', 'contact_phone_field', 'contact_name_field', 'auto_create_contact'] as $column) {
                if (Schema::hasColumn('account_surveys', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
