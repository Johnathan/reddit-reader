<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'subreddit',
    ];

    /**
     * The user that owns this subscription.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Normalize the subreddit name to lowercase whenever it's set.
     */
    protected function setSubredditAttribute(string $value): void
    {
        $this->attributes['subreddit'] = strtolower(trim($value));
    }
}
