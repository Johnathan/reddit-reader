<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Reddit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $expectedParams  what Reddit::comments must be called with
     * @param  array<string, mixed>  $postOverrides
     */
    private function mockThread(array $expectedParams = [], array $postOverrides = []): void
    {
        $this->mock(Reddit::class, function ($m) use ($expectedParams, $postOverrides) {
            $m->shouldReceive('comments')->with('php', 'abc123', $expectedParams)->andReturn([
                ['data' => ['children' => [['data' => array_merge([
                    'id' => 'abc123', 'title' => 'A post', 'subreddit' => 'php',
                    'author' => 'bob', 'created_utc' => 1750000000, 'score' => 99, 'num_comments' => 1,
                    'is_self' => true, 'selftext' => 'plain body text', 'selftext_html' => '<p>the body</p>',
                    'permalink' => '/r/php/x',
                ], $postOverrides)]]]],
                ['data' => ['children' => [['kind' => 't1', 'data' => [
                    'id' => 'c1', 'author' => 'carol', 'subreddit' => 'php', 'score' => 4,
                    'created_utc' => 1750000100, 'body_html' => '<p>a comment</p>',
                ]]]]],
            ]);
        });
    }

    // ---- comment sorting -------------------------------------------------

    public function test_a_comment_sort_is_passed_through_to_reddit(): void
    {
        $this->mockThread(['sort' => 'top']);

        $this->actingAs(User::factory()->create())
            ->get('/r/php/comments/abc123/x?sort=top')
            ->assertOk();
    }

    public function test_best_is_the_default_and_sends_no_sort_param(): void
    {
        // No param at all: Reddit serves "best" anyway, and omitting it keeps
        // existing cache entries warm.
        $this->mockThread([]);

        $this->actingAs(User::factory()->create())
            ->get('/r/php/comments/abc123/x')
            ->assertOk();
    }

    public function test_qa_maps_to_reddits_api_value(): void
    {
        $this->mockThread(['sort' => 'qa']);

        $this->actingAs(User::factory()->create())
            ->get('/r/php/comments/abc123/x?sort=qa')
            ->assertOk();
    }

    public function test_an_unknown_sort_falls_back_to_best(): void
    {
        $this->mockThread([]);

        $this->actingAs(User::factory()->create())
            ->get('/r/php/comments/abc123/x?sort=evil')
            ->assertOk();
    }

    public function test_the_sort_pills_render_for_the_owner_only(): void
    {
        $this->mockThread([]);

        $this->actingAs(User::factory()->create())
            ->get('/r/php/comments/abc123/x')
            ->assertOk()
            ->assertSee('Sorted by')
            ->assertSee('Controversial');
    }

    // ---- sharing ---------------------------------------------------------

    public function test_the_owner_gets_a_share_button_with_the_public_url(): void
    {
        $this->mockThread([]);

        $this->actingAs(User::factory()->create())
            ->get('/r/php/comments/abc123/x')
            ->assertOk()
            ->assertSee('data-share', false)
            ->assertSee(route('posts.show', ['subreddit' => 'php', 'id' => 'abc123', 'slug' => 'a-post']), false);
    }

    // ---- the guest page stays stripped ----------------------------------

    public function test_guests_get_neither_sort_pills_nor_a_share_button(): void
    {
        $this->mockThread([]);

        $html = $this->get('/r/php/comments/abc123/x')->getContent();

        $this->assertStringNotContainsString('Sorted by', $html);
        $this->assertStringNotContainsString('data-share', $html);
    }

    // ---- unfurl metadata on shared links ---------------------------------

    public function test_the_guest_page_carries_open_graph_tags(): void
    {
        $this->mockThread([]);

        $this->get('/r/php/comments/abc123/x')
            ->assertOk()
            ->assertSee('property="og:title" content="A post"', false)
            ->assertSee('property="og:description" content="plain body text"', false);
    }

    public function test_nsfw_posts_unfurl_without_an_image(): void
    {
        $this->mockThread([], [
            'over_18' => true,
            'preview' => ['images' => [['source' => ['url' => 'https://i.redd.it/xyz.jpg']]]],
        ]);

        $html = $this->get('/r/php/comments/abc123/x')->getContent();

        $this->assertStringNotContainsString('og:image', $html);
    }

    public function test_the_owner_page_has_no_open_graph_tags(): void
    {
        // The app itself is private; only the shared page advertises content.
        $this->mockThread([]);

        $html = $this->actingAs(User::factory()->create())
            ->get('/r/php/comments/abc123/x')->getContent();

        $this->assertStringNotContainsString('og:title', $html);
    }
}
