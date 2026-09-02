<?php

namespace Tests\Feature;

use App\Exceptions\RedditException;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Reddit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnfoldTest extends TestCase
{
    use RefreshDatabase;

    private function postData(array $overrides = []): array
    {
        return array_merge([
            'id' => 'p1', 'title' => 'A post', 'subreddit' => 'php', 'author' => 'someone',
            'created_utc' => 1750000000, 'score' => 10, 'num_comments' => 2,
        ], $overrides);
    }

    private function listing(array $posts): array
    {
        return ['kind' => 'Listing', 'data' => [
            'children' => array_map(fn ($p) => ['kind' => 't3', 'data' => $p], $posts),
            'after' => null,
        ]];
    }

    // ---- Reddit::expandableImages ---------------------------------------

    public function test_a_bodyless_text_post_has_nothing_to_expand(): void
    {
        $post = $this->postData(['is_self' => true]);

        $this->assertSame([], Reddit::expandableImages($post));
        $this->assertFalse(Reddit::isExpandable($post));
    }

    public function test_a_direct_image_link_expands_to_itself(): void
    {
        $this->assertSame(
            ['https://i.redd.it/abc.jpg'],
            Reddit::expandableImages($this->postData(['url' => 'https://i.redd.it/abc.jpg'])),
        );
    }

    public function test_a_link_post_expands_to_its_preview_source(): void
    {
        $post = $this->postData([
            'url' => 'https://example.com/article',
            'preview' => ['images' => [['source' => ['url' => 'https://preview.redd.it/x.jpg?width=1080&amp;s=abc']]]],
        ]);

        // raw_json is on, but entities are decoded defensively regardless.
        $this->assertSame(['https://preview.redd.it/x.jpg?width=1080&s=abc'], Reddit::expandableImages($post));
    }

    public function test_a_gallery_expands_to_every_image(): void
    {
        $post = $this->postData([
            'is_gallery' => true,
            'gallery_data' => ['items' => [['media_id' => 'a'], ['media_id' => 'b']]],
            'media_metadata' => [
                'a' => ['s' => ['u' => 'https://i.redd.it/a.jpg']],
                'b' => ['s' => ['u' => 'https://i.redd.it/b.jpg']],
            ],
        ]);

        $this->assertSame(['https://i.redd.it/a.jpg', 'https://i.redd.it/b.jpg'], Reddit::expandableImages($post));
    }

    public function test_a_self_post_expands_its_body_text(): void
    {
        $post = $this->postData(['is_self' => true, 'selftext_html' => '<div class="md"><p>Hello</p></div>']);

        $this->assertSame('<div class="md"><p>Hello</p></div>', Reddit::expandableText($post));
        $this->assertTrue(Reddit::isExpandable($post));
    }

    public function test_body_text_is_sanitized_before_it_is_rendered_raw(): void
    {
        $post = $this->postData(['selftext_html' => '<p>hi</p><script>alert(1)</script>']);

        $this->assertSame('<p>hi</p>', Reddit::expandableText($post));
    }

    public function test_a_video_post_expands_to_a_player_with_a_poster(): void
    {
        $post = $this->postData([
            'media' => ['reddit_video' => ['fallback_url' => 'https://v.redd.it/x/DASH_720.mp4']],
            'preview' => ['images' => [['source' => ['url' => 'https://preview.redd.it/poster.jpg']]]],
        ]);

        $expando = Reddit::expando($post);

        $this->assertSame('https://v.redd.it/x/DASH_720.mp4', $expando['video']['src']);
        $this->assertSame('https://preview.redd.it/poster.jpg', $expando['video']['poster']);
        // The poster must not also appear as a still image — that's the same
        // frame twice.
        $this->assertSame([], $expando['images']);
    }

    public function test_an_oembed_post_expands_to_the_providers_markup(): void
    {
        $post = $this->postData([
            'url' => 'https://youtube.com/watch?v=abc',
            'secure_media' => ['oembed' => ['html' => '&lt;iframe src="https://youtube.com/embed/abc"&gt;&lt;/iframe&gt;']],
            'preview' => ['images' => [['source' => ['url' => 'https://preview.redd.it/thumb.jpg']]]],
        ]);

        $expando = Reddit::expando($post);

        $this->assertSame('<iframe src="https://youtube.com/embed/abc"></iframe>', $expando['embed']);
        $this->assertSame([], $expando['images'], 'the embed replaces the still, not doubles it');
    }

    public function test_a_video_post_wins_over_an_embed(): void
    {
        $post = $this->postData([
            'media' => ['reddit_video' => ['fallback_url' => 'https://v.redd.it/x/DASH_720.mp4']],
            'secure_media' => ['oembed' => ['html' => '<iframe src="https://example.com"></iframe>']],
        ]);

        $this->assertSame('', Reddit::expando($post)['embed']);
    }

    public function test_a_gallery_carries_every_image_for_the_carousel(): void
    {
        $post = $this->postData([
            'is_gallery' => true,
            'gallery_data' => ['items' => [['media_id' => 'a'], ['media_id' => 'b'], ['media_id' => 'c']]],
            'media_metadata' => [
                'a' => ['s' => ['u' => 'https://i.redd.it/a.jpg']],
                'b' => ['s' => ['u' => 'https://i.redd.it/b.jpg']],
                'c' => ['s' => ['u' => 'https://i.redd.it/c.jpg']],
            ],
        ]);

        $this->assertCount(3, Reddit::expando($post)['images']);
    }

    // ---- the Explore unfold ---------------------------------------------

    public function test_the_preview_route_returns_three_posts_as_a_fragment(): void
    {
        $this->mock(Reddit::class, function ($m) {
            $m->shouldReceive('listing')
                ->with('php', 'hot', ['limit' => 3])
                ->andReturn($this->listing([
                    $this->postData(['id' => 'a', 'title' => 'First post']),
                    $this->postData(['id' => 'b', 'title' => 'Second post']),
                    $this->postData(['id' => 'c', 'title' => 'Third post']),
                    $this->postData(['id' => 'd', 'title' => 'Fourth post']),
                ]));
        });

        $res = $this->actingAs(User::factory()->create())->get('/r/php/preview');

        $res->assertOk();
        $res->assertSee('First post');
        $res->assertSee('Third post');
        $res->assertDontSee('Fourth post');
        // A bare fragment: no layout chrome.
        $this->assertStringNotContainsString('<html', $res->getContent());
        $this->assertStringNotContainsString('app-tabbar', $res->getContent());
    }

    public function test_the_preview_reports_reddit_failures_instead_of_blowing_up(): void
    {
        $this->mock(Reddit::class, function ($m) {
            $m->shouldReceive('listing')->andThrow(new RedditException('Reddit is having a moment.'));
        });

        $res = $this->actingAs(User::factory()->create())->get('/r/php/preview');

        $res->assertOk();
        $res->assertSee('Reddit is having a moment.');
    }

    public function test_explore_rows_offer_an_unfold_control(): void
    {
        $user = User::factory()->create();
        Subscription::create(['user_id' => $user->id, 'subreddit' => 'php']);

        $this->mock(Reddit::class, function ($m) {
            $m->shouldReceive('popularSubreddits')->andReturn(['data' => ['children' => [
                ['data' => ['display_name' => 'php', 'subscribers' => 1000, 'public_description' => 'PHP']],
            ]]]);
            $m->shouldReceive('relatedSubreddits')->andReturn([]);
        });

        $res = $this->actingAs($user)->get('/explore');

        $res->assertOk();
        $res->assertSee('data-unfold', false);
        $res->assertSee(route('subreddit.preview', 'php'), false);
    }

    // ---- the feed expando ------------------------------------------------

    public function test_the_feed_shows_an_expando_only_when_a_post_has_images(): void
    {
        $user = User::factory()->create();
        Subscription::create(['user_id' => $user->id, 'subreddit' => 'php']);

        $this->mock(Reddit::class, function ($m) {
            $m->shouldReceive('homeListing')->andReturn($this->listing([
                $this->postData(['id' => 'txt', 'title' => 'Text only', 'is_self' => true]),
                $this->postData(['id' => 'img', 'title' => 'An image', 'url' => 'https://i.redd.it/a.jpg']),
            ]));
        });

        $res = $this->actingAs($user)->get('/');

        $res->assertOk();
        $res->assertSee('data-expando-all aria-pressed', false);
        $res->assertSee('https://i.redd.it/a.jpg', false);
        // One expando button, for the image post only.
        $this->assertSame(1, substr_count($res->getContent(), 'data-expando '));
    }

    public function test_a_text_only_feed_hides_the_expand_all_control(): void
    {
        $user = User::factory()->create();
        Subscription::create(['user_id' => $user->id, 'subreddit' => 'php']);

        $this->mock(Reddit::class, function ($m) {
            $m->shouldReceive('homeListing')->andReturn($this->listing([
                $this->postData(['is_self' => true]),
            ]));
        });

        $res = $this->actingAs($user)->get('/');

        $res->assertOk();
        $res->assertDontSee('data-expando-all aria-pressed', false);
    }
}
