<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\LaravelPasskeys\Models\Concerns\HasPasskeys;
use Spatie\LaravelPasskeys\Models\Concerns\InteractsWithPasskeys;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasPasskeys
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, InteractsWithPasskeys, Notifiable;

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return HasMany<Feed, $this>
     */
    public function feeds(): HasMany
    {
        return $this->hasMany(Feed::class);
    }

    /**
     * @return HasMany<ReadingListItem, $this>
     */
    public function readingList(): HasMany
    {
        return $this->hasMany(ReadingListItem::class);
    }

    /**
     * The archive — permanently kept posts and comments.
     *
     * @return HasMany<SavedItem, $this>
     */
    public function savedItems(): HasMany
    {
        return $this->hasMany(SavedItem::class);
    }

    /**
     * @return HasMany<Folder, $this>
     */
    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class);
    }

    /**
     * @return HasMany<Tag, $this>
     */
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    /**
     * Home-timeline sources ('reddit', 'hn', 'news') this user has switched
     * off. Null column = nothing disabled.
     *
     * @return array<int, string>
     */
    public function disabledSources(): array
    {
        return $this->disabled_sources ?? [];
    }

    /**
     * Read-tracking rows: what's been opened, and at what comment count.
     *
     * @return HasMany<SeenItem, $this>
     */
    public function seenItems(): HasMany
    {
        return $this->hasMany(SeenItem::class);
    }

    /**
     * Record that this user has opened an item. A null $comments (an inline
     * expando, an RSS article) marks it read WITHOUT touching the stored
     * comment count — the "+N new" baseline only moves when the user actually
     * sees the thread.
     */
    public function markSeen(string $key, ?int $comments = null): void
    {
        $this->seenItems()->updateOrCreate(
            ['seen_key' => $key],
            ['last_seen_at' => now()] + ($comments !== null ? ['comments_seen' => $comments] : []),
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'disabled_sources' => 'array',
        ];
    }
}
