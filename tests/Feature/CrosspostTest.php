<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Reddit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A crosspost carries no text or media of its own — Reddit keeps them on the
 * original, in crosspost_parent_list. Read raw, every crossposted gallery
 * rendered as a bare title.
 */
class CrosspostTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Http::preventStrayRequests();
    }

    /**
     * Answer Reddit requests by path, below the transport (session, token,
     * challenge), which is not what's under test here.
     *
     * @param  array<string, array<mixed>>  $responses  path => decoded JSON
     */
    private function fakeReddit(array $responses): void
    {
        $this->app->instance(Reddit::class, new class($responses) extends Reddit
        {
            public function __construct(private array $responses) {}

            protected function request(string $path, array $query, bool $retrying = false): ?array
            {
                return $this->responses[$path] ?? throw new \RuntimeException("Unexpected Reddit request: {$path}");
            }
        });
    }

    private function galleryOriginal(array $overrides = []): array
    {
        return array_merge([
            'id' => 'orig1', 'subreddit' => 'Fusion360', 'title' => 'My macropad', 'author' => 'maker',
            'num_comments' => 4, 'is_self' => false, 'is_gallery' => true,
            'url' => 'https://www.reddit.com/gallery/orig1', 'domain' => 'reddit.com',
            'selftext' => 'Designed in Fusion', 'selftext_html' => '<p>Designed in Fusion</p>',
            'gallery_data' => ['items' => [['media_id' => 'a'], ['media_id' => 'b']]],
            'media_metadata' => [
                'a' => ['s' => ['u' => 'https://i.redd.it/aaa.jpg']],
                'b' => ['s' => ['u' => 'https://i.redd.it/bbb.jpg']],
            ],
            'removed_by_category' => null,
        ], $overrides);
    }

    /** What Reddit actually serves once a moderator removes the original. */
    private function removedOriginal(): array
    {
        return [
            'id' => 'orig1', 'subreddit' => 'Fusion360', 'title' => '[ Removed by moderator ]',
            'author' => '[deleted]', 'num_comments' => 4, 'is_self' => false,
            'url' => 'https://www.reddit.com/gallery/orig1', 'domain' => 'reddit.com',
            'selftext' => '[removed]', 'removed_by_category' => 'moderator',
            'is_gallery' => null, 'gallery_data' => null, 'media_metadata' => null, 'preview' => null,
        ];
    }

    private function crosspost(array $original): array
    {
        return [
            'id' => 'xp1', 'subreddit' => 'macro_pads', 'title' => 'Custom 53-key macropad', 'author' => 'maker',
            'created_utc' => 1750000000, 'score' => 5, 'num_comments' => 0, 'permalink' => '/r/macro_pads/comments/xp1/x/',
            'is_self' => false, 'selftext' => '', 'selftext_html' => null,
            'url' => 'https://www.reddit.com/gallery/orig1', 'domain' => 'reddit.com',
            'is_gallery' => null, 'gallery_data' => null, 'media_metadata' => null, 'preview' => null,
            'crosspost_parent_list' => [$original],
        ];
    }

    private function fakeThread(array $post): void
    {
        $this->fakeReddit([
            '/r/macro_pads/comments/xp1.json' => [
                ['kind' => 'Listing', 'data' => ['children' => [['kind' => 't3', 'data' => $post]]]],
                ['kind' => 'Listing', 'data' => ['children' => []]],
            ],
        ]);
    }

    public function test_a_crossposted_gallery_shows_the_originals_images_and_text(): void
    {
        $this->fakeThread($this->crosspost($this->galleryOriginal()));

        $this->actingAs(User::factory()->create())
            ->get('/r/macro_pads/comments/xp1/x')
            ->assertOk()
            ->assertSee('https://i.redd.it/aaa.jpg', false)
            ->assertSee('https://i.redd.it/bbb.jpg', false)
            ->assertSee('Designed in Fusion')
            ->assertSee('Crossposted from')
            ->assertSee('/r/Fusion360/comments/orig1', false)
            ->assertSee('4 comments there');
    }

    public function test_a_crosspost_of_a_removed_post_says_why_it_is_empty(): void
    {
        $this->fakeThread($this->crosspost($this->removedOriginal()));

        $this->actingAs(User::factory()->create())
            ->get('/r/macro_pads/comments/xp1/x')
            ->assertOk()
            ->assertSee('The original post in r/Fusion360 was removed by its moderators.')
            ->assertSee('nothing to show')
            ->assertDontSee('[removed]')
            ->assertSee('/r/Fusion360/comments/orig1', false);
    }

    public function test_a_shared_link_names_the_original_without_linking_into_the_app(): void
    {
        $this->fakeThread($this->crosspost($this->galleryOriginal()));

        $this->get('/r/macro_pads/comments/xp1/x')
            ->assertOk()
            ->assertSee('https://i.redd.it/aaa.jpg', false)
            ->assertSee('Crossposted from')
            ->assertDontSee('/r/Fusion360/comments/orig1', false);
    }

    public function test_a_crossposted_gallery_unfolds_in_the_feed(): void
    {
        $this->fakeReddit([
            '/r/macro_pads/hot.json' => ['kind' => 'Listing', 'data' => [
                'children' => [['kind' => 't3', 'data' => $this->crosspost($this->galleryOriginal())]],
                'after' => null,
            ]],
        ]);

        $post = app(Reddit::class)->listing('macro_pads')['data']['children'][0]['data'];

        $this->assertSame(['https://i.redd.it/aaa.jpg', 'https://i.redd.it/bbb.jpg'], Reddit::expando($post)['images']);
        $this->assertSame('macro_pads', $post['subreddit'], 'only content is copied, not where it was posted');
        $this->assertSame('xp1', $post['id']);
    }

    public function test_a_removed_originals_marker_is_not_copied_as_body_text(): void
    {
        $post = Reddit::withCrosspostContent($this->crosspost($this->removedOriginal()));

        $this->assertSame('', $post['selftext']);
        $this->assertFalse(Reddit::isExpandable($post));
    }

    public function test_an_ordinary_removed_post_gets_a_notice_too(): void
    {
        $this->assertSame(
            'This post was deleted by its author.',
            Reddit::removalNotice(['subreddit' => 'php', 'selftext' => '[deleted]', 'removed_by_category' => 'deleted']),
        );
        $this->assertNull(Reddit::removalNotice(['subreddit' => 'php', 'selftext' => 'hello']));
    }
}
