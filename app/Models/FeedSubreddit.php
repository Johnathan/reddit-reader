<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedSubreddit extends Model
{
    use HasFactory;

    protected $fillable = ['feed_id', 'subreddit'];

    public function feed(): BelongsTo
    {
        return $this->belongsTo(Feed::class);
    }

    protected function setSubredditAttribute(string $value): void
    {
        $this->attributes['subreddit'] = strtolower(trim($value));
    }
}
