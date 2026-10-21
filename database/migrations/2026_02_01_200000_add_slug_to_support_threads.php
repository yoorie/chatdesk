<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_threads', function (Blueprint $table) {
            if (! Schema::hasColumn('support_threads', 'slug')) {
                $table->string('slug')->nullable()->unique();
            }
        });

        $threads = DB::table('support_threads')->select('id', 'subject', 'slug')->get();
        foreach ($threads as $thread) {
            if (! empty($thread->slug)) {
                continue;
            }
            $base = $thread->subject ? Str::slug($thread->subject) : 'support-thread';
            if ($base === '') {
                $base = 'support-thread';
            }
            $slug = $base.'-'.$thread->id;
            DB::table('support_threads')->where('id', $thread->id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('support_threads', function (Blueprint $table) {
            if (Schema::hasColumn('support_threads', 'slug')) {
                $table->dropUnique(['slug']);
                $table->dropColumn('slug');
            }
        });
    }
};
