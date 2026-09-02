<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Reddit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingListReaderTest extends TestCase
{
    use RefreshDatabase;

    private function item(User $user, array $o = [])
    {
        return $user->readingList()->create(array_merge([
            'post_id' => 'abc123', 'subreddit' => 'php', 'title' => 'A saved post',
            'slug' => 'a-saved-post', 'score' => 10, 'num_comments' => 3,
        ], $o));
    }

    // ---- the deck --------------------------------------------------------

    public function test_the_reader_shows_only_unread_items_as_cards(): void
    {
        $user = User::factory()->create();
        $this->item($user, ['post_id' => 'unread1', 'title' => 'Still to read']);
        $this->item($user, ['post_id' => 'read1', 'title' => 'Already done'])->update(['read_at' => now()]);

        $res = $this->actingAs($user)->get('/reading-list/read');

        $res->assertOk();
        // Assert on the deck's own lazy-content URLs, not page text — the
        // floating dock lists every item and would muddy a title match.
        $res->assertSee(route('reading.content', 'unread1'), false);
        $res->assertSee(route('reading.markRead', 'unread1'), false);
        $res->assertDontSee(route('reading.content', 'read1'), false);
    }

    public function test_the_reader_has_an_empty_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/reading-list/read')
            ->assertOk()
            ->assertSee('Nothing left to read');
    }

    // ---- lazy card content ----------------------------------------------

    public function test_card_content_renders_the_post_and_its_comments(): void
    {
        $user = User::factory()->create();
        $this->item($user, ['post_id' => 'abc123']);

        $this->mock(Reddit::class, function ($m) {
            $m->shouldReceive('comments')->with('php', 'abc123')->andReturn([
                ['data' => ['children' => [['data' => [
                    'id' => 'abc123', 'title' => 'The real fetched title', 'subreddit' => 'php',
                    'author' => 'bob', 'created_utc' => 1750000000, 'score' => 99, 'num_comments' => 1,
                    'is_self' => true, 'selftext_html' => '<p>the body</p>', 'permalink' => '/r/php/x',
                ]]]]],
                ['data' => ['children' => [['kind' => 't1', 'data' => [
                    'id' => 'c1', 'author' => 'carol', 'subreddit' => 'php', 'score' => 4,
                    'created_utc' => 1750000100, 'body_html' => '<p>a fetched comment</p>',
                ]]]]],
            ]);
        });

        $res = $this->actingAs($user)->get('/reading-list/abc123/content');

        $res->assertOk();
        $res->assertSee('The real fetched title');
        $res->assertSee('the body', false);
        $res->assertSee('a fetched comment', false);
        // A bare fragment — no page chrome.
        $this->assertStringNotContainsString('<html', $res->getContent());
        $this->assertStringNotContainsString('app-tabbar', $res->getContent());
    }

    public function test_card_content_survives_a_removed_post(): void
    {
        $user = User::factory()->create();
        $this->item($user, ['post_id' => 'gone', 'title' => 'Snapshot title']);

        $this->mock(Reddit::class, function ($m) {
            $m->shouldReceive('comments')->andReturn([['data' => ['children' => []]], ['data' => ['children' => []]]]);
        });

        $res = $this->actingAs($user)->get('/reading-list/gone/content');

        $res->assertOk();
        $res->assertSee('could not be loaded');
        $res->assertSee('Snapshot title');   // still names it from the snapshot
    }

    public function test_card_content_is_scoped_to_the_owner(): void
    {
        $mine = User::factory()->create();
        $theirs = User::factory()->create();
        $this->item($theirs, ['post_id' => 'secret']);

        $this->actingAs($mine)->get('/reading-list/secret/content')->assertNotFound();
    }

    // ---- mark read as you go --------------------------------------------

    public function test_mark_read_sets_the_item_read_and_returns_counts(): void
    {
        $user = User::factory()->create();
        $this->item($user, ['post_id' => 'abc123']);

        $this->actingAs($user)->postJson('/reading-list/abc123/mark-read')
            ->assertOk()
            ->assertJson(['read' => true, 'unread' => 0, 'count' => 1]);

        $this->assertNotNull($user->readingList()->first()->read_at);
    }

    public function test_mark_read_is_idempotent_and_never_toggles_back(): void
    {
        $user = User::factory()->create();
        $this->item($user, ['post_id' => 'abc123']);

        $this->actingAs($user)->postJson('/reading-list/abc123/mark-read')->assertOk();
        $first = $user->readingList()->first()->read_at;

        // A second call must keep it read (toggleRead would flip it back).
        $this->actingAs($user)->postJson('/reading-list/abc123/mark-read')
            ->assertOk()->assertJson(['read' => true]);

        $this->assertNotNull($user->readingList()->first()->read_at);
        $this->assertTrue($first->equalTo($user->readingList()->first()->read_at), 'read_at should not change on repeat');
    }

    public function test_mark_read_is_scoped_to_the_owner(): void
    {
        $mine = User::factory()->create();
        $theirs = User::factory()->create();
        $this->item($theirs, ['post_id' => 'secret']);

        $this->actingAs($mine)->postJson('/reading-list/secret/mark-read')->assertNotFound();
        $this->assertNull($theirs->readingList()->first()->read_at);
    }

    // ---- the archive tab -------------------------------------------------

    public function test_the_index_read_tab_shows_only_read_items(): void
    {
        $user = User::factory()->create();
        $this->item($user, ['post_id' => 'unread1', 'title' => 'To read still']);
        $this->item($user, ['post_id' => 'read1', 'title' => 'Finished this'])->update(['read_at' => now()]);

        // Target the list rows specifically — the floating dock lists both, so
        // a plain title match would see everything on either tab.
        $this->actingAs($user)->get('/reading-list')
            ->assertSee('data-reading-row data-post-id="unread1"', false)
            ->assertDontSee('data-reading-row data-post-id="read1"', false);

        $this->actingAs($user)->get('/reading-list?view=read')
            ->assertSee('data-reading-row data-post-id="read1"', false)
            ->assertDontSee('data-reading-row data-post-id="unread1"', false);
    }

    public function test_the_reader_requires_a_login(): void
    {
        $this->get('/reading-list/read')->assertRedirect();
        $this->postJson('/reading-list/abc123/mark-read')->assertStatus(401);
    }
}
