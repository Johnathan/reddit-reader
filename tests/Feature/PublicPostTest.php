<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Reddit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Shared post links: a logged-out visitor can read the thread — and nothing
 * else. No app chrome, no actions, no account data, no way to browse on.
 */
class PublicPostTest extends TestCase
{
    use RefreshDatabase;

    private function mockThread(): void
    {
        $this->mock(Reddit::class, function ($m) {
            $m->shouldReceive('comments')->andReturn([
                ['data' => ['children' => [['data' => [
                    'id' => 'abc123', 'title' => 'A shared post', 'subreddit' => 'php',
                    'author' => 'bob', 'created_utc' => 1750000000, 'score' => 99, 'num_comments' => 1,
                    'is_self' => true, 'selftext_html' => '<p>the body</p>', 'permalink' => '/r/php/x',
                ]]]]],
                ['data' => ['children' => [['kind' => 't1', 'data' => [
                    'id' => 'c1', 'author' => 'carol', 'subreddit' => 'php', 'score' => 4,
                    'created_utc' => 1750000100, 'body_html' => '<p>a comment</p>',
                ]]]]],
            ]);
        });
    }

    public function test_a_guest_can_read_a_shared_post(): void
    {
        $this->mockThread();

        $res = $this->get('/r/php/comments/abc123/a-shared-post');

        $res->assertOk();
        $res->assertSee('A shared post');
        $res->assertSee('the body', false);
        $res->assertSee('a comment', false);
        $res->assertSee(route('login'), false);
    }

    public function test_the_guest_page_has_no_app_chrome_or_actions(): void
    {
        $this->mockThread();

        $html = $this->get('/r/php/comments/abc123/x')->getContent();

        // None of the logged-in shell (sidebar, tab bar, dock, search).
        $this->assertStringNotContainsString('app-topbar', $html);
        $this->assertStringNotContainsString('app-tabbar', $html);
        $this->assertStringNotContainsString('app-sidebar', $html);
        $this->assertStringNotContainsString('reading-dock', $html);

        // None of the interactions.
        $this->assertStringNotContainsString('data-later', $html);
        $this->assertStringNotContainsString('data-archive', $html);
        $this->assertStringNotContainsString('data-sub-toggle', $html);

        // The subreddit name is text, not a door into the rest of the app.
        $this->assertStringNotContainsString('href="'.route('subreddit.show', 'php').'"', $html);
    }

    public function test_a_guest_visit_records_nothing(): void
    {
        $this->mockThread();
        User::factory()->create();

        $this->get('/r/php/comments/abc123/x')->assertOk();

        $this->assertDatabaseCount('seen_items', 0);
    }

    public function test_the_owner_still_gets_the_full_app(): void
    {
        $this->mockThread();
        $user = User::factory()->create();

        $res = $this->actingAs($user)->get('/r/php/comments/abc123/x');

        $res->assertOk();
        $res->assertSee('app-tabbar', false);
        $res->assertSee('A shared post');

        // And the visit is recorded as read, as before.
        $this->assertDatabaseCount('seen_items', 1);
    }

    public function test_a_guest_still_cannot_browse_anywhere_else(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/r/php')->assertRedirect(route('login'));
        $this->get('/explore')->assertRedirect(route('login'));
        $this->get('/reading-list')->assertRedirect(route('login'));
        $this->get('/saved')->assertRedirect(route('login'));
    }
}
