<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Custom feeds are local multireddits: a named group of subreddits the
     * user views combined via /r/a+b+c. No Reddit account involved.
     */
    public function up(): void
    {
        Schema::create('feeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['user_id', 'slug']);
        });

        Schema::create('feed_subreddits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_id')->constrained()->cascadeOnDelete();
            $table->string('subreddit');
            $table->timestamps();

            $table->unique(['feed_id', 'subreddit']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_subreddits');
        Schema::dropIfExists('feeds');
    }
};
