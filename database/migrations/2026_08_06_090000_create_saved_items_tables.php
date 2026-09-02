<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The archive: posts and comments kept for reference, organised into
     * folders and tags, and searchable.
     *
     * The point of difference from the reading list is that this ARCHIVES the
     * content — body_html is copied in at save time, so a deleted post or a
     * nuked comment is still readable here years later. Nothing re-fetches.
     */
    public function up(): void
    {
        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['user_id', 'slug']);
        });

        Schema::create('saved_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained()->nullOnDelete();

            $table->string('kind');                 // post | comment
            $table->string('reddit_id');            // t3 or t1 id
            $table->string('subreddit');
            $table->string('author')->nullable();

            // For a comment this is the parent post, so the archive can still
            // say what the discussion was about.
            $table->string('post_id')->nullable();
            $table->string('post_title')->nullable();
            $table->string('slug')->nullable();

            $table->string('title')->nullable();    // posts only
            $table->longText('body_html')->nullable();   // THE archive copy
            $table->text('note')->nullable();       // the user's own annotation
            $table->string('permalink')->nullable();
            $table->string('thumbnail')->nullable();

            $table->integer('score')->default(0);
            $table->unsignedInteger('num_comments')->default(0);
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'reddit_id']);
            $table->index(['user_id', 'kind']);
            $table->index(['user_id', 'folder_id']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['user_id', 'slug']);
        });

        Schema::create('saved_item_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saved_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();

            $table->unique(['saved_item_id', 'tag_id']);
        });

        // Full-text index. `content=''` makes it contentless — FTS stores only
        // the index, not a second copy of every body. It is kept in step from
        // PHP (see App\Models\SavedItem) rather than SQL triggers: SQLite
        // refuses to touch a virtual table from a trigger unless the
        // connection has trusted_schema=ON, which is not something to rely on.
        //
        // contentless_delete=1 (SQLite 3.43+) is what makes a plain DELETE
        // legal here; without it, removing a row means re-supplying every
        // column's ORIGINAL text to the special 'delete' command.
        DB::statement("
            CREATE VIRTUAL TABLE saved_items_fts USING fts5(
                title, body, note, author, subreddit,
                content='',
                contentless_delete=1,
                tokenize='porter unicode61'
            )
        ");

    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS saved_items_fts');

        Schema::dropIfExists('saved_item_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('saved_items');
        Schema::dropIfExists('folders');
    }
};
