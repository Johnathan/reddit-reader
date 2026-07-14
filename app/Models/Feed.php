<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Feed extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'slug'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subreddits(): HasMany
    {
        return $this->hasMany(FeedSubreddit::class);
    }

    /**
     * The subreddit names in this feed, lowercased.
     *
     * @return array<int, string>
     */
    public function subredditNames(): array
    {
        return $this->subreddits->pluck('subreddit')->all();
    }

    /**
     * Derive a URL slug from a feed name, keeping it unique for the user.
     */
    public static function uniqueSlugFor(int $userId, string $name): string
    {
        $base = Str::slug($name) ?: 'feed';
        $slug = $base;
        $i = 2;

        while (static::query()->where('user_id', $userId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
