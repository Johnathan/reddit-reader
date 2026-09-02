<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The feed is no longer only Reddit: reading-list and archive rows need to
     * say which source their id belongs to, and RSS items (no stable public
     * id) carry their article URL.
     */
    public function up(): void
    {
        Schema::table('reading_list_items', function (Blueprint $table) {
            $table->string('source')->default('reddit');
            $table->string('url', 2000)->nullable();

            // Ids are only unique within a source now.
            $table->dropUnique(['user_id', 'post_id']);
            $table->unique(['user_id', 'source', 'post_id']);
        });

        Schema::table('saved_items', function (Blueprint $table) {
            $table->string('source')->default('reddit');

            $table->dropUnique(['user_id', 'reddit_id']);
            $table->unique(['user_id', 'source', 'reddit_id']);
        });
    }

    public function down(): void
    {
        Schema::table('saved_items', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'source', 'reddit_id']);
            $table->unique(['user_id', 'reddit_id']);
            $table->dropColumn('source');
        });

        Schema::table('reading_list_items', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'source', 'post_id']);
            $table->unique(['user_id', 'post_id']);
            $table->dropColumn(['source', 'url']);
        });
    }
};
