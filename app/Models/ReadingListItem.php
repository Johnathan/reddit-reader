<?php

namespace App\Models;

use App\Services\ImageProxy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingListItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'source',
        'post_id',
        'url',
        'subreddit',
        'title',
        'slug',
        'thumbnail',
        'score',
        'num_comments',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'score' => 'integer',
            'num_comments' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The in-app page for this item, whatever its source. */
    public function url(): string
    {
        return match ($this->source) {
            'hn' => route('hn.item', $this->post_id),
            'reddit', null => route('posts.show', [
                'subreddit' => $this->subreddit,
                'id' => $this->post_id,
                'slug' => $this->slug ?: '-',
            ]),
            // RSS sources: subreddit holds the source key.
            default => route('article.show', ['source' => $this->source, 'id' => $this->post_id]),
        };
    }

    /**
     * What a row shows as this item's origin: 'r/php', 'Hacker News',
     * 'BBC NI'. For non-Reddit sources `subreddit` holds the source key, so
     * rendering r/{subreddit} everywhere mislabels them.
     */
    public function sourceLabel(): string
    {
        return match ($this->source) {
            'hn' => 'Hacker News',
            'reddit', null => 'r/'.$this->subreddit,
            default => \App\Services\RssSource::find($this->source)['name'] ?? $this->subreddit,
        };
    }

    /** Thumbnails are snapshots, so imgur ones still need the proxy. */
    public function thumbnailUrl(): ?string
    {
        return ImageProxy::wrap($this->thumbnail);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    protected function setSubredditAttribute(string $value): void
    {
        $this->attributes['subreddit'] = strtolower(trim($value));
    }
}
