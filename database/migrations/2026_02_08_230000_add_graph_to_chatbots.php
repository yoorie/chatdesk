<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_nodes', function (Blueprint $table) {
            if (! Schema::hasColumn('bot_nodes', 'pos_x')) {
                $table->integer('pos_x')->nullable()->after('sort_order');
            }
            if (! Schema::hasColumn('bot_nodes', 'pos_y')) {
                $table->integer('pos_y')->nullable()->after('pos_x');
            }
        });

        Schema::create('bot_edges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->foreignId('bot_flow_id')->constrained('bot_flows')->onDelete('cascade');
            $table->foreignId('from_node_id')->constrained('bot_nodes')->onDelete('cascade');
            $table->foreignId('to_node_id')->constrained('bot_nodes')->onDelete('cascade');
            $table->string('label')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['account_id', 'bot_flow_id']);
            $table->index(['bot_flow_id', 'from_node_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_edges');

        Schema::table('bot_nodes', function (Blueprint $table) {
            if (Schema::hasColumn('bot_nodes', 'pos_y')) {
                $table->dropColumn('pos_y');
            }
            if (Schema::hasColumn('bot_nodes', 'pos_x')) {
                $table->dropColumn('pos_x');
            }
        });
    }
};
