<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A post/thread/article the user has opened, with the comment count they saw.
 * Powers the feed's read-dimming and "+N new" comment badges across devices.
 */
class SeenItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'seen_key',
        'comments_seen',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'comments_seen' => 'integer',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The source-qualified identity of a feed item, matching what the feed
     * cards render as data-seen. IDs are only unique within a source (HN is
     * numeric, Reddit base36, RSS a per-feed hash), so the key carries it.
     *
     * @param  array<string, mixed>  $item  A normalized feed item payload.
     */
    public static function keyFor(array $item): string
    {
        return match ($item['_source'] ?? 'reddit') {
            'hn' => 'hn:'.$item['id'],
            'rss' => 'rss:'.$item['source_key'].':'.$item['id'],
            default => 'reddit:'.$item['id'],
        };
    }
}
