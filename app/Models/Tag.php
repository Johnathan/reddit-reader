<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'slug'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(SavedItem::class);
    }

    /** Find or create by name, matched case-insensitively via the slug. */
    public static function findOrCreateFor(int $userId, string $name): ?self
    {
        $name = trim($name);
        $slug = Str::slug($name);

        if ($name === '' || $slug === '') {
            return null;
        }

        return static::firstOrCreate(
            ['user_id' => $userId, 'slug' => $slug],
            ['name' => $name],
        );
    }
}
