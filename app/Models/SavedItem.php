<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

/**
 * An archived post or comment.
 *
 * body_html is a COPY taken when it was saved, not a pointer — if the author
 * deletes it or the sub goes private, what is here still reads. Nothing in the
 * archive ever calls Reddit.
 */
class SavedItem extends Model
{
    use HasFactory;

    public const KIND_POST = 'post';

    public const KIND_COMMENT = 'comment';

    protected $fillable = [
        'user_id', 'folder_id', 'source', 'kind', 'reddit_id', 'subreddit', 'author',
        'post_id', 'post_title', 'slug', 'title', 'body_html', 'note',
        'permalink', 'thumbnail', 'score', 'num_comments', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
            'score' => 'integer',
            'num_comments' => 'integer',
        ];
    }

    /**
     * Keep the FTS index in step. Done here rather than in SQL triggers
     * because SQLite refuses to touch a virtual table from a trigger unless
     * the connection sets trusted_schema=ON.
     */
    protected static function booted(): void
    {
        static::saved(fn (self $item) => $item->syncSearchIndex());
        static::deleted(fn (self $item) => $item->removeFromSearchIndex());
    }

    public function syncSearchIndex(): void
    {
        $this->removeFromSearchIndex();

        DB::statement(
            'INSERT INTO saved_items_fts(rowid, title, body, note, author, subreddit) VALUES (?, ?, ?, ?, ?, ?)',
            [
                $this->id,
                (string) ($this->title ?: $this->post_title),
                // Index the readable text, not the markup, so a search for
                // "class" doesn't match every <div class="...">.
                trim(html_entity_decode(strip_tags((string) $this->body_html))),
                (string) $this->note,
                (string) $this->author,
                (string) $this->subreddit,
            ],
        );
    }

    public function removeFromSearchIndex(): void
    {
        DB::statement('DELETE FROM saved_items_fts WHERE rowid = ?', [$this->id]);
    }

    /**
     * Full-text search, newest first among equally relevant hits.
     *
     * FTS5 treats a bare string as a query expression, so anything the user
     * types must be quoted — otherwise `AND`, `*`, `"` or a stray `-` either
     * changes the meaning or throws a syntax error mid-typing.
     */
    public function scopeSearch(Builder $query, string $terms): Builder
    {
        $terms = trim($terms);

        if ($terms === '') {
            return $query;
        }

        $expression = collect(preg_split('/\s+/', $terms))
            ->reject(fn ($word) => $word === '')
            ->map(fn ($word) => '"'.str_replace('"', '""', $word).'"*')
            ->implode(' ');

        return $query->whereIn('id', function ($sub) use ($expression) {
            $sub->select('rowid')->from('saved_items_fts')->whereRaw('saved_items_fts MATCH ?', [$expression]);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function isComment(): bool
    {
        return $this->kind === self::KIND_COMMENT;
    }

    /** Where this lives in the app — for a comment, the post it came from. */
    public function url(): ?string
    {
        if ($this->source === 'hn') {
            return route('hn.item', $this->reddit_id);
        }

        if ($this->source !== null && $this->source !== 'reddit') {
            // RSS: the permalink IS the article.
            return $this->permalink;
        }

        if (! $this->post_id && ! $this->isComment()) {
            return route('posts.show', [
                'subreddit' => $this->subreddit,
                'id' => $this->reddit_id,
                'slug' => $this->slug ?: '-',
            ]);
        }

        if (! $this->post_id) {
            return null;
        }

        return route('posts.show', array_filter([
            'subreddit' => $this->subreddit,
            'id' => $this->post_id,
            'slug' => $this->slug ?: '-',
            'comment' => $this->isComment() ? $this->reddit_id : null,
        ]));
    }

    protected function setSubredditAttribute(string $value): void
    {
        $this->attributes['subreddit'] = strtolower(trim($value));
    }
}
