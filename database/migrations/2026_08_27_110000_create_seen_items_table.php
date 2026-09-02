<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Read-tracking, RES-style, but on the server so every device sees the
     * same history. One row per (user, item): when it was last opened and how
     * many comments it had then — the delta against the live count is the
     * feed's "+N new" badge. seen_key is source-qualified ('reddit:abc12',
     * 'hn:41231', 'rss:bbcni:d41d8...') since ids only mean anything within
     * their source.
     */
    public function up(): void
    {
        Schema::create('seen_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('seen_key', 120);
            $table->unsignedInteger('comments_seen')->nullable();
            $table->timestamp('last_seen_at');

            $table->unique(['user_id', 'seen_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seen_items');
    }
};
