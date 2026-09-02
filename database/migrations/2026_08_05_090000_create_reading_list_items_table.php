<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Save-for-later. The row keeps a snapshot of the post (title, thumbnail,
     * counts) taken at the moment it was saved, so the reading list renders
     * instantly without a Reddit round trip per item — and still shows
     * something sensible if the post is later removed.
     */
    public function up(): void
    {
        Schema::create('reading_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('post_id');              // Reddit's t3 id, e.g. 1v8xm6t
            $table->string('subreddit');
            $table->string('title');
            $table->string('slug')->nullable();
            $table->string('thumbnail')->nullable();
            $table->unsignedInteger('score')->default(0);
            $table->unsignedInteger('num_comments')->default(0);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'post_id']);
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_list_items');
    }
};
