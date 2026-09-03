<?php

namespace Tests\Feature;

use App\Models\ReadingListItem;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Reddit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingListTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $o = []): array
    {
        return array_merge([
            'post_id' => 'abc123',
            'subreddit' => 'php',
            'title' => 'A post worth reading later',
            'slug' => 'a-post-worth-reading-later',
            'score' => 42,
            'num_comments' => 7,
        ], $o);
    }

    private function item(User $user, array $o = []): ReadingListItem
    {
        return $user->readingList()->create($this->payload($o));
    }

    public function test_a_post_can_be_saved_and_the_response_carries_the_new_counts(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user)->postJson('/reading-list', $this->payload());

        $res->assertOk()
            ->assertJson(['saved' => true, 'count' => 1, 'unread' => 1])
            ->assertJsonPath('item.title', 'A post worth reading later');

        $this->assertDatabaseHas('reading_list_items', ['user_id' => $user->id, 'post_id' => 'abc123']);
    }

    public function test_saving_the_same_post_twice_does_not_duplicate_it(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/reading-list', $this->payload());
        $this->actingAs($user)->postJson('/reading-list', $this->payload(['title' => 'Retitled']))->assertOk();

        $this->assertSame(1, $user->readingList()->count());
        $this->assertSame('Retitled', $user->readingList()->first()->title);
    }

    public function test_it_rejects_a_junk_snapshot(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/reading-list', $this->payload(['post_id' => '../../etc/passwd']))
            ->assertStatus(422);

        $this->actingAs($user)
            ->postJson('/reading-list', $this->payload(['thumbnail' => 'javascript:alert(1)']))
            ->assertStatus(422);
    }

    public function test_a_post_can_be_removed(): void
    {
        $user = User::factory()->create();
        $this->item($user);

        $this->actingAs($user)->deleteJson('/reading-list/abc123')
            ->assertOk()
            ->assertJson(['saved' => false, 'count' => 0]);

        $this->assertSame(0, $user->readingList()->count());
    }

    public function test_marking_read_moves_it_out_of_the_unread_count(): void
    {
        $user = User::factory()->create();
        $this->item($user);

        $this->actingAs($user)->postJson('/reading-list/abc123/read')
            ->assertOk()
            ->assertJson(['read' => true, 'count' => 1, 'unread' => 0]);

        // ...and back again.
        $this->actingAs($user)->postJson('/reading-list/abc123/read')
            ->assertOk()
            ->assertJson(['read' => false, 'unread' => 1]);
    }

    public function test_clear_read_only_keeps_the_unread_ones(): void
    {
        $user = User::factory()->create();
        $this->item($user, ['post_id' => 'read1'])->update(['read_at' => now()]);
        $this->item($user, ['post_id' => 'unread1']);

        $this->actingAs($user)->deleteJson('/reading-list', ['read_only' => true])->assertOk();

        $this->assertSame(['unread1'], $user->readingList()->pluck('post_id')->all());
    }

    public function test_one_users_list_is_invisible_to_another(): void
    {
        $mine = User::factory()->create();
        $theirs = User::factory()->create();
        $this->item($theirs, ['post_id' => 'secret']);

        // Not listed...
        $this->actingAs($mine)->get('/reading-list')->assertOk()->assertDontSee('secret');

        // ...and not deletable.
        $this->actingAs($mine)->deleteJson('/reading-list/secret')->assertOk();
        $this->assertDatabaseHas('reading_list_items', ['user_id' => $theirs->id, 'post_id' => 'secret']);

        // ...and not markable.
        $this->actingAs($mine)->postJson('/reading-list/secret/read')->assertNotFound();
    }

    public function test_the_list_requires_a_login(): void
    {
        $this->get('/reading-list')->assertRedirect();

        $this->postJson('/reading-list', $this->payload())->assertStatus(401);
        $this->assertDatabaseCount('reading_list_items', 0);
    }

    public function test_the_feed_marks_already_saved_posts(): void
    {
        $user = User::factory()->create();
        Subscription::create(['user_id' => $user->id, 'subreddit' => 'php']);
        $this->item($user, ['post_id' => 'saved1']);

        $this->mock(Reddit::class, function ($m) {
            $m->shouldReceive('homeListing')->andReturn(['data' => ['children' => [
                ['kind' => 't3', 'data' => ['id' => 'saved1', 'title' => 'Already saved', 'subreddit' => 'php',
                    'author' => 'a', 'created_utc' => 1750000000, 'score' => 1, 'num_comments' => 1]],
                ['kind' => 't3', 'data' => ['id' => 'fresh1', 'title' => 'Not saved', 'subreddit' => 'php',
                    'author' => 'a', 'created_utc' => 1750000000, 'score' => 1, 'num_comments' => 1]],
            ], 'after' => null]]);
        });

        $html = $this->actingAs($user)->get('/')->getContent();

        $this->assertMatchesRegularExpression('/data-post-id="saved1"[^>]*data-inlist="1"/s', $html);
        $this->assertMatchesRegularExpression('/data-post-id="fresh1"[^>]*data-inlist="0"/s', $html);
    }

    public function test_the_dock_is_hidden_when_the_list_is_empty(): void
    {
        // The dock lives on ordinary pages (checked on /saved — it needs no
        // Reddit fetch); the reading-list pages themselves never render it,
        // since it would be a floating miniature of the page you're on.
        $user = User::factory()->create();

        $empty = $this->actingAs($user)->get('/saved')->getContent();
        $this->assertMatchesRegularExpression('/id="reading-dock"[^>]*class="[^"]*hidden/s', $empty);

        $this->item($user);

        $full = $this->actingAs($user)->get('/saved')->getContent();
        $this->assertDoesNotMatchRegularExpression('/id="reading-dock"[^>]*class="[^"]*hidden/s', $full);
        $this->assertStringContainsString('A post worth reading later', $full);

        $this->assertStringNotContainsString('id="reading-dock"',
            $this->actingAs($user)->get('/reading-list')->getContent());
    }
}
