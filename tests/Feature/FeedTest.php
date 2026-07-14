<?php

namespace Tests\Feature;

use App\Models\Feed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create();
    }

    public function test_creating_a_feed_generates_a_unique_slug(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->post('/feeds', ['name' => 'My Tech'])
            ->assertRedirect();
        $this->actingAs($user)->post('/feeds', ['name' => 'My Tech'])
            ->assertRedirect();

        $slugs = $user->feeds()->pluck('slug')->all();
        $this->assertEqualsCanonicalizing(['my-tech', 'my-tech-2'], $slugs);
    }

    public function test_subreddits_can_be_added_to_and_removed_from_a_feed(): void
    {
        $user = $this->owner();
        $feed = $user->feeds()->create(['name' => 'Dev', 'slug' => 'dev']);

        $this->actingAs($user)->post("/feeds/{$feed->id}/subs", ['subreddit' => 'PHP'])
            ->assertRedirect();

        // Stored lowercased, and duplicates are ignored.
        $this->actingAs($user)->post("/feeds/{$feed->id}/subs", ['subreddit' => 'php']);
        $this->assertSame(['php'], $feed->fresh()->subredditNames());

        $this->actingAs($user)->delete("/feeds/{$feed->id}/subs/php")->assertRedirect();
        $this->assertSame([], $feed->fresh()->subredditNames());
    }

    public function test_a_user_cannot_touch_another_users_feed(): void
    {
        $mine = $this->owner();
        $theirs = User::factory()->create();
        $feed = $theirs->feeds()->create(['name' => 'Secret', 'slug' => 'secret']);

        $this->actingAs($mine)->post("/feeds/{$feed->id}/subs", ['subreddit' => 'php'])
            ->assertNotFound();
        $this->actingAs($mine)->delete("/feeds/{$feed->id}")
            ->assertNotFound();
    }

    public function test_feed_show_is_scoped_to_the_owner(): void
    {
        $mine = $this->owner();
        $theirs = User::factory()->create();
        $theirs->feeds()->create(['name' => 'Theirs', 'slug' => 'theirs']);

        // Same slug, different owner: must not resolve for me.
        $this->actingAs($mine)->get('/f/theirs')->assertNotFound();
    }
}
