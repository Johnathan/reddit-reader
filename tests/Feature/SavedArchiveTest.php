<?php

namespace Tests\Feature;

use App\Models\Folder;
use App\Models\SavedItem;
use App\Models\Tag;
use App\Models\User;
use App\Services\Reddit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedArchiveTest extends TestCase
{
    use RefreshDatabase;

    private function redditReturns(array $post, array $comments = []): void
    {
        $this->mock(Reddit::class, function ($m) use ($post, $comments) {
            $m->shouldReceive('comments')->andReturn([
                ['data' => ['children' => [['data' => $post]]]],
                ['data' => ['children' => array_map(fn ($c) => ['data' => $c], $comments)]],
            ]);
        });
    }

    private function postData(array $o = []): array
    {
        return array_merge([
            'id' => 'abc123', 'title' => 'How Laravel queues actually work', 'subreddit' => 'php',
            'author' => 'bob', 'created_utc' => 1750000000, 'score' => 900, 'num_comments' => 12,
            'selftext_html' => '<div class="md"><p>You need supervisor and horizon.</p></div>',
            'permalink' => '/r/php/comments/abc123/x',
        ], $o);
    }

    // ---- archiving -------------------------------------------------------

    public function test_saving_a_post_keeps_a_copy_of_its_body(): void
    {
        $user = User::factory()->create();
        $this->redditReturns($this->postData());

        $this->actingAs($user)->postJson('/saved', [
            'kind' => 'post', 'reddit_id' => 'abc123', 'subreddit' => 'php',
        ])->assertOk()->assertJson(['saved' => true, 'count' => 1]);

        $item = $user->savedItems()->first();
        $this->assertSame('post', $item->kind);
        $this->assertSame('How Laravel queues actually work', $item->title);
        $this->assertStringContainsString('supervisor and horizon', $item->body_html);
        $this->assertSame('bob', $item->author);
    }

    public function test_saving_a_comment_keeps_its_body_and_its_parent_post(): void
    {
        $user = User::factory()->create();
        $this->redditReturns($this->postData(), [[
            'id' => 'cmt1', 'author' => 'carol', 'subreddit' => 'php', 'score' => 55,
            'created_utc' => 1750000100, 'body_html' => '<div class="md"><p>Use Horizon for metrics.</p></div>',
            'permalink' => '/r/php/comments/abc123/x/cmt1',
        ]]);

        $this->actingAs($user)->postJson('/saved', [
            'kind' => 'comment', 'reddit_id' => 'cmt1', 'subreddit' => 'php', 'post_id' => 'abc123',
        ])->assertOk();

        $item = $user->savedItems()->first();
        $this->assertSame('comment', $item->kind);
        $this->assertSame('carol', $item->author);
        $this->assertStringContainsString('Horizon for metrics', $item->body_html);
        $this->assertSame('abc123', $item->post_id);
        $this->assertSame('How Laravel queues actually work', $item->post_title);
    }

    public function test_a_comment_without_its_post_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/saved', [
            'kind' => 'comment', 'reddit_id' => 'cmt1', 'subreddit' => 'php',
        ])->assertStatus(422);
    }

    public function test_archived_content_survives_the_original_being_deleted(): void
    {
        $user = User::factory()->create();
        $this->redditReturns($this->postData());

        $this->actingAs($user)->postJson('/saved', [
            'kind' => 'post', 'reddit_id' => 'abc123', 'subreddit' => 'php',
        ])->assertOk();

        // Reddit now returns nothing for it — the post is gone.
        $this->mock(Reddit::class, function ($m) {
            $m->shouldReceive('comments')->andReturn([['data' => ['children' => []]], ['data' => ['children' => []]]]);
        });

        $html = $this->actingAs($user)->get('/saved')->getContent();

        $this->assertStringContainsString('How Laravel queues actually work', $html);
        $this->assertStringContainsString('supervisor and horizon', $html);
    }

    public function test_body_html_is_sanitized_before_being_stored(): void
    {
        $user = User::factory()->create();
        $this->redditReturns($this->postData([
            'selftext_html' => '<p>hi</p><script>alert(1)</script>',
        ]));

        $this->actingAs($user)->postJson('/saved', [
            'kind' => 'post', 'reddit_id' => 'abc123', 'subreddit' => 'php',
        ])->assertOk();

        $this->assertStringNotContainsString('<script', $user->savedItems()->first()->body_html);
    }

    public function test_saving_twice_updates_rather_than_duplicates(): void
    {
        $user = User::factory()->create();
        $this->redditReturns($this->postData());

        $payload = ['kind' => 'post', 'reddit_id' => 'abc123', 'subreddit' => 'php'];
        $this->actingAs($user)->postJson('/saved', $payload)->assertOk();
        $this->actingAs($user)->postJson('/saved', $payload)->assertOk()->assertJson(['count' => 1]);

        $this->assertSame(1, $user->savedItems()->count());
    }

    public function test_an_item_can_be_removed(): void
    {
        $user = User::factory()->create();
        $this->redditReturns($this->postData());
        $this->actingAs($user)->postJson('/saved', ['kind' => 'post', 'reddit_id' => 'abc123', 'subreddit' => 'php']);

        $this->actingAs($user)->deleteJson('/saved/abc123')->assertOk()->assertJson(['count' => 0]);
        $this->assertSame(0, $user->savedItems()->count());
    }

    // ---- search ----------------------------------------------------------

    private function seedItems(User $user, array $rows): void
    {
        foreach ($rows as $i => $row) {
            $user->savedItems()->create(array_merge([
                'kind' => 'post', 'reddit_id' => 'id'.$i, 'subreddit' => 'php', 'author' => 'bob',
            ], $row));
        }
    }

    public function test_full_text_search_matches_bodies_titles_and_notes(): void
    {
        $user = User::factory()->create();
        $this->seedItems($user, [
            ['title' => 'Queues explained', 'body_html' => '<p>supervisor and horizon</p>'],
            ['title' => 'Testing tips', 'body_html' => '<p>pest and phpunit</p>', 'note' => 'read before the migration'],
            ['title' => 'Unrelated', 'body_html' => '<p>nothing to see</p>'],
        ]);

        $this->assertSame(1, SavedItem::search('horizon')->count(), 'body');
        $this->assertSame(1, SavedItem::search('testing')->count(), 'title');
        $this->assertSame(1, SavedItem::search('migration')->count(), 'note');
        $this->assertSame(0, SavedItem::search('kubernetes')->count());
    }

    public function test_search_stems_and_matches_prefixes(): void
    {
        $user = User::factory()->create();
        $this->seedItems($user, [['title' => 'Queues explained', 'body_html' => '<p>supervisor</p>']]);

        $this->assertSame(1, SavedItem::search('queue')->count(), 'porter stemming');
        $this->assertSame(1, SavedItem::search('superv')->count(), 'prefix');
    }

    public function test_search_ignores_html_markup(): void
    {
        $user = User::factory()->create();
        $this->seedItems($user, [['title' => 'A post', 'body_html' => '<div class="md"><p>plain words</p></div>']]);

        $this->assertSame(0, SavedItem::search('div')->count());
        $this->assertSame(0, SavedItem::search('md')->count());
        $this->assertSame(1, SavedItem::search('plain')->count());
    }

    public function test_search_does_not_choke_on_fts_operators(): void
    {
        $user = User::factory()->create();
        $this->seedItems($user, [['title' => 'A post', 'body_html' => '<p>words</p>']]);

        // Any of these would be a syntax error if passed to FTS5 unquoted.
        foreach (['AND', 'OR NOT', '"', '*', '-x', 'a OR b', 'NEAR(x y)'] as $q) {
            $this->assertIsInt(SavedItem::search($q)->count(), "choked on: {$q}");
        }
    }

    public function test_the_index_removes_deleted_items(): void
    {
        $user = User::factory()->create();
        $this->seedItems($user, [['title' => 'Queues explained', 'body_html' => '<p>horizon</p>']]);

        $user->savedItems()->first()->delete();

        $this->assertSame(0, SavedItem::search('horizon')->count());
        $this->assertSame(0, \DB::table('saved_items_fts')->count(), 'no orphaned index rows');
    }

    // ---- organising ------------------------------------------------------

    public function test_items_can_be_filed_and_tagged(): void
    {
        $user = User::factory()->create();
        $folder = $user->folders()->create(['name' => 'Reference', 'slug' => 'reference']);
        $this->seedItems($user, [['title' => 'A post', 'body_html' => '<p>x</p>']]);
        $item = $user->savedItems()->first();

        $this->actingAs($user)->patchJson("/saved/{$item->id}", [
            'folder_id' => $folder->id, 'tags' => 'laravel, Queues', 'note' => 'good thread',
        ])->assertOk();

        $item->refresh();
        $this->assertSame($folder->id, $item->folder_id);
        $this->assertSame('good thread', $item->note);
        $this->assertEqualsCanonicalizing(['laravel', 'queues'], $item->tags->pluck('slug')->all());
    }

    public function test_tags_are_reused_case_insensitively(): void
    {
        $user = User::factory()->create();
        $this->seedItems($user, [
            ['title' => 'One', 'body_html' => '<p>x</p>'],
            ['title' => 'Two', 'body_html' => '<p>y</p>'],
        ]);
        $items = $user->savedItems()->get();

        $this->actingAs($user)->patchJson("/saved/{$items[0]->id}", ['tags' => 'Laravel']);
        $this->actingAs($user)->patchJson("/saved/{$items[1]->id}", ['tags' => 'laravel']);

        $this->assertSame(1, Tag::where('user_id', $user->id)->count());
    }

    public function test_a_folder_can_be_created_inline_over_fetch(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user)->postJson('/saved/folders', ['name' => 'Reference']);

        $res->assertOk()->assertJsonPath('name', 'Reference');
        $this->assertSame(1, $user->folders()->count());

        // The plain form on /saved still gets its redirect.
        $this->actingAs($user)->post('/saved/folders', ['name' => 'Another'])->assertRedirect();
        $this->assertSame(2, $user->folders()->count());
    }

    public function test_deleting_a_folder_keeps_its_items(): void
    {
        $user = User::factory()->create();
        $folder = $user->folders()->create(['name' => 'Reference', 'slug' => 'reference']);
        $this->seedItems($user, [['title' => 'A post', 'body_html' => '<p>x</p>', 'folder_id' => $folder->id]]);

        $this->actingAs($user)->delete("/saved/folders/{$folder->id}");

        $this->assertSame(1, $user->savedItems()->count(), 'items must outlive the folder');
        $this->assertNull($user->savedItems()->first()->folder_id);
        $this->assertSame(0, Folder::count());
    }

    public function test_the_index_filters_by_folder_tag_and_kind(): void
    {
        $user = User::factory()->create();
        $folder = $user->folders()->create(['name' => 'Ref', 'slug' => 'ref']);
        $this->seedItems($user, [
            ['title' => 'Filed post', 'body_html' => '<p>a</p>', 'folder_id' => $folder->id],
            ['title' => 'Loose post', 'body_html' => '<p>b</p>'],
            ['kind' => 'comment', 'reddit_id' => 'c9', 'post_title' => 'Parent', 'body_html' => '<p>c</p>'],
        ]);

        $this->actingAs($user)->get('/saved?folder=ref')->assertSee('Filed post')->assertDontSee('Loose post');
        $this->actingAs($user)->get('/saved?kind=comment')->assertDontSee('Filed post');
    }

    // ---- isolation -------------------------------------------------------

    public function test_one_users_archive_is_invisible_to_another(): void
    {
        $mine = User::factory()->create();
        $theirs = User::factory()->create();
        $this->seedItems($theirs, [['title' => 'Their secret', 'body_html' => '<p>hidden</p>']]);
        $theirItem = $theirs->savedItems()->first();

        $this->actingAs($mine)->get('/saved')->assertOk()->assertDontSee('Their secret');
        $this->actingAs($mine)->patchJson("/saved/{$theirItem->id}", ['note' => 'hacked'])->assertNotFound();
        $this->actingAs($mine)->deleteJson('/saved/id0')->assertOk();

        $this->assertSame(1, $theirs->savedItems()->count());
        $this->assertNull($theirItem->refresh()->note);
    }

    public function test_the_archive_requires_a_login(): void
    {
        $this->get('/saved')->assertRedirect();
        $this->postJson('/saved', ['kind' => 'post', 'reddit_id' => 'x', 'subreddit' => 'php'])->assertStatus(401);
    }
}
