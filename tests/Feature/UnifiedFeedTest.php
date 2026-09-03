<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use App\Services\ArticleExtractor;
use App\Services\HackerNews;
use App\Services\Reddit;
use App\Services\RssSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UnifiedFeedTest extends TestCase
{
    use RefreshDatabase;

    private function redditPost(string $id, int $ts, string $title): array
    {
        return ['kind' => 't3', 'data' => [
            'id' => $id, 'title' => $title, 'subreddit' => 'php', 'author' => 'bob',
            'created_utc' => $ts, 'score' => 10, 'num_comments' => 2,
        ]];
    }

    private function hnItem(string $id, int $ts, string $title): array
    {
        return [
            '_source' => 'hn', 'id' => $id, 'title' => $title, 'author' => 'pg',
            'created_utc' => $ts, 'score' => 100, 'num_comments' => 40, 'url' => 'https://example.com/a',
            'domain' => 'example.com', 'is_self' => false, 'selftext_html' => '',
            'permalink' => 'https://news.ycombinator.com/item?id='.$id,
            'subreddit' => null, 'stickied' => false, 'over_18' => false,
        ];
    }

    private function rssItem(string $id, int $ts, string $title): array
    {
        return [
            '_source' => 'rss', 'source_key' => 'bbcni', 'source_name' => 'BBC NI',
            'id' => $id, 'title' => $title, 'author' => null, 'created_utc' => $ts,
            'score' => null, 'num_comments' => null, 'url' => 'https://www.bbc.co.uk/news/'.$id,
            'domain' => 'bbc.co.uk', 'is_self' => false, 'selftext_html' => '', 'summary' => 'A summary.',
            'permalink' => 'https://www.bbc.co.uk/news/'.$id, 'subreddit' => null,
            'thumbnail' => null, 'stickied' => false, 'over_18' => false,
        ];
    }

    private function fakeSources(array $reddit = [], array $hn = [], array $rss = [], array $cursors = []): void
    {
        $this->mock(Reddit::class, function ($m) use ($reddit, $cursors) {
            $m->shouldReceive('homeListing')->andReturn(['data' => [
                'children' => $reddit, 'after' => $cursors['reddit'] ?? null,
            ]]);
        });

        $hnCursor = $cursors['hn'] ?? null;
        $this->instance(HackerNews::class, new class($hn, $hnCursor) extends HackerNews
        {
            public function __construct(private array $items, private ?string $cursor) {}

            public function listing(string $sort, ?string $c = null): array
            {
                return ['items' => $this->items, 'cursor' => $this->cursor];
            }
        });

        $rssCursor = $cursors['rss'] ?? null;
        $this->instance(RssSource::class, new class($rss, $rssCursor) extends RssSource
        {
            public function __construct(private array $items, private ?string $cursor) {}

            public function listing(string $key, ?string $c = null): array
            {
                return ['items' => $this->items, 'cursor' => $this->cursor];
            }
        });
    }

    // ---- the merge -------------------------------------------------------

    public function test_new_merges_every_source_strictly_by_timestamp(): void
    {
        $user = User::factory()->create();
        Subscription::create(['user_id' => $user->id, 'subreddit' => 'php']);

        $this->fakeSources(
            reddit: [$this->redditPost('r1', 300, 'Reddit newest'), $this->redditPost('r2', 100, 'Reddit oldest')],
            hn: [$this->hnItem('111', 250, 'HN middle')],
            rss: [$this->rssItem('aaa', 200, 'BBC between')],
        );

        $html = $this->actingAs($user)->get('/?sort=new')->getContent();

        $positions = array_map(fn ($t) => strpos($html, $t),
            ['Reddit newest', 'HN middle', 'BBC between', 'Reddit oldest']);

        $this->assertNotContains(false, $positions, 'every source appears');
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions, 'strict timestamp order, newest first');
    }

    public function test_every_source_appears_on_the_hot_feed(): void
    {
        $user = User::factory()->create();
        Subscription::create(['user_id' => $user->id, 'subreddit' => 'php']);

        $this->fakeSources(
            reddit: [$this->redditPost('r1', 300, 'A reddit post')],
            hn: [$this->hnItem('111', 250, 'An HN story')],
            rss: [$this->rssItem('aaa', 200, 'A BBC story')],
        );

        $res = $this->actingAs($user)->get('/');

        $res->assertOk()
            ->assertSee('A reddit post')->assertSee('An HN story')->assertSee('A BBC story')
            ->assertSee('>HN<', false)          // source badges
            ->assertSee('BBC NI');
    }

    public function test_the_feed_works_with_no_subreddit_subscriptions(): void
    {
        $user = User::factory()->create();

        $this->fakeSources(hn: [$this->hnItem('111', 250, 'Only HN here')]);

        $this->actingAs($user)->get('/')->assertOk()->assertSee('Only HN here');
    }

    public function test_load_more_carries_every_sources_cursor(): void
    {
        $user = User::factory()->create();
        Subscription::create(['user_id' => $user->id, 'subreddit' => 'php']);

        $this->fakeSources(
            reddit: [$this->redditPost('r1', 300, 'A reddit post')],
            hn: [$this->hnItem('111', 250, 'An HN story')],
            rss: [$this->rssItem('aaa', 200, 'A BBC story')],
            cursors: ['reddit' => 't3_r1', 'hn' => '1', 'rss' => '200'],
        );

        $html = $this->actingAs($user)->get('/')->getContent();

        $this->assertMatchesRegularExpression('/c%5Breddit%5D=t3_r1|c\[reddit\]=t3_r1/', $html);
        $this->assertMatchesRegularExpression('/c%5Bhn%5D=1|c\[hn\]=1/', $html);
        $this->assertMatchesRegularExpression('/c%5Bbbcni%5D=200|c\[bbcni\]=200/', $html);
    }

    public function test_one_failing_source_does_not_sink_the_feed(): void
    {
        $user = User::factory()->create();
        Subscription::create(['user_id' => $user->id, 'subreddit' => 'php']);

        $this->mock(Reddit::class, function ($m) {
            $m->shouldReceive('homeListing')->andThrow(new \App\Exceptions\RedditException('Reddit fell over.'));
        });
        $this->instance(HackerNews::class, new class extends HackerNews
        {
            public function listing(string $sort, ?string $c = null): array
            {
                return ['items' => [[
                    '_source' => 'hn', 'id' => '9', 'title' => 'HN survives', 'author' => 'pg',
                    'created_utc' => 1, 'score' => 1, 'num_comments' => 0, 'url' => null, 'domain' => null,
                    'is_self' => true, 'selftext_html' => '', 'permalink' => 'https://news.ycombinator.com/item?id=9',
                    'subreddit' => null, 'stickied' => false, 'over_18' => false,
                ]], 'cursor' => null];
            }
        });

        $res = $this->actingAs($user)->get('/');

        $res->assertOk()->assertSee('HN survives')->assertSee('Reddit fell over.');
    }

    // ---- the HN service against the real payload shape --------------------

    public function test_hn_service_maps_algolia_search_hits(): void
    {
        // A FULL page (20 hits) — a short page correctly means "last page".
        $hits = array_map(fn ($i) => [
            'objectID' => (string) (49241679 + $i), 'title' => 'A story '.$i, 'author' => 'riordan',
            'points' => 993, 'num_comments' => 561, 'url' => 'https://www.example.com/blog/post',
            'created_at_i' => 1786356602 - $i,
        ], range(0, 19));

        Http::fake(['hn.algolia.com/*' => Http::response(['hits' => $hits, 'nbPages' => 5])]);

        $page = (new HackerNews)->listing('hot');

        $this->assertCount(20, $page['items']);
        $item = $page['items'][0];
        $this->assertSame('hn', $item['_source']);
        $this->assertSame('49241679', $item['id']);
        $this->assertSame(993, $item['score']);
        $this->assertSame('example.com', $item['domain']);
        $this->assertSame('1', $page['cursor'], 'page-numbered cursor advances');
    }

    public function test_hn_service_maps_a_thread_with_nested_comments(): void
    {
        Http::fake(['hn.algolia.com/*' => Http::response([
            'id' => 1, 'type' => 'story', 'title' => 'Y Combinator', 'author' => 'pg',
            'points' => 57, 'created_at_i' => 1160418111, 'url' => 'http://ycombinator.com',
            'children' => [[
                'id' => 17, 'author' => 'sama', 'text' => '<p>top comment</p>', 'points' => null,
                'created_at_i' => 1160423565,
                'children' => [[
                    'id' => 1079, 'author' => 'pg', 'text' => 'sure', 'points' => null,
                    'created_at_i' => 1172441903, 'children' => [],
                ]],
            ]],
        ])]);

        $thread = (new HackerNews)->thread('1');

        $this->assertSame('Y Combinator', $thread['item']['title']);
        $this->assertSame(2, $thread['item']['num_comments'], 'counts the whole tree');

        $top = $thread['comments'][0];
        $this->assertSame('t1', $top['kind']);
        $this->assertSame('sama', $top['data']['author']);
        $reply = $top['data']['replies']['data']['children'][0];
        $this->assertSame('pg', $reply['data']['author']);
    }

    // ---- HN thread page ---------------------------------------------------

    public function test_the_hn_item_page_renders_story_and_comments(): void
    {
        $this->instance(HackerNews::class, new class extends HackerNews
        {
            public function thread(string $id): ?array
            {
                return [
                    'item' => [
                        '_source' => 'hn', 'id' => '1', 'type' => 'story', 'story_id' => null,
                        'title' => 'Y Combinator', 'author' => 'pg', 'created_utc' => 1160418111,
                        'score' => 57, 'num_comments' => 1, 'url' => 'http://ycombinator.com',
                        'domain' => 'ycombinator.com', 'is_self' => false, 'selftext_html' => '',
                        'permalink' => 'https://news.ycombinator.com/item?id=1',
                        'subreddit' => null, 'stickied' => false, 'over_18' => false,
                    ],
                    'comments' => [['kind' => 't1', 'data' => [
                        'id' => '17', 'author' => 'sama', 'body_html' => '<p>a fine comment</p>',
                        'score' => 0, 'created_utc' => 1160423565, 'subreddit' => null, 'replies' => '',
                    ]]],
                ];
            }

            public function feedCommentCount(string $id): ?int
            {
                return null;   // no network in tests
            }
        });

        $res = $this->actingAs(User::factory()->create())->get('/hn/1');

        $res->assertOk()
            ->assertSee('Y Combinator')
            ->assertSee('a fine comment', false)
            ->assertSee('u/sama');
    }

    public function test_the_hn_item_page_rejects_non_numeric_ids(): void
    {
        $this->actingAs(User::factory()->create())->get('/hn/../etc')->assertNotFound();
        $this->actingAs(User::factory()->create())->get('/hn/abc')->assertNotFound();
    }

    // ---- the article reader -----------------------------------------------

    public function test_the_article_page_extracts_and_renders_full_text(): void
    {
        $entry = $this->rssItem('deadbeef00000000', 1786400000, 'Stormont story');

        $this->instance(RssSource::class, new class($entry) extends RssSource
        {
            public function __construct(private array $entry) {}

            public function resolve(string $key, string $id): ?array
            {
                return $id === 'deadbeef00000000' ? $this->entry : null;
            }
        });

        Http::fake(['www.bbc.co.uk/*' => Http::response(
            '<html><head><title>Stormont story</title></head><body><article><h1>Stormont story</h1>'
            .str_repeat('<p>Full paragraph of the actual article body text, not just a headline.</p>', 12)
            .'</article></body></html>'
        )]);

        $res = $this->actingAs(User::factory()->create())->get('/news/bbcni/deadbeef00000000');

        $res->assertOk()
            ->assertSee('BBC NI')
            ->assertSee('Full paragraph of the actual article body', false)
            ->assertSee('Original article');
    }

    public function test_rss_cards_offer_an_article_expando(): void
    {
        $user = User::factory()->create();

        $this->fakeSources(rss: [$this->rssItem('deadbeef00000000', 200, 'A BBC story')]);

        $html = $this->actingAs($user)->get('/')->getContent();

        // The button carries the fetch URL for the extracted article...
        $this->assertStringContainsString(
            'data-fetch="'.route('article.body', ['source' => 'bbcni', 'id' => 'deadbeef00000000']).'"',
            $html,
        );
        $this->assertStringContainsString('>article</span>', $html);
        // ...and the page offers expand-all even though nothing is Reddit-expandable.
        $this->assertStringContainsString('data-expando-all', $html);
    }

    public function test_the_article_body_fragment_returns_extracted_text(): void
    {
        $entry = $this->rssItem('deadbeef00000000', 1786400000, 'Stormont story');
        $entry['thumbnail'] = 'https://ichef.example/240/cpsprodpb/abc.jpg';

        $this->instance(RssSource::class, new class($entry) extends RssSource
        {
            public function __construct(private array $entry) {}

            public function resolve(string $key, string $id): ?array
            {
                return $id === 'deadbeef00000000' ? $this->entry : null;
            }
        });

        Http::fake(['www.bbc.co.uk/*' => Http::response(
            '<html><head><title>Stormont story</title></head><body><article>'
            .str_repeat('<p>Inline expando body text from the extracted article.</p>', 12)
            .'</article></body></html>'
        )]);

        $res = $this->actingAs(User::factory()->create())->get('/news/bbcni/deadbeef00000000/body');

        $res->assertOk();
        $res->assertSee('Inline expando body text', false);
        // Extraction lost the images, so the feed thumbnail steps in as hero.
        $res->assertSee('ichef.example/800/cpsprodpb', false);
        $res->assertSee('Original article');
        // A bare fragment, not a page.
        $this->assertStringNotContainsString('<html', $res->getContent());
    }

    public function test_the_article_body_fragment_reports_a_dropped_entry(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/news/bbcni/0000000000000000/body')
            ->assertOk()
            ->assertSee('dropped out of the feed');
    }

    public function test_the_article_page_404s_for_unknown_sources(): void
    {
        $this->actingAs(User::factory()->create())->get('/news/nope/abc123')->assertNotFound();
    }

    public function test_the_extractor_refuses_hosts_outside_the_source_list(): void
    {
        Http::fake();

        $this->assertNull((new ArticleExtractor)->extract('https://evil.test/x'));
        $this->assertNull((new ArticleExtractor)->extract('https://169.254.169.254/latest'));
        $this->assertNull((new ArticleExtractor)->extract('http://www.bbc.co.uk/insecure'));

        Http::assertNothingSent();
    }

    // ---- cross-source read-later and archive ------------------------------

    public function test_an_hn_story_can_go_on_the_reading_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/reading-list', [
            'post_id' => '49241679', 'source' => 'hn', 'subreddit' => 'hackernews',
            'title' => 'An HN story', 'score' => 993, 'num_comments' => 561,
        ])->assertOk();

        $item = $user->readingList()->first();
        $this->assertSame('hn', $item->source);
        $this->assertStringContainsString('/hn/49241679', $item->url());
    }

    public function test_a_bbc_article_can_go_on_the_reading_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/reading-list', [
            'post_id' => 'deadbeef00000000', 'source' => 'bbcni', 'subreddit' => 'bbcni',
            'title' => 'A BBC story', 'url' => 'https://www.bbc.co.uk/news/x',
        ])->assertOk();

        $item = $user->readingList()->first();
        $this->assertSame('bbcni', $item->source);
        $this->assertSame('https://www.bbc.co.uk/news/x', $item->url);
        $this->assertStringContainsString('/news/bbcni/deadbeef00000000', $item->url());
    }

    public function test_the_same_id_can_exist_in_two_sources(): void
    {
        $user = User::factory()->create();

        foreach (['reddit', 'hn'] as $source) {
            $this->actingAs($user)->postJson('/reading-list', [
                'post_id' => '123456', 'source' => $source, 'subreddit' => 'php',
                'title' => 'Same id, different world',
            ])->assertOk();
        }

        $this->assertSame(2, $user->readingList()->count());
    }

    public function test_an_hn_story_can_be_archived_with_its_body(): void
    {
        $user = User::factory()->create();

        $this->instance(HackerNews::class, new class extends HackerNews
        {
            public function thread(string $id): ?array
            {
                return ['item' => [
                    '_source' => 'hn', 'id' => '42', 'type' => 'story', 'story_id' => null,
                    'title' => 'Ask HN: archiving?', 'author' => 'pg', 'created_utc' => 1160418111,
                    'score' => 10, 'num_comments' => 0, 'url' => null, 'domain' => null,
                    'is_self' => true, 'selftext_html' => '<p>the story body</p>',
                    'permalink' => 'https://news.ycombinator.com/item?id=42',
                    'subreddit' => null, 'stickied' => false, 'over_18' => false,
                ], 'comments' => []];
            }
        });

        $this->actingAs($user)->postJson('/saved', [
            'kind' => 'post', 'source' => 'hn', 'reddit_id' => '42', 'subreddit' => 'hackernews',
        ])->assertOk();

        $item = $user->savedItems()->first();
        $this->assertSame('hn', $item->source);
        $this->assertStringContainsString('the story body', $item->body_html);
    }

    public function test_a_bbc_article_archives_its_extracted_full_text(): void
    {
        $user = User::factory()->create();

        Http::fake(['www.bbc.co.uk/*' => Http::response(
            '<html><head><title>The headline</title></head><body><article>'
            .str_repeat('<p>Body text that must survive in the archive forever and ever.</p>', 12)
            .'</article></body></html>'
        )]);

        $this->actingAs($user)->postJson('/saved', [
            'kind' => 'post', 'source' => 'bbcni', 'reddit_id' => 'deadbeef00000000',
            'subreddit' => 'bbcni', 'url' => 'https://www.bbc.co.uk/news/articles/x',
        ])->assertOk();

        $item = $user->savedItems()->first();
        $this->assertSame('bbcni', $item->source);
        $this->assertStringContainsString('survive in the archive', $item->body_html);
        $this->assertSame('https://www.bbc.co.uk/news/articles/x', $item->permalink);
    }

    public function test_archiving_an_article_from_a_non_allowed_host_is_refused(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())->postJson('/saved', [
            'kind' => 'post', 'source' => 'bbcni', 'reddit_id' => 'abc123',
            'subreddit' => 'bbcni', 'url' => 'https://evil.test/steal',
        ])->assertStatus(404);

        Http::assertNothingSent();
    }
}
