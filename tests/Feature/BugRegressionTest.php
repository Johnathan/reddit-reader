<?php

namespace Tests\Feature;

use App\Exceptions\RedditException;
use App\Models\User;
use App\Services\ArticleExtractor;
use App\Services\FeedMerger;
use App\Services\HackerNews;
use App\Services\ImageProxy;
use App\Services\Reddit;
use App\Services\RssSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * Pins for a batch of bugs found in the 2026-09 sweep, so none of them
 * quietly come back.
 */
class BugRegressionTest extends TestCase
{
    use RefreshDatabase;

    // ---- SSRF via redirects ----------------------------------------------

    public function test_the_image_proxy_does_not_follow_redirects(): void
    {
        // An allowed host answering 302 to an internal address: followed, the
        // proxy would hand internal responses to the caller. It must refuse.
        Http::fake([
            'i.imgur.com/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/latest/meta-data/']),
            '*' => Http::response('INTERNAL', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        config(['media.proxy_template' => 'self']);
        $this->actingAs(User::factory()->create())
            ->get(ImageProxy::wrap('https://i.imgur.com/abc123.jpg'))
            ->assertStatus(502);

        Http::assertNotSent(fn ($req) => str_contains($req->url(), '127.0.0.1'));
    }

    public function test_the_article_extractor_refuses_a_redirect_off_the_allowlist(): void
    {
        Http::fake([
            'www.bbc.co.uk/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/secret']),
            '*' => Http::response('INTERNAL'),
        ]);

        $this->assertNull(app(ArticleExtractor::class)->extract('https://www.bbc.co.uk/news/articles/x123'));

        Http::assertNotSent(fn ($req) => str_contains($req->url(), '127.0.0.1'));
    }

    public function test_the_article_extractor_still_follows_redirects_within_the_allowlist(): void
    {
        $article = '<html><head><title>T</title></head><body><article>'
            .str_repeat('<p>A perfectly reasonable paragraph of news content for the parser.</p>', 20)
            .'</article></body></html>';

        Http::fake([
            'www.bbc.co.uk/*' => Http::response('', 301, ['Location' => 'https://www.bbc.com/news/articles/x123']),
            'www.bbc.com/*' => Http::response($article),
        ]);

        $result = app(ArticleExtractor::class)->extract('https://www.bbc.co.uk/news/articles/x123');

        $this->assertNotNull($result);
        $this->assertStringContainsString('reasonable paragraph', $result['html']);
    }

    // ---- a source failing on page one must stay retryable -----------------

    public function test_a_source_failing_on_page_one_is_retried_not_retired(): void
    {
        $hn = new class extends HackerNews
        {
            public function listing(string $sort, ?string $cursor = null): array
            {
                return ['items' => [], 'cursor' => null];
            }
        };
        $rss = new class extends RssSource
        {
            public function listing(string $key, ?string $cursor = null): array
            {
                return ['items' => [], 'cursor' => null];
            }
        };

        $reddit = Mockery::mock(Reddit::class);
        $reddit->shouldReceive('homeListing')->once()->andThrow(new RedditException('Reddit fell over.'));

        $first = (new FeedMerger($reddit, $hn, $rss))->page(['php'], 'hot', null, [], true);

        // The failed source keeps a retryable cursor and the feed offers more.
        $this->assertSame('start', $first['cursors']['reddit']);
        $this->assertTrue(FeedMerger::hasMore($first['cursors']));

        // "Load more" with that cursor fetches reddit from the top again.
        $reddit = Mockery::mock(Reddit::class);
        $reddit->shouldReceive('homeListing')
            ->once()
            ->withArgs(fn ($subs, $sort, $params) => ($params['after'] ?? null) === null)
            ->andReturn(['data' => ['children' => [], 'after' => null]]);

        $second = (new FeedMerger($reddit, $hn, $rss))
            ->page(['php'], 'hot', null, $first['cursors'], false);

        $this->assertSame('end', $second['cursors']['reddit']);
    }

    // ---- seen keys ---------------------------------------------------------

    public function test_seen_keys_require_the_source_prefix_to_be_delimited(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/seen', ['key' => 'reddit:abc12'])->assertOk();
        $this->actingAs($user)->postJson('/seen', ['key' => 'redditabc12'])->assertStatus(422);
        $this->actingAs($user)->postJson('/seen', ['key' => 'rss:bbcni:d41d8cd9'])->assertOk();

        $keys = $user->seenItems()->orderBy('seen_key')->pluck('seen_key');

        $this->assertSame(['reddit:abc12', 'rss:bbcni:d41d8cd9'], $keys->all());
    }

    public function test_the_seen_import_bulk_inserts_and_skips_junk(): void
    {
        $user = User::factory()->create();
        $user->markSeen('reddit:already', null);

        $this->actingAs($user)->postJson('/seen/import', ['items' => [
            'reddit:aaa' => ['t' => 1750000000000, 'c' => 4],
            'hn:123' => ['t' => 1750000000000],
            'reddit:already' => ['t' => 1750000000000],   // server state wins
            'nonsense' => ['t' => 1750000000000],          // bad key: skipped
        ]])->assertOk()->assertJson(['ok' => true, 'imported' => 2]);

        $this->assertSame(3, $user->seenItems()->count());
    }

    // ---- reading-list ids are only unique per source -----------------------

    public function test_removing_a_reading_list_item_is_scoped_to_its_source(): void
    {
        $user = User::factory()->create();
        $shared = ['title' => 'Same id, different worlds', 'score' => 1, 'num_comments' => 0];
        $user->readingList()->create($shared + ['post_id' => 'clash', 'subreddit' => 'php', 'source' => 'reddit']);
        $user->readingList()->create($shared + ['post_id' => 'clash', 'subreddit' => 'bbcni', 'source' => 'bbcni', 'url' => 'https://www.bbc.co.uk/x']);

        $this->actingAs($user)->post('/reading-list/clash', ['_method' => 'DELETE', 'source' => 'bbcni'])
            ->assertOk();

        // Only the RSS row went; the Reddit one survives.
        $this->assertSame(['reddit'], $user->readingList()->pluck('source')->all());
    }

    public function test_toggling_read_is_scoped_to_its_source(): void
    {
        $user = User::factory()->create();
        $shared = ['title' => 'Same id, different worlds', 'score' => 1, 'num_comments' => 0];
        $user->readingList()->create($shared + ['post_id' => 'clash', 'subreddit' => 'php', 'source' => 'reddit']);
        $user->readingList()->create($shared + ['post_id' => 'clash', 'subreddit' => 'hackernews', 'source' => 'hn']);

        $this->actingAs($user)->postJson('/reading-list/clash/read', ['source' => 'hn'])->assertOk();

        $this->assertNull($user->readingList()->where('source', 'reddit')->first()->read_at);
        $this->assertNotNull($user->readingList()->where('source', 'hn')->first()->read_at);
    }

    // ---- sanitizer hardening ----------------------------------------------

    public function test_script_scheme_urls_are_neutralised(): void
    {
        $html = '<a href="javascript:alert(1)">x</a>'
            .'<a href=" JAVASCRIPT:alert(2)">y</a>'
            .'<a href="data:text/html,<script>alert(3)</script>">z</a>'
            .'<img src="data:image/png;base64,iVBORw0KGgo=">'
            .'<a href="https://example.com/fine">ok</a>';

        $clean = Reddit::sanitizeHtml($html);

        $this->assertStringNotContainsString('href="javascript:', $clean);
        $this->assertStringNotContainsString('JAVASCRIPT:alert', $clean);
        $this->assertStringNotContainsString('href="data:', $clean);
        // data: images in a src are harmless and must survive.
        $this->assertStringContainsString('src="data:image/png', $clean);
        $this->assertStringContainsString('https://example.com/fine', $clean);
    }
}
