<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('title', 190);
            $table->longText('body')->nullable();
            $table->string('meta_title', 190)->nullable();
            $table->string('meta_description', 300)->nullable();
            $table->string('og_image', 500)->nullable();
            $table->string('status', 16)->default('draft')->index();
            $table->boolean('show_in_header')->default(false);
            $table->boolean('show_in_footer')->default(false);
            $table->unsignedInteger('menu_order')->default(0);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_pages');
    }
};
