<?php

namespace Tests\Feature;

use App\Models\Feed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageTitleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every page renders and carries a title that names the page, not a bare
     * "reader". Guards the layout against the parse/runtime errors a
     * conditional @hasSection title previously caused.
     */
    public function test_tab_root_pages_render_with_contextual_titles(): void
    {
        $user = User::factory()->create();
        $feed = Feed::create(['user_id' => $user->id, 'name' => 'Morning Read', 'slug' => 'morning-read']);

        $cases = [
            '/' => '<title>Home · reader</title>',
            '/feeds' => '<title>Feeds · reader</title>',
            '/subs' => '<title>Subscriptions · reader</title>',
            '/f/morning-read' => '<title>Morning Read · reader</title>',
        ];

        foreach ($cases as $url => $expected) {
            $res = $this->actingAs($user)->get($url);
            $res->assertOk();
            $this->assertStringContainsString($expected, $res->getContent(), "wrong title for {$url}");
        }
    }

    /** The post page titles itself with the post's own title. */
    public function test_post_page_uses_the_post_title(): void
    {
        $this->mock(\App\Services\Reddit::class, function ($m) {
            $m->shouldReceive('comments')->andReturn([
                ['data' => ['children' => [['data' => [
                    'title' => 'Balls on the Falls rant',
                    'subreddit' => 'northernireland',
                    'author' => 'someone',
                    'id' => '1v8xm6t',
                    'created_utc' => 1750000000,
                    'score' => 42,
                    'num_comments' => 3,
                    'selftext_html' => null,
                    'is_self' => true,
                    'permalink' => '/r/northernireland/comments/1v8xm6t/x',
                ]]]]],
                ['data' => ['children' => []]],
            ]);
        });

        $res = $this->actingAs(User::factory()->create())
            ->get('/r/northernireland/comments/1v8xm6t/balls-on-the-falls-rant');

        $res->assertOk();
        $this->assertStringContainsString(
            '<title>Balls on the Falls rant · r/northernireland · reader</title>',
            $res->getContent(),
        );
    }

    /** A post that fails to load must still render, not blow up on a null post. */
    public function test_post_page_survives_a_missing_post(): void
    {
        $this->mock(\App\Services\Reddit::class, function ($m) {
            $m->shouldReceive('comments')->andThrow(new \App\Exceptions\RedditException('nope'));
        });

        $res = $this->actingAs(User::factory()->create())
            ->get('/r/northernireland/comments/1v8xm6t/balls-on-the-falls-rant');

        $res->assertOk();
        $this->assertStringContainsString('<title>Post · r/northernireland · reader</title>', $res->getContent());
    }
}
